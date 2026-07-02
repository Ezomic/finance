@extends('layouts.app')
@section('title', 'Categorize')
@section('subtitle', 'Clear out uncategorized transactions, grouped by similar payee')
@section('content')

<style>[x-cloak] { display: none !important; }</style>

@if ($importBatch)
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-moss-300 bg-moss-50 px-4 py-3">
        <p class="text-sm text-moss-700">Only showing transactions from your last import.</p>
        <a href="{{ route('categorize.index') }}" class="text-sm text-moss-700 hover:underline">Show all uncategorized</a>
    </div>
@endif

@if ($groups->isEmpty())
    <div class="card p-10 text-center text-ink/50">
        @if ($importBatch)
            Every transaction from that import is categorized. 🎉
            <a href="{{ route('transactions.index', ['import_batch' => $importBatch]) }}" class="text-moss-700 hover:underline">View them</a>
        @else
            Nothing to categorize — every transaction has a category. 🎉
        @endif
    </div>
@else
    <p class="text-sm text-ink/60 mb-6">
        {{ $groups->sum(fn ($g) => $g['transactions']->count()) }} uncategorized transaction(s) in {{ $groups->count() }} group(s).
        Review each group, adjust the selection if needed, pick a category, and apply.
    </p>

    <div class="space-y-6">
        @foreach ($groups as $group)
            <div class="card p-6"
                 x-data="{
                    allIds: {{ \Illuminate\Support\Js::from($group['transactions']->pluck('id')) }},
                    selected: {{ \Illuminate\Support\Js::from($group['transactions']->pluck('id')) }},
                    categoryChoice: '{{ $group['suggested_category_id'] ?? 'new' }}',
                    get selectedCount() { return this.selected.length; },
                 }">
                <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                    <div>
                        <h2 class="font-display text-lg font-semibold">{{ $group['label'] }}</h2>
                        <p class="text-xs text-ink/50 mt-1">
                            {{ $group['transactions']->count() }} transaction(s) ·
                            <span class="capitalize">{{ $group['type'] }}</span> ·
                            total {{ number_format($group['total'], 2) }}
                        </p>
                    </div>
                    <div class="flex gap-2 text-xs">
                        <button type="button" class="btn-secondary !px-3 !py-1" @click="selected = [...allIds]">Select all</button>
                        <button type="button" class="btn-secondary !px-3 !py-1" @click="selected = []">Select none</button>
                    </div>
                </div>

                <form method="POST" action="{{ route('categorize.apply') }}" class="grid md:grid-cols-3 gap-6">
                    @csrf
                    <input type="hidden" name="import_batch" value="{{ $importBatch }}">

                    <div class="md:col-span-2 max-h-64 overflow-y-auto rounded-xl border border-moss-100 divide-y divide-moss-100">
                        @foreach ($group['transactions'] as $t)
                            <label class="flex items-center gap-3 px-3 py-2 text-sm hover:bg-moss-50 cursor-pointer">
                                <input type="checkbox" name="transaction_ids[]" :value="{{ $t->id }}" x-model="selected" class="rounded border-moss-300 text-moss-700 focus:ring-moss-500">
                                <span class="whitespace-nowrap text-ink/50">{{ $t->date->format('M j, Y') }}</span>
                                <span class="flex-1 truncate">{{ $t->description ?: '—' }}</span>
                                <span class="font-medium {{ $t->type === 'income' ? 'text-moss-700' : 'text-clay' }}">
                                    {{ $t->type === 'expense' ? '-' : '+' }}{{ number_format($t->amount, 2) }}
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="label">Category</label>
                            <select name="category_choice" x-model="categoryChoice" class="input">
                                <option value="new">+ New category</option>
                                @foreach ($categories->where('type', $group['type']) as $category)
                                    <option value="{{ $category->id }}">{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="categoryChoice === 'new'" x-cloak>
                            <label class="label">New category name</label>
                            <input type="text" name="new_category_name" value="{{ $group['suggested_new_name'] }}" class="input">
                            <input type="hidden" name="new_category_type" value="{{ $group['type'] }}">
                        </div>

                        <button type="submit" class="btn-primary w-full" :disabled="selectedCount === 0" :class="{ 'opacity-50 cursor-not-allowed': selectedCount === 0 }">
                            Categorize <span x-text="selectedCount"></span> selected
                        </button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
@endif
@endsection
