@extends('layouts.app')
@section('title', 'Add transaction')
@section('content')
<div class="card p-6 max-w-lg">
    <form method="POST" action="{{ route('transactions.store') }}" class="space-y-4" x-data="{ type: 'expense', amount: null }">
        @csrf
        <div>
            <label class="label">Type</label>
            <select name="type" x-model="type" required class="input">
                <option value="expense">Expense</option>
                <option value="income">Income</option>
                <option value="transfer">Transfer</option>
            </select>
        </div>
        <div>
            <label class="label">Account</label>
            <select name="account_id" required class="input">
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="type === 'transfer'">
            <label class="label">Transfer to</label>
            <select name="transfer_account_id" class="input">
                <option value="">Select account</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }}</option>
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
                <input type="date" name="date" value="{{ now()->format('Y-m-d') }}" required class="input">
            </div>
        </div>
        <div>
            <label class="label">Description</label>
            <input type="text" name="description" class="input">
        </div>
        <button type="submit" class="btn-primary w-full">Save transaction</button>
    </form>
</div>
@endsection
