@extends('layouts.app')
@section('title', 'Accounts')
@section('subtitle', 'Every place the household keeps money')
@section('content')

<div class="flex justify-end mb-6">
    <a href="{{ route('accounts.create') }}" class="btn-primary">+ Add account</a>
</div>

@if ($accounts->isEmpty())
    <div class="card p-10 text-center text-ink/50 text-sm">No accounts yet. Add a checking account, credit card, or investment account to get started.</div>
@else
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($accounts as $account)
            <a href="{{ route('accounts.show', $account) }}" class="card p-5 block hover:border-moss-300 transition-colors {{ $account->is_archived ? 'opacity-50' : '' }}">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs uppercase tracking-wide text-ink/50">{{ $account->typeLabel() }}</span>
                    @if ($account->is_archived)
                        <span class="text-xs text-ink/40">Archived</span>
                    @endif
                </div>
                <div class="font-display text-lg font-semibold mb-1">{{ $account->name }}</div>
                <div class="text-2xl font-semibold {{ $account->balance >= 0 ? 'text-moss-700' : 'text-clay' }}">
                    {{ $account->currency }} {{ number_format($account->balance, 2) }}
                </div>
                <div class="text-xs text-ink/40 mt-2">Owner: {{ $account->owner->name ?? 'Shared' }}</div>
            </a>
        @endforeach
    </div>
@endif
@endsection
