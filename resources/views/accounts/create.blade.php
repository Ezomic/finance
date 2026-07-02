@extends('layouts.app')
@section('title', 'Add account')
@section('content')
<div class="card p-6 max-w-lg">
    <form method="POST" action="{{ route('accounts.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="input">
        </div>
        <div>
            <label class="label">Type</label>
            <select name="type" required class="input">
                <option value="checking">Checking</option>
                <option value="savings">Savings</option>
                <option value="credit">Credit card</option>
                <option value="cash">Cash</option>
                <option value="investment">Investment</option>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label">Currency</label>
                <input type="text" name="currency" value="USD" maxlength="3" required class="input uppercase">
            </div>
            <div>
                <label class="label">Opening balance</label>
                <input type="number" step="0.01" name="opening_balance" value="0" required class="input">
            </div>
        </div>
        <button type="submit" class="btn-primary w-full">Add account</button>
    </form>
</div>
@endsection
