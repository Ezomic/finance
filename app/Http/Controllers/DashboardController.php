<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Support\SpendingInsights;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $household = $this->household();
        $insights = SpendingInsights::generate($household);

        $accounts = $household->accounts()->where('is_archived', false)->get();
        $netWorth = $accounts->sum->balance;

        $thisMonth = Carbon::now()->startOfMonth();
        $income = $household->transactions()->where('type', 'income')->forMonth($thisMonth)->sum('amount');
        $expense = $household->transactions()->where('type', 'expense')->forMonth($thisMonth)->sum('amount');

        $recentTransactions = $household->transactions()
            ->with(['account', 'category', 'user'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $budgets = $household->budgets()
            ->with('category')
            ->whereYear('month', $thisMonth->year)
            ->whereMonth('month', $thisMonth->month)
            ->get();

        $upcomingBills = $household->bills()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Bill $bill) => $bill->nextDueDate())
            ->filter(fn (Bill $bill) => ! $bill->isPaidThisCycle())
            ->take(5);

        return view('dashboard.index', compact(
            'accounts', 'netWorth', 'income', 'expense', 'recentTransactions', 'budgets', 'upcomingBills', 'insights'
        ));
    }
}
