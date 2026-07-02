<form method="POST" action="{{ route('categories.update', $category) }}" class="grid sm:grid-cols-2 gap-3" x-data="{ parentId: '{{ $category->parent_id }}' }">
    @csrf
    @method('PUT')
    <div>
        <label class="label">Name</label>
        <input type="text" name="name" value="{{ $category->name }}" required class="input">
    </div>

    @if ($hasChildren)
        <div class="text-xs text-ink/50 self-end pb-2">Has subcategories, so it can't be nested itself.</div>
    @else
        <div>
            <label class="label">Parent category</label>
            <select name="parent_id" x-model="parentId" class="input">
                <option value="">— None (top-level) —</option>
                @foreach ($topLevel as $top)
                    @if ($top->id !== $category->id)
                        <option value="{{ $top->id }}" @selected($category->parent_id === $top->id)>{{ $top->name }}</option>
                    @endif
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <label class="label">Type</label>
        <select name="type" required class="input">
            <option value="expense" @selected($category->type === 'expense')>Expense</option>
            <option value="income" @selected($category->type === 'income')>Income</option>
        </select>
        <p class="text-xs text-ink/40 mt-1" x-show="parentId !== ''" x-cloak>Inherits the parent's type.</p>
    </div>
    <div>
        <label class="label">Color</label>
        <input type="color" name="color" value="{{ $category->color }}" class="input h-10">
    </div>

    <div class="sm:col-span-2">
        <button type="submit" class="btn-secondary w-full">Save changes</button>
    </div>
</form>
