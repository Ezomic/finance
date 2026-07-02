@extends('layouts.app')
@section('title', 'Edit ' . $account->name)
@section('content')
<div class="card p-6 max-w-lg">
    <form method="POST" action="{{ route('accounts.update', $account) }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="label">Name</label>
            <input type="text" name="name" value="{{ old('name', $account->name) }}" required class="input">
        </div>
        <div>
            <label class="label">Type</label>
            <select name="type" required class="input">
                @foreach (['checking' => 'Checking', 'savings' => 'Savings', 'credit' => 'Credit card', 'cash' => 'Cash', 'investment' => 'Investment'] as $value => $label)
                    <option value="{{ $value }}" @selected($account->type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label">Currency</label>
                <input type="text" name="currency" value="{{ old('currency', $account->currency) }}" maxlength="3" required class="input uppercase">
            </div>
            <div>
                <label class="label">Opening balance</label>
                <input type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance', $account->opening_balance) }}" required class="input">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_archived" value="1" @checked($account->is_archived) class="rounded border-moss-300 text-moss-600">
            Archived
        </label>
        <div class="flex gap-3">
            <button type="submit" class="btn-primary flex-1">Save changes</button>
        </div>
    </form>

    <form method="POST" action="{{ route('accounts.destroy', $account) }}" class="mt-4" onsubmit="return confirm('Delete this account and all its transactions?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-danger w-full">Delete account</button>
    </form>
</div>
@endsection
