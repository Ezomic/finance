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

@if ($subscriptions->isEmpty())
    <div class="card p-10 text-center text-ink/50">
        No recurring charges detected yet. A subscription shows up here once we see the same amount from the same payee and account at least twice, roughly a month apart.
    </div>
@else
    <div class="card overflow-hidden">
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
@endsection
