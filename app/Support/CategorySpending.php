<?php

namespace App\Support;

use App\Models\Household;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Expense totals per category, accounting for split transactions: a split
 * transaction's amount is attributed to its splits' categories instead of
 * its own (null) category_id.
 */
class CategorySpending
{
    public static function forCategory(int $categoryId, Carbon $month): float
    {
        $direct = Transaction::where('category_id', $categoryId)
            ->where('type', 'expense')
            ->where('is_split', false)
            ->forMonth($month)
            ->sum('amount');

        $split = TransactionSplit::where('category_id', $categoryId)
            ->whereHas('transaction', fn ($q) => $q->where('type', 'expense')->forMonth($month))
            ->sum('amount');

        return (float) $direct + (float) $split;
    }

    /**
     * @return Collection<string, array{total: float, color: string}> keyed by category name
     */
    public static function byCategory(Household $household, Carbon $month): Collection
    {
        $rows = collect();

        $household->transactions()
            ->where('type', 'expense')
            ->where('is_split', false)
            ->forMonth($month)
            ->with('category')
            ->get()
            ->each(fn (Transaction $t) => $rows->push([
                'name' => $t->category->name ?? 'Uncategorized',
                'color' => $t->category->color ?? '#8A8A8A',
                'amount' => (float) $t->amount,
            ]));

        TransactionSplit::whereHas('transaction', fn ($q) => $q
            ->where('household_id', $household->id)
            ->where('type', 'expense')
            ->forMonth($month))
            ->with('category')
            ->get()
            ->each(fn (TransactionSplit $s) => $rows->push([
                'name' => $s->category->name ?? 'Uncategorized',
                'color' => $s->category->color ?? '#8A8A8A',
                'amount' => (float) $s->amount,
            ]));

        /** @var Collection<string, array{total: float, color: string}> */
        return $rows->groupBy('name')
            ->map(fn (Collection $group) => [
                'total' => (float) $group->sum('amount'),
                'color' => (string) $group->first()['color'],
            ])
            ->sortByDesc('total');
    }
}
