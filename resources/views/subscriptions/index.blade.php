@extends('layouts.app')
@section('title', 'Subscriptions')
@section('subtitle', 'Recurring charges detected from your transaction history')
@section('content')

<div class="grid gap-6 md:grid-cols-2 mb-8">
    <div class="card p-6">
        <div class="text-xs uppercase tracking-wide text-ink/50 mb-1">Active monthly cost</div>
        <div class="font-display text-3xl font-semibold text-clay">{{ number_format($activeMonthlyCost, 2) }}</div>
        <div class="text-xs text-ink/40 mt-1">{{ $subscriptions->where('is_stale', false)->count() }} active subscription(s)</div>
    </div>
    <div class="card p-6">
        <div class="text-xs uppercase tracking-wide text-ink/50 mb-1">Projected annual cost</div>
        <div class="font-display text-3xl font-semibold text-clay">{{ number_format($activeAnnualCost, 2) }}</div>
        <div class="text-xs text-ink/40 mt-1">Based on each subscription's own cadence</div>
    </div>
</div>

@if ($priceChanges->isNotEmpty())
    <div class="card overflow-hidden mb-8">
        <div class="px-4 py-3 border-b border-moss-100">
            <h2 class="font-display text-lg font-semibold">Price changes</h2>
            <p class="text-xs text-ink/50">Recurring charges that got more expensive since their previous charge.</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-moss-50 text-left text-xs uppercase tracking-wide text-ink/50">
                <tr>
                    <th class="px-4 py-3">Subscription</th>
                    <th class="px-4 py-3">Account</th>
                    <th class="px-4 py-3 text-right">Was</th>
                    <th class="px-4 py-3 text-right">Now</th>
                    <th class="px-4 py-3 text-right">Change</th>
                    <th class="px-4 py-3">Since</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-moss-100">
                @foreach ($priceChanges as $change)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $change['label'] }}</td>
                        <td class="px-4 py-3">{{ $change['account']->name }}</td>
                        <td class="px-4 py-3 text-right text-ink/60">{{ number_format($change['old_amount'], 2) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-clay">{{ number_format($change['new_amount'], 2) }}</td>
                        <td class="px-4 py-3 text-right text-clay">+{{ $change['percent_change'] !== null ? $change['percent_change'].'%' : '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $change['changed_on']->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($subscriptions->isEmpty())
    <div class="card p-10 text-center text-ink/50">
        No recurring charges detected yet. A subscription shows up here once we see the same amount from the same payee and account at least twice, roughly a month apart.
    </div>
@else
    <div class="card overflow-hidden mb-8">
        <table class="w-full text-sm">
            <thead class="bg-moss-50 text-left text-xs uppercase tracking-wide text-ink/50">
                <tr>
                    <th class="px-4 py-3">Subscription</th>
                    <th class="px-4 py-3">Account</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-right">Annualized</th>
                    <th class="px-4 py-3">Cadence</th>
                    <th class="px-4 py-3">Last charged</th>
                    <th class="px-4 py-3">Next expected</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-moss-100">
                @foreach ($subscriptions as $sub)
                    <tr class="{{ $sub['is_stale'] ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $sub['label'] }}</div>
                            <div class="text-xs text-ink/50">{{ $sub['count'] }} charges seen</div>
                        </td>
                        <td class="px-4 py-3">{{ $sub['account']->name }}</td>
                        <td class="px-4 py-3">{{ $sub['category']->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-medium text-clay">{{ number_format($sub['amount'], 2) }}</td>
                        <td class="px-4 py-3 text-right text-ink/60">{{ number_format($sub['annual_cost'], 2) }}</td>
                        <td class="px-4 py-3 text-ink/60 whitespace-nowrap">every ~{{ $sub['avg_interval_days'] }} days</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $sub['last_date']->format('M j, Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($sub['is_stale'])
                                <span class="text-xs text-clay">Possibly canceled</span>
                            @else
                                {{ $sub['next_expected_date']->format('M j, Y') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="mb-4">
    <h2 class="font-display text-lg font-semibold">Recurring income</h2>
    <p class="text-xs text-ink/50">Salary and other income seen at least twice, roughly the same interval apart.</p>
</div>
@if ($recurringIncome->isEmpty())
    <div class="card p-10 text-center text-ink/50">No recurring income detected yet.</div>
@else
    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-moss-50 text-left text-xs uppercase tracking-wide text-ink/50">
                <tr>
                    <th class="px-4 py-3">Source</th>
                    <th class="px-4 py-3">Account</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3">Cadence</th>
                    <th class="px-4 py-3">Last received</th>
                    <th class="px-4 py-3">Next expected</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-moss-100">
                @foreach ($recurringIncome as $income)
                    <tr class="{{ $income['is_stale'] ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $income['label'] }}</div>
                            <div class="text-xs text-ink/50">{{ $income['count'] }} payments seen</div>
                        </td>
                        <td class="px-4 py-3">{{ $income['account']->name }}</td>
                        <td class="px-4 py-3 text-right font-medium text-moss-700">{{ number_format($income['amount'], 2) }}</td>
                        <td class="px-4 py-3 text-ink/60 whitespace-nowrap">every ~{{ $income['avg_interval_days'] }} days</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $income['last_date']->format('M j, Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($income['is_stale'])
                                <span class="text-xs text-clay">May have stopped</span>
                            @else
                                {{ $income['next_expected_date']->format('M j, Y') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
