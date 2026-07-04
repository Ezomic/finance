<?php

namespace App\Models;

use App\Support\CategorySpending;
use App\Support\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $month
 */
class Budget extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['household_id', 'category_id', 'month', 'amount'];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function spent(): float
    {
        return CategorySpending::forCategory($this->category_id, $this->month);
    }

    public function remaining(): float
    {
        return (float) $this->amount - $this->spent();
    }

    public function percentUsed(): float
    {
        if ((float) $this->amount <= 0) {
            return 0;
        }

        return min(100, round(($this->spent() / (float) $this->amount) * 100, 1));
    }

    public function activityLabel(): string
    {
        $category = $this->category->name ?? 'category';

        return "{$category} — {$this->month->format('M Y')}";
    }
}
