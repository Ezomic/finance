<?php

namespace App\Support;

use App\Models\Household;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Flags two kinds of noteworthy spending this month: a category trending
 * well above its recent average, and individual transactions that are much
 * larger than what's typical for their category.
 */
class SpendingInsights
{
    private const CATEGORY_TREND_MONTHS = 3;
    private const OUTLIER_LOOKBACK_MONTHS = 6;
    private const CATEGORY_TREND_MULTIPLIER = 1.3;
    private const CATEGORY_TREND_MIN_DELTA = 20.0;
    private const OUTLIER_MULTIPLIER = 2.0;
    private const OUTLIER_MIN_SAMPLE = 5;
    private const MAX_INSIGHTS = 5;

    public static function generate(Household $household): Collection
    {
        return self::categoryTrends($household)
            ->merge(self::outlierTransactions($household))
            ->sortByDesc('magnitude')
            ->take(self::MAX_INSIGHTS)
            ->values();
    }

    private static function categoryTrends(Household $household): Collection
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $insights = collect();

        foreach ($household->categoriesTree('expense') as $category) {
            $current = CategorySpending::forCategory($category->id, $thisMonth);
            if ($current <= 0) {
                continue;
            }

            $priorMonths = collect(range(1, self::CATEGORY_TREND_MONTHS))
                ->map(fn ($i) => CategorySpending::forCategory($category->id, $thisMonth->copy()->subMonths($i)));

            // Require real history in each of the trailing months, not a brand-new category.
            if ($priorMonths->filter(fn ($amount) => $amount > 0)->count() < self::CATEGORY_TREND_MONTHS) {
                continue;
            }

            $average = $priorMonths->avg();
            $delta = $current - $average;

            if ($average > 0 && $current > $average * self::CATEGORY_TREND_MULTIPLIER && $delta >= self::CATEGORY_TREND_MIN_DELTA) {
                $percent = round(($delta / $average) * 100);
                $insights->push([
                    'type' => 'category_trend',
                    'message' => "{$category->name} is {$percent}% above its ".self::CATEGORY_TREND_MONTHS."-month average this month (".number_format($current, 2).' vs '.number_format($average, 2).').',
                    'magnitude' => $delta,
                ]);
            }
        }

        return $insights;
    }

    private static function outlierTransactions(Household $household): Collection
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $lookbackStart = $thisMonth->copy()->subMonths(self::OUTLIER_LOOKBACK_MONTHS);

        $thisMonthExpenses = $household->transactions()
            ->where('type', 'expense')
            ->where('is_split', false)
            ->whereNotNull('category_id')
            ->forMonth($thisMonth)
            ->with('category')
            ->get()
            ->groupBy('category_id');

        $insights = collect();

        foreach ($thisMonthExpenses as $categoryId => $group) {
            $history = $household->transactions()
                ->where('type', 'expense')
                ->where('category_id', $categoryId)
                ->where('is_split', false)
                ->whereBetween('date', [$lookbackStart, $thisMonth->copy()->subDay()])
                ->pluck('amount')
                ->map(fn ($amount) => (float) $amount);

            if ($history->count() < self::OUTLIER_MIN_SAMPLE) {
                continue;
            }

            $historyAverage = $history->avg();
            if ($historyAverage <= 0) {
                continue;
            }

            foreach ($group as $transaction) {
                $amount = (float) $transaction->amount;

                if ($amount > $historyAverage * self::OUTLIER_MULTIPLIER) {
                    $label = $transaction->description ?: $transaction->category->name;
                    $insights->push([
                        'type' => 'outlier_transaction',
                        'message' => 'A '.number_format($amount, 2)." charge for \"{$label}\" is much larger than your typical {$transaction->category->name} purchase (~".number_format($historyAverage, 2).').',
                        'magnitude' => $amount - $historyAverage,
                    ]);
                }
            }
        }

        return $insights;
    }
}
