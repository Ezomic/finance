<?php

namespace App\Http\Controllers;

use App\Support\CategorySpending;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index()
    {
        $household = $this->household();

        // Spending by category, current month.
        $monthStart = Carbon::now()->startOfMonth();
        $spendingByCategory = CategorySpending::byCategory($household, $monthStart);

        // Income vs expense, last 6 months.
        $months = collect(range(5, 0))->map(fn ($i) => Carbon::now()->subMonths($i)->startOfMonth());
        $incomeVsExpense = $months->map(function (Carbon $month) use ($household) {
            return [
                'label' => $month->format('M Y'),
                'income' => (float) $household->transactions()->where('type', 'income')->forMonth($month)->sum('amount'),
                'expense' => (float) $household->transactions()->where('type', 'expense')->forMonth($month)->sum('amount'),
            ];
        });

        // Net worth over time, last 12 months (each account's balance-as-of that
        // month end, honoring any balance checkpoints — see Account::balanceAsOf()).
        $accounts = $household->accounts()->where('is_archived', false)->get();
        $twelveMonths = collect(range(11, 0))->map(fn ($i) => Carbon::now()->subMonths($i)->endOfMonth());
        $netWorthOverTime = $twelveMonths->map(function (Carbon $end) use ($accounts) {
            $total = $accounts->sum(fn ($account) => $account->balanceAsOf($end));

            return ['label' => $end->format('M Y'), 'total' => round($total, 2)];
        });

        return view('reports.index', compact('spendingByCategory', 'incomeVsExpense', 'netWorthOverTime'));
    }
}
