<?php

namespace App\Models;

use App\Support\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 * @property string $type
 * @property string $amount
 * @property string|null $description
 * @property string|null $import_batch
 * @property bool $is_split
 */
class Transaction extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'household_id', 'account_id', 'category_id', 'user_id', 'transfer_account_id',
        'type', 'amount', 'description', 'date', 'import_batch', 'is_split',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'is_split' => 'boolean',
        ];
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function transferAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'transfer_account_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<TransactionSplit, $this> */
    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<Transaction> $query */
    public function scopeForMonth(Builder $query, Carbon $month): void
    {
        $start = $month->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query->whereBetween('date', [$start, $end]);
    }

    public function activityLabel(): string
    {
        return number_format((float) $this->amount, 2).($this->description ? " ({$this->description})" : '');
    }
}
