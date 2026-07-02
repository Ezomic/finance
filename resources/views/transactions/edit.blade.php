@extends('layouts.app')
@section('title', 'Edit transaction')
@section('content')
<div class="card p-6 max-w-lg">
    <form method="POST" action="{{ route('transactions.update', $transaction) }}" class="space-y-4" x-data="{ type: '{{ $transaction->type }}', amount: {{ (float) $transaction->amount }} }">
        @csrf
        @method('PUT')
        <div>
            <label class="label">Type</label>
            <select name="type" x-model="type" required class="input">
                <option value="expense" @selected($transaction->type === 'expense')>Expense</option>
                <option value="income" @selected($transaction->type === 'income')>Income</option>
                <option value="transfer" @selected($transaction->type === 'transfer')>Transfer</option>
            </select>
        </div>
        <div>
            <label class="label">Account</label>
            <select name="account_id" required class="input">
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected($transaction->account_id === $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="type === 'transfer'">
            <label class="label">Transfer to</label>
            <select name="transfer_account_id" class="input">
                <option value="">Select account</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected($transaction->transfer_account_id === $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        @include('transactions._split-fields')
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label">Amount</label>
                <input type="number" step="0.01" name="amount" x-model.number="amount" required class="input">
            </div>
            <div>
                <label class="label">Date</label>
                <input type="date" name="date" value="{{ $transaction->date->format('Y-m-d') }}" required class="input">
            </div>
        </div>
        <div>
            <label class="label">Description</label>
            <input type="text" name="description" value="{{ $transaction->description }}" class="input">
        </div>
        <button type="submit" class="btn-primary w-full">Save changes</button>
    </form>
</div>
@endsection
