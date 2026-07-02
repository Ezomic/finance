@extends('layouts.app')
@section('title', $account->name)
@section('subtitle', $account->typeLabel())
@section('content')

<div class="flex items-center justify-between mb-6">
    <div class="font-display text-3xl font-semibold {{ $account->balance >= 0 ? 'text-moss-700' : 'text-clay' }}">
        {{ $account->currency }} {{ number_format($account->balance, 2) }}
    </div>
    <a href="{{ route('accounts.edit', $account) }}" class="btn-secondary">Edit account</a>
</div>

<div class="card p-6 mb-6">
    <h2 class="font-display text-lg font-semibold mb-3">{{ $account->type === 'investment' ? 'Record a balance snapshot' : 'Set current balance' }}</h2>
    <p class="text-sm text-ink/60 mb-4">
        @if ($account->type === 'investment')
            Investment values don't move through transactions — log the current balance whenever you check your statement.
        @else
            Reconcile your balance as of today. Transactions dated on or before this date won't change it — only transactions dated after will, so you can safely import older statements afterwards without throwing off your total.
        @endif
    </p>
    <form method="POST" action="{{ route('accounts.snapshot', $account) }}" class="flex flex-wrap gap-3">
        @csrf
        <input type="date" name="date" value="{{ now()->format('Y-m-d') }}" required class="input max-w-[160px]">
        <input type="number" step="0.01" name="balance" placeholder="Balance" required class="input max-w-[160px]">
        <button type="submit" class="btn-primary">Save {{ $account->type === 'investment' ? 'snapshot' : 'balance' }}</button>
    </form>
    @if ($account->netWorthSnapshots->isNotEmpty())
        <p class="text-xs text-ink/40 mt-3">Last set: {{ $account->netWorthSnapshots->first()->date->format('M j, Y') }} — {{ $account->currency }} {{ number_format($account->netWorthSnapshots->first()->balance, 2) }}</p>
    @endif
</div>

<div class="card p-6">
    <h2 class="font-display text-lg font-semibold mb-4">Recent activity</h2>
    @if ($account->transactions->isEmpty())
        <p class="text-sm text-ink/50">No transactions recorded on this account yet.</p>
    @else
        <div class="divide-y divide-moss-100">
            @foreach ($account->transactions as $t)
                <div class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <div class="font-medium">{{ $t->description ?: ($t->category->name ?? ucfirst($t->type)) }}</div>
                        <div class="text-xs text-ink/50">{{ $t->date->format('M j, Y') }}</div>
                    </div>
                    <div class="font-medium {{ $t->type === 'income' ? 'text-moss-700' : ($t->type === 'expense' ? 'text-clay' : 'text-ink/60') }}">
                        {{ $t->type === 'expense' ? '-' : ($t->type === 'income' ? '+' : '') }}{{ number_format($t->amount, 2) }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
