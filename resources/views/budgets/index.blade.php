@extends('layouts.app')
@section('title', 'Budgets')
@section('subtitle', 'Set a monthly ceiling for each spending category')
@section('content')

<div class="flex items-center justify-between mb-6">
    <form method="GET" class="flex items-center gap-2">
        <input type="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()" class="input max-w-[180px]">
    </form>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
        @forelse ($budgets as $budget)
            <div class="card p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full" style="background-color: {{ $budget->category->color }}"></span>
                        <span class="font-medium">{{ $budget->category->name }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-ink/60">{{ number_format($budget->spent(), 2) }} / {{ number_format($budget->amount, 2) }}</span>
                        <form method="POST" action="{{ route('budgets.destroy', $budget) }}" onsubmit="return confirm('Remove this budget?');">
                            @csrf @method('DELETE')
                            <button class="text-xs text-clay hover:underline">Remove</button>
                        </form>
                    </div>
                </div>
                <div class="h-2 rounded-full bg-moss-50 overflow-hidden">
                    <div class="h-full rounded-full {{ $budget->percentUsed() >= 100 ? 'bg-clay' : 'bg-moss-500' }}" style="width: {{ $budget->percentUsed() }}%"></div>
                </div>
            </div>
        @empty
            <div class="card p-10 text-center text-ink/50 text-sm">No budgets set for {{ $month->format('F Y') }} yet.</div>
        @endforelse
    </div>

    <div class="card p-6 h-fit">
        <h2 class="font-display text-lg font-semibold mb-4">Set a budget</h2>
        <form method="POST" action="{{ route('budgets.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <div>
                <label class="label">Category</label>
                <select name="category_id" required class="input">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Monthly amount</label>
                <input type="number" step="0.01" name="amount" required class="input">
            </div>
            <button type="submit" class="btn-primary w-full">Save budget</button>
        </form>
    </div>
</div>
@endsection
