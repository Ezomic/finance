@php
    $initialSplits = isset($transaction) && $transaction->is_split
        ? $transaction->splits->map(fn ($s) => ['category_id' => $s->category_id, 'amount' => (float) $s->amount, 'description' => $s->description])->values()
        : collect();
@endphp
<div x-show="type !== 'transfer'"
     x-data="{
         splitting: {{ $initialSplits->isNotEmpty() ? 'true' : 'false' }},
         splits: {{ $initialSplits->isNotEmpty() ? $initialSplits->toJson() : "[{ category_id: '', amount: null, description: '' }]" }},
     }">
    <label class="flex items-center gap-2 text-sm cursor-pointer mb-2">
        <input type="checkbox" x-model="splitting" class="rounded border-moss-300">
        Split across multiple categories
    </label>

    <div x-show="!splitting">
        <label class="label">Category</label>
        <select name="category_id" class="input">
            <option value="">None</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(($transaction->category_id ?? null) === $category->id)>{{ $category->parent_id ? '— ' : '' }}{{ $category->name }} ({{ $category->type }})</option>
            @endforeach
        </select>
    </div>

    <div x-show="splitting" class="space-y-3 rounded-xl border border-moss-100 p-4">
        <template x-for="(split, i) in splits" :key="i">
            <div class="grid grid-cols-[1fr_110px_auto] gap-2 items-center">
                <select :name="`splits[${i}][category_id]`" x-model="split.category_id" class="input">
                    <option value="">None</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->parent_id ? '— ' : '' }}{{ $category->name }} ({{ $category->type }})</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" :name="`splits[${i}][amount]`" x-model.number="split.amount" placeholder="Amount" class="input">
                <button type="button" @click="splits.splice(i, 1)" class="text-xs text-clay hover:underline" x-show="splits.length > 1">Remove</button>
            </div>
        </template>
        <div class="flex items-center justify-between">
            <button type="button" @click="splits.push({ category_id: '', amount: null, description: '' })" class="btn-secondary text-xs px-3 py-1.5">+ Add split</button>
            <p class="text-xs text-ink/50">
                Remaining: <span x-text="(amount - splits.reduce((sum, s) => sum + (parseFloat(s.amount) || 0), 0)).toFixed(2)"></span>
            </p>
        </div>
    </div>
</div>
