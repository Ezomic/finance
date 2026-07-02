@extends('layouts.app')
@section('title', 'Overview')
@section('subtitle', 'Where things stand today, ' . now()->format('F j, Y'))
@section('content')

<div class="grid gap-6 md:grid-cols-3 mb-8">
    <div class="card p-6">
        <div class="text-xs uppercase tracking-wide text-ink/50 mb-1">Net worth</div>
        <div class="font-display text-3xl font-semibold {{ $netWorth >= 0 ? 'text-moss-700' : 'text-clay' }}">
            {{ number_format($netWorth, 2) }}
        </div>
        <div class="text-xs text-ink/40 mt-1">Across {{ $accounts->count() }} active account{{ $accounts->count() === 1 ? '' : 's' }}</div>
    </div>
    <div class="card p-6">
        <div class="text-xs uppercase tracking-wide text-ink/50 mb-1">Income this month</div>
        <div class="font-display text-3xl font-semibold text-moss-700">{{ number_format($income, 2) }}</div>
    </div>
    <div class="card p-6">
        <div class="text-xs uppercase tracking-wide text-ink/50 mb-1">Spent this month</div>
        <div class="font-display text-3xl font-semibold text-clay">{{ number_format($expense, 2) }}</div>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="card p-6 lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-display text-lg font-semibold">Recent transactions</h2>
            <a href="{{ route('transactions.index') }}" class="text-sm text-moss-700 hover:underline">View all</a>
        </div>
        @if ($recentTransactions->isEmpty())
            <p class="text-sm text-ink/50">No transactions yet. <a href="{{ route('transactions.create') }}" class="text-moss-700 hover:underline">Add your first one</a>.</p>
        @else
            <div class="divide-y divide-moss-100">
                @foreach ($recentTransactions as $t)
                    <div class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <div class="font-medium">{{ $t->description ?: ($t->category->name ?? ucfirst($t->type)) }}</div>
                            <div class="text-xs text-ink/50">{{ $t->account->name }} · {{ $t->date->format('M j') }} · {{ $t->user->name }}</div>
                        </div>
                        <div class="font-medium {{ $t->type === 'income' ? 'text-moss-700' : ($t->type === 'expense' ? 'text-clay' : 'text-ink/60') }}">
                            {{ $t->type === 'expense' ? '-' : ($t->type === 'income' ? '+' : '') }}{{ number_format($t->amount, 2) }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="space-y-6">
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-display text-lg font-semibold">Budgets this month</h2>
                <a href="{{ route('budgets.index') }}" class="text-sm text-moss-700 hover:underline">Manage</a>
            </div>
            @if ($budgets->isEmpty())
                <p class="text-sm text-ink/50">No budgets set for this month yet.</p>
            @else
                <div class="space-y-3">
                    @foreach ($budgets as $budget)
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-medium">{{ $budget->category->name }}</span>
                                <span class="text-ink/50">{{ number_format($budget->spent(), 0) }} / {{ number_format($budget->amount, 0) }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-moss-50 overflow-hidden">
                                <div class="h-full rounded-full {{ $budget->percentUsed() >= 100 ? 'bg-clay' : 'bg-moss-500' }}" style="width: {{ $budget->percentUsed() }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-display text-lg font-semibold">Upcoming bills</h2>
                <a href="{{ route('bills.index') }}" class="text-sm text-moss-700 hover:underline">Manage</a>
            </div>
            @if ($upcomingBills->isEmpty())
                <p class="text-sm text-ink/50">Nothing due soon.</p>
            @else
                <div class="space-y-3">
                    @foreach ($upcomingBills as $bill)
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <div class="font-medium">{{ $bill->name }}</div>
                                <div class="text-xs text-ink/50">Due {{ $bill->nextDueDate()->format('M j') }}</div>
                            </div>
                            <div class="font-medium">{{ number_format($bill->amount, 2) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
