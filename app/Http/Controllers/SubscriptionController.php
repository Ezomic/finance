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

        $active = $subscriptions->where('is_stale', false);
        $activeMonthlyCost = $active->sum('amount');
        $activeAnnualCost = $active->sum('annual_cost');

        return view('subscriptions.index', compact('subscriptions', 'activeMonthlyCost', 'activeAnnualCost'));
    }
}
