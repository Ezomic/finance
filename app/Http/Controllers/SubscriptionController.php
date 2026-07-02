<?php

namespace App\Http\Controllers;

use App\Support\RecurringDetector;

class SubscriptionController extends Controller
{
    public function index()
    {
        $household = $this->household();

        $transactions = $household
            ->transactions()
            ->where('type', 'expense')
            ->with(['account', 'category'])
            ->orderBy('date')
            ->get();

        $subscriptions = RecurringDetector::detect($transactions);
        $priceChanges = RecurringDetector::detectPriceChanges($transactions);

        $active = $subscriptions->where('is_stale', false);
        $activeMonthlyCost = $active->sum('amount');
        $activeAnnualCost = $active->sum('annual_cost');

        $incomeTransactions = $household
            ->transactions()
            ->where('type', 'income')
            ->with(['account', 'category'])
            ->orderBy('date')
            ->get();

        $recurringIncome = RecurringDetector::detect($incomeTransactions);

        return view('subscriptions.index', compact(
            'subscriptions', 'activeMonthlyCost', 'activeAnnualCost', 'priceChanges', 'recurringIncome'
        ));
    }
}
