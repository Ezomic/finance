@extends('layouts.app')
@section('title', 'Bills')
@section('subtitle', 'Recurring payments and when they land')
@section('content')

<div class="flex gap-2 mb-6 text-sm">
    <span class="px-3 py-1.5 rounded-full bg-moss-100 text-moss-900 font-medium">List</span>
    <a href="{{ route('bills.calendar') }}" class="px-3 py-1.5 rounded-full text-ink/60 hover:bg-moss-50">Calendar</a>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-3">
        @forelse ($bills as $bill)
            <div class="card p-5 flex items-center justify-between {{ !$bill->is_active ? 'opacity-50' : '' }}">
                <div>
                    <div class="font-medium">{{ $bill->name }}</div>
                    <div class="text-xs text-ink/50">
                        {{ number_format($bill->amount, 2) }} · {{ ucfirst($bill->frequency) }} · Due {{ $bill->nextDueDate()->format('M j') }}
                        @if ($bill->category) · {{ $bill->category->name }} @endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if ($bill->isPaidThisCycle())
                        <span class="text-xs text-moss-700 bg-moss-50 px-2 py-1 rounded-full">Paid</span>
                    @else
                        <form method="POST" action="{{ route('bills.mark-paid', $bill) }}">
                            @csrf
                            <button class="btn-secondary text-xs px-3 py-1.5">Mark paid</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('bills.destroy', $bill) }}" onsubmit="return confirm('Delete this bill?');">
                        @csrf @method('DELETE')
                        <button class="text-xs text-clay hover:underline">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="card p-10 text-center text-ink/50 text-sm">No recurring bills tracked yet.</div>
        @endforelse
    </div>

    <div class="card p-6 h-fit">
        <h2 class="font-display text-lg font-semibold mb-4">Add a bill</h2>
        <form method="POST" action="{{ route('bills.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="label">Name</label>
                <input type="text" name="name" required class="input">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Amount</label>
                    <input type="number" step="0.01" name="amount" required class="input">
                </div>
                <div>
                    <label class="label">Due day</label>
                    <input type="number" min="1" max="28" name="due_day" required class="input">
                </div>
            </div>
            <div>
                <label class="label">Frequency</label>
                <select name="frequency" required class="input">
                    <option value="monthly">Monthly</option>
                    <option value="weekly">Weekly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </div>
            <div>
                <label class="label">Category</label>
                <select name="category_id" class="input">
                    <option value="">None</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Pay from account</label>
                <select name="account_id" class="input">
                    <option value="">None</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary w-full">Add bill</button>
        </form>
    </div>
</div>
@endsection
