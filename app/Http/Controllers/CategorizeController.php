<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\CategoryGuesser;
use App\Support\TransactionNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CategorizeController extends Controller
{
    public function index(Request $request)
    {
        $household = $this->household();
        $importBatch = $request->string('import_batch')->toString() ?: null;

        $uncategorized = $household
            ->transactions()
            ->whereNull('category_id')
            ->where('type', '!=', 'transfer')
            ->when($importBatch, fn ($q) => $q->where('import_batch', $importBatch))
            ->orderBy('date')
            ->get();

        $categories = $household->categoriesTree();
        $historyVotes = $this->historyVotes($household);

        $groups = $uncategorized
            ->groupBy(fn (Transaction $t) => $this->groupKey($t))
            ->map(function (Collection $transactions, string $groupKey) use ($categories, $historyVotes) {
                $first = $transactions->first();
                $label = TransactionNormalizer::label($first->description ?? '');

                // 1. What has this household called similar transactions before?
                $suggestedCategoryId = null;
                if (!empty($historyVotes[$groupKey])) {
                    arsort($historyVotes[$groupKey]);
                    $suggestedCategoryId = array_key_first($historyVotes[$groupKey]);
                }

                $suggestedNewName = null;

                // 2. Fall back to keyword rules.
                if (!$suggestedCategoryId) {
                    $guessedName = CategoryGuesser::guess($first->description ?? '');

                    if ($guessedName) {
                        $existing = $categories->first(
                            fn (Category $c) => $c->type === $first->type
                                && strcasecmp($c->name, $guessedName) === 0,
                        );

                        if ($existing) {
                            $suggestedCategoryId = $existing->id;
                        } else {
                            $suggestedNewName = $guessedName;
                        }
                    }
                }

                // 3. Nothing matched — suggest a new category named after the merchant.
                if (!$suggestedCategoryId && !$suggestedNewName) {
                    $suggestedNewName = $label;
                }

                return [
                    'key' => $groupKey,
                    'label' => $label,
                    'type' => $first->type,
                    'transactions' => $transactions->sortByDesc('date')->values(),
                    'total' => $transactions->sum('amount'),
                    'suggested_category_id' => $suggestedCategoryId,
                    'suggested_new_name' => $suggestedNewName,
                ];
            })
            ->sortByDesc(fn (array $g) => $g['transactions']->count())
            ->values();

        return view('categorize.index', compact('groups', 'categories', 'importBatch'));
    }

    public function apply(Request $request)
    {
        $household = $this->household();

        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer'],
            'category_choice' => ['required', 'string'],
            'new_category_name' => ['required_if:category_choice,new', 'nullable', 'string', 'max:255'],
            'new_category_type' => ['required_if:category_choice,new', 'nullable', 'in:income,expense'],
            'import_batch' => ['nullable', 'string'],
        ]);

        if ($data['category_choice'] === 'new') {
            $category = Category::create([
                'household_id' => $household->id,
                'name' => $data['new_category_name'],
                'type' => $data['new_category_type'],
                'color' => '#4E7A48',
            ]);
        } else {
            $category = Category::findOrFail((int) $data['category_choice']);
            $this->abortUnlessOwned($category);
        }

        $count = $household
            ->transactions()
            ->whereIn('id', $data['transaction_ids'])
            ->update(['category_id' => $category->id]);

        if ($count > 0) {
            ActivityLog::create([
                'household_id' => $household->id,
                'user_id' => $request->user()->id,
                'subject_type' => Transaction::class,
                'subject_id' => null,
                'action' => 'categorized',
                'summary' => "Categorized {$count} transaction(s) as \"{$category->name}\"",
            ]);
        }

        return redirect()
            ->route('categorize.index', array_filter(['import_batch' => $data['import_batch'] ?? null]))
            ->with('status', "Categorized {$count} transaction(s) as \"{$category->name}\".");
    }

    /**
     * Maps each normalized description (+ type) seen among already
     * categorized transactions to the number of times each category was
     * used for it, so we can suggest the household's most common pick.
     */
    private function historyVotes($household): array
    {
        $history = $household
            ->transactions()
            ->whereNotNull('category_id')
            ->select('description', 'category_id', 'type')
            ->get();

        $votes = [];

        foreach ($history as $t) {
            if (trim($t->description ?? '') === '') {
                continue; // no useful signal to group on
            }

            $key = $this->groupKey($t);
            $votes[$key][$t->category_id] = ($votes[$key][$t->category_id] ?? 0) + 1;
        }

        return $votes;
    }

    private function groupKey(Transaction $t): string
    {
        return TransactionNormalizer::normalize($t->description ?? '') . '|' . $t->type;
    }
}
