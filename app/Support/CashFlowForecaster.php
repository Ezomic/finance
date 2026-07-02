<?php

namespace App\Support;

use App\Models\Household;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Projects account balances forward from known recurring events (bills,
 * detected recurring income/expenses) plus a smoothed daily rate for
 * everything else (groceries, dining, etc.) based on recent history.
 */
class CashFlowForecaster
{
    private const TRAILING_DAYS = 90;

    public static function project(Household $household, int $days = 60): array
    {
        $today = Carbon::today();
        $horizonEnd = $today->copy()->addDays($days);

        $startBalance = (float) $household->accounts()->where('is_archived', false)->get()->sum->balance;

        $bills = $household->bills()->where('is_active', true)->get();

        $expenseTransactions = $household->transactions()->where('type', 'expense')->with('account')->orderBy('date')->get();
        $recurringExpenses = RecurringDetector::detect($expenseTransactions)
            ->where('is_stale', false)
            ->reject(fn (array $sub) => self::matchesABill($sub, $bills));

        $incomeTransactions = $household->transactions()->where('type', 'income')->with('account')->orderBy('date')->get();
        $recurringIncome = RecurringDetector::detect($incomeTransactions)->where('is_stale', false);

        $events = collect()
            ->merge(self::billEvents($bills, $today, $horizonEnd))
            ->merge(self::recurringEvents($recurringExpenses, $today, $horizonEnd, -1))
            ->merge(self::recurringEvents($recurringIncome, $today, $horizonEnd, 1))
            ->sortBy('date')
            ->values();

        $discretionaryDaily = self::discretionaryDailyRate($household, $today, $bills, $recurringExpenses);

        $eventsByDay = $events->groupBy(fn (array $e) => $e['date']->format('Y-m-d'));

        $points = collect();
        $balance = $startBalance;
        for ($date = $today->copy(); $date->lte($horizonEnd); $date->addDay()) {
            $balance += $eventsByDay->get($date->format('Y-m-d'), collect())->sum('amount');
            $balance -= $discretionaryDaily;
            $points->push(['date' => $date->copy(), 'balance' => round($balance, 2)]);
        }

        return [
            'points' => $points,
            'events' => $events,
            'discretionary_daily' => round($discretionaryDaily, 2),
            'start_balance' => round($startBalance, 2),
        ];
    }

    private static function matchesABill(array $sub, Collection $bills): bool
    {
        return $bills->contains(fn ($bill) => $bill->account_id === $sub['account']->id
            && abs((float) $bill->amount - $sub['amount']) < 0.01);
    }

    private static function billEvents(Collection $bills, Carbon $from, Carbon $to): Collection
    {
        return $bills->flatMap(fn ($bill) => $bill->occurrencesBetween($from, $to)->map(fn (Carbon $date) => [
            'date' => $date,
            'label' => $bill->name,
            'amount' => -(float) $bill->amount,
            'type' => 'bill',
        ]));
    }

    /**
     * @param  Collection<int, array>  $candidates  RecurringDetector::detect() output
     * @param  int  $sign  1 for income (adds to balance), -1 for expenses (subtracts)
     */
    private static function recurringEvents(Collection $candidates, Carbon $from, Carbon $to, int $sign): Collection
    {
        $events = collect();

        foreach ($candidates as $candidate) {
            $interval = $candidate['avg_interval_days'] ?: 30;
            $date = $candidate['next_expected_date']->copy();

            while ($date->lte($to)) {
                if ($date->gte($from)) {
                    $events->push([
                        'date' => $date->copy(),
                        'label' => $candidate['label'],
                        'amount' => $sign * (float) $candidate['amount'],
                        'type' => $sign > 0 ? 'income' : 'subscription',
                    ]);
                }
                $date->addDays($interval);
            }
        }

        return $events;
    }

    /**
     * Blended daily spend for everything not already captured as a bill or
     * detected recurring expense, based on the trailing window.
     */
    private static function discretionaryDailyRate(Household $household, Carbon $today, Collection $bills, Collection $recurringExpenses): float
    {
        $trailingStart = $today->copy()->subDays(self::TRAILING_DAYS);

        $trailingExpenseTotal = (float) $household->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$trailingStart, $today])
            ->sum('amount');

        $attributed = $bills->sum(fn ($bill) => $bill->occurrencesBetween($trailingStart, $today)->count() * (float) $bill->amount);

        $attributed += $recurringExpenses->sum(function (array $sub) {
            $occurrences = floor(self::TRAILING_DAYS / ($sub['avg_interval_days'] ?: 30));

            return $occurrences * $sub['amount'];
        });

        return max(0, ($trailingExpenseTotal - $attributed) / self::TRAILING_DAYS);
    }
}
