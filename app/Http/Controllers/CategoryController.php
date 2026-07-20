<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = $this->household()->categoriesTree();

        return view('categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $household = $this->household();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'color' => ['required', 'string', 'max:7'],
            'parent_id' => ['nullable', $this->parentRule($household)],
        ]);

        if (! empty($data['parent_id'])) {
            /** @var Category $parent */
            $parent = Category::findOrFail($data['parent_id']);
            $data['type'] = $parent->type;
        }

        $data['household_id'] = $household->id;

        Category::create($data);

        return back()->with('status', 'Category added.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->abortUnlessOwned($category);
        $household = $this->household();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'color' => ['required', 'string', 'max:7'],
            'parent_id' => [
                'nullable',
                $this->parentRule($household),
                function ($attribute, $value, $fail) use ($category) {
                    if (! $value) {
                        return;
                    }
                    if ((int) $value === $category->id) {
                        $fail('A category cannot be its own parent.');
                    }
                    if ($category->children()->exists()) {
                        $fail('A category with subcategories cannot itself become a subcategory.');
                    }
                },
            ],
        ]);

        if (! empty($data['parent_id'])) {
            /** @var Category $parent */
            $parent = Category::findOrFail($data['parent_id']);
            $data['type'] = $parent->type;
        }

        $category->update($data);

        return back()->with('status', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->abortUnlessOwned($category);
        $category->delete();

        return back()->with('status', 'Category removed.');
    }

    /**
     * A parent must belong to the same household and be top-level itself —
     * subcategories are capped at one level deep.
     */
    private function parentRule(Household $household): Exists
    {
        return Rule::exists('categories', 'id')->where(
            fn ($query) => $query->where('household_id', $household->id)->whereNull('parent_id'),
        );
    }
}
