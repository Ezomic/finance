@extends('layouts.app')
@section('title', 'Categories')
@section('subtitle', 'How income and spending get labeled')
@section('content')

<style>[x-cloak] { display: none !important; }</style>

@php $topLevel = $categories->whereNull('parent_id'); @endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 card p-6">
        <h2 class="font-display text-lg font-semibold mb-4">All categories</h2>
        <div class="space-y-3">
            @forelse ($topLevel as $category)
                @php $children = $categories->where('parent_id', $category->id); @endphp
                <div class="rounded-xl border border-moss-100 overflow-hidden" x-data="{ editing: false }">
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="w-3 h-3 rounded-full" style="background-color: {{ $category->color }}"></span>
                            <div>
                                <div class="text-sm font-medium">{{ $category->name }}</div>
                                <div class="text-xs text-ink/50 capitalize">{{ $category->type }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" class="text-xs text-moss-700 hover:underline" @click="editing = !editing">Edit</button>
                            <form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Delete this category? Any subcategories become top-level.');">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs text-clay hover:underline">Remove</button>
                            </form>
                        </div>
                    </div>

                    <div x-show="editing" x-cloak class="px-4 pb-4 border-t border-moss-100 pt-4">
                        @include('categories._edit-form', ['category' => $category, 'topLevel' => $topLevel, 'hasChildren' => $children->isNotEmpty()])
                    </div>

                    @if ($children->isNotEmpty())
                        <div class="border-t border-moss-100 divide-y divide-moss-50 bg-moss-50/40">
                            @foreach ($children as $child)
                                <div x-data="{ editing: false }">
                                    <div class="flex items-center justify-between pl-10 pr-4 py-2.5">
                                        <div class="flex items-center gap-3">
                                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $child->color }}"></span>
                                            <div class="text-sm">{{ $child->name }}</div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <button type="button" class="text-xs text-moss-700 hover:underline" @click="editing = !editing">Edit</button>
                                            <form method="POST" action="{{ route('categories.destroy', $child) }}" onsubmit="return confirm('Delete this subcategory?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-xs text-clay hover:underline">Remove</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div x-show="editing" x-cloak class="pl-10 pr-4 pb-3">
                                        @include('categories._edit-form', ['category' => $child, 'topLevel' => $topLevel, 'hasChildren' => false])
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-ink/50">No categories yet.</p>
            @endforelse
        </div>
    </div>

    <div class="card p-6 h-fit" x-data="{ parentId: '' }">
        <h2 class="font-display text-lg font-semibold mb-4">Add a category</h2>
        <form method="POST" action="{{ route('categories.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="label">Name</label>
                <input type="text" name="name" required class="input">
            </div>
            <div>
                <label class="label">Parent category (optional)</label>
                <select name="parent_id" x-model="parentId" class="input">
                    <option value="">— None (top-level) —</option>
                    @foreach ($topLevel as $top)
                        <option value="{{ $top->id }}">{{ $top->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Type</label>
                <select name="type" required class="input">
                    <option value="expense">Expense</option>
                    <option value="income">Income</option>
                </select>
                <p class="text-xs text-ink/40 mt-1" x-show="parentId !== ''" x-cloak>Inherits the parent's type.</p>
            </div>
            <div>
                <label class="label">Color</label>
                <input type="color" name="color" value="#4E7A48" class="input h-10">
            </div>
            <button type="submit" class="btn-primary w-full">Add category</button>
        </form>
    </div>
</div>
@endsection
