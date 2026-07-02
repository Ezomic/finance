<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Flags recurring monthly charges (subscriptions) from a household's
 * expense history: same payee, same account, same amount, roughly a
 * month (or a whole number of months — skipped months are tolerated)
 * apart, seen at least twice.
 */
class RecurringDetector
{
    private const CYCLE_DAYS = 30;
    private const CYCLE_TOLERANCE_DAYS = 5;
    private const STALE_AFTER_DAYS = 45;

    /**
     * @param  Collection<int, Transaction>  $transactions  Expense transactions with account/category loaded.
     * @return Collection<int, array>
     */
    public static function detect(Collection $transactions): Collection
    {
        $candidates = $transactions
            ->groupBy(
                fn (Transaction $t) => TransactionNormalizer::normalize($t->description ?? '')
                    . '|' . $t->account_id
                    . '|' . number_format((float) $t->amount, 2, '.', ''),
            )
            ->filter(fn (Collection $group) => $group->count() >= 2)
            ->map(fn (Collection $group) => $group->sortBy('date')->values())
            ->mapWithKeys(function (Collection $sorted, string $key) {
                $cadence = self::monthlyCadence($sorted);

                return $cadence === null ? [] : [$key => ['sorted' => $sorted, 'cadence' => $cadence]];
            })
            ->map(function (array $data) {
                $sorted = $data['sorted'];
                $first = $sorted->first();
                $last = $sorted->last();
                $avgGap = (int) round($data['cadence']);
                $isStale = $last->date->lt(Carbon::now()->subDays(self::STALE_AFTER_DAYS));

                return [
                    'label' => TransactionNormalizer::label($first->description ?? ''),
                    'account' => $first->account,
                    'category' => $last->category,
                    'amount' => (float) $first->amount,
                    'occurrences' => $sorted,
                    'count' => $sorted->count(),
                    'avg_interval_days' => $avgGap,
                    'first_date' => $first->date,
                    'last_date' => $last->date,
                    'next_expected_date' => $last->date->copy()->addDays($avgGap ?: self::CYCLE_DAYS),
                    'annual_cost' => round((float) $first->amount * (365 / ($avgGap ?: self::CYCLE_DAYS)), 2),
                    'is_stale' => $isStale,
                ];
            })
            ->values()
            ->all();

        usort($candidates, function (array $a, array $b) {
            if ($a['is_stale'] !== $b['is_stale']) {
                return $a['is_stale'] <=> $b['is_stale'];
            }

            return $b['amount'] <=> $a['amount'];
        });

        return collect($candidates);
    }

    /**
     * Average per-month interval if every gap between occurrences is
     * (approximately) a whole number of ~30-day cycles apart — so a
     * skipped month (e.g. a ~61-day gap) still counts as monthly cadence,
     * not a broken pattern. Returns null if any gap doesn't fit.
     */
    private static function monthlyCadence(Collection $sorted): ?float
    {
        $perCycleGaps = [];

        for ($i = 1; $i < $sorted->count(); $i++) {
            $days = $sorted[$i]->date->diffInDays($sorted[$i - 1]->date, true);
            $cycles = max(1, (int) round($days / self::CYCLE_DAYS));
            $perCycle = $days / $cycles;

            if (abs($perCycle - self::CYCLE_DAYS) > self::CYCLE_TOLERANCE_DAYS) {
                return null;
            }

            $perCycleGaps[] = $perCycle;
        }

        return empty($perCycleGaps) ? null : collect($perCycleGaps)->avg();
    }
}
