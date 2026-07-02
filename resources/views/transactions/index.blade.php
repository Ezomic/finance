@extends('layouts.app')
@section('title', 'Transactions')
@section('subtitle', 'Every dollar in and out')
@section('content')

@if ($importBatch)
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-moss-300 bg-moss-50 px-4 py-3">
        <p class="text-sm text-moss-700">
            Showing {{ $transactions->total() }} transaction(s) from your last import.
            @if ($uncategorizedInBatch > 0)
                {{ $uncategorizedInBatch }} still need a category.
            @endif
        </p>
        <div class="flex gap-3 text-sm">
            @if ($uncategorizedInBatch > 0)
                <a href="{{ route('categorize.index', ['import_batch' => $importBatch]) }}" class="btn-primary !py-1.5">Categorize these</a>
            @endif
            <a href="{{ route('transactions.index') }}" class="text-moss-700 hover:underline self-center">Clear filter</a>
        </div>
    </div>
@endif

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-2">
        <input type="hidden" name="import_batch" value="{{ $importBatch }}">
        <select name="account_id" class="input max-w-[160px]" onchange="this.form.submit()">
            <option value="">All accounts</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected(request('account_id') == $account->id)>{{ $account->name }}</option>
            @endforeach
        </select>
        <select name="category_id" class="input max-w-[160px]" onchange="this.form.submit()">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="type" class="input max-w-[140px]" onchange="this.form.submit()">
            <option value="">All types</option>
            <option value="income" @selected(request('type') === 'income')>Income</option>
            <option value="expense" @selected(request('type') === 'expense')>Expense</option>
            <option value="transfer" @selected(request('type') === 'transfer')>Transfer</option>
        </select>
    </form>
    <a href="{{ route('transactions.create') }}" class="btn-primary">+ Add transaction</a>
</div>

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-moss-50 text-left text-xs uppercase tracking-wide text-ink/50">
            <tr>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Description</th>
                <th class="px-4 py-3">Account</th>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3 text-right">Amount</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-moss-100">
            @forelse ($transactions as $t)
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $t->date->format('M j, Y') }}</td>
                    <td class="px-4 py-3">{{ $t->description ?: '—' }}</td>
                    <td class="px-4 py-3">{{ $t->account->name }}@if($t->type === 'transfer') → {{ $t->transferAccount->name ?? '—' }}@endif</td>
                    <td class="px-4 py-3">{{ $t->category->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right font-medium {{ $t->type === 'income' ? 'text-moss-700' : ($t->type === 'expense' ? 'text-clay' : 'text-ink/60') }}">
                        {{ $t->type === 'expense' ? '-' : ($t->type === 'income' ? '+' : '') }}{{ number_format($t->amount, 2) }}
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('transactions.edit', $t) }}" class="text-xs text-moss-700 hover:underline">Edit</a>
                        <form method="POST" action="{{ route('transactions.destroy', $t) }}" class="inline" onsubmit="return confirm('Delete this transaction?');">
                            @csrf @method('DELETE')
                            <button class="text-xs text-clay hover:underline ml-2">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-ink/50">No transactions match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $transactions->links() }}</div>
@endsection
