<?php

namespace App\Models;

use App\Support\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Account extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'household_id', 'user_id', 'name', 'type', 'currency', 'opening_balance', 'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_archived' => 'boolean',
        ];
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'transfer_account_id');
    }

    /** @return HasMany<NetWorthSnapshot, $this> */
    public function netWorthSnapshots(): HasMany
    {
        return $this->hasMany(NetWorthSnapshot::class)->orderByDesc('date');
    }

    /**
     * Current balance. Investment accounts prefer the latest manual snapshot,
     * otherwise balance is derived from opening balance plus transaction history.
     */
    public function getBalanceAttribute(): float
    {
        return $this->balanceAsOf(now());
    }

    /**
     * Balance as of a given date. Investment accounts are pure manual
     * revaluations (latest snapshot at-or-before $end, transactions ignored).
     *
     * Other account types use the latest balance checkpoint at-or-before
     * $end as a base (set via "Set current balance"/snapshot), plus only
     * the transactions dated strictly after that checkpoint — so importing
     * older statements after reconciling a balance never moves it. With no
     * checkpoint at all, it falls back to opening balance plus every
     * transaction up to $end, as before.
     */
    public function balanceAsOf(Carbon $end): float
    {
        $snapshot = $this->netWorthSnapshots()->where('date', '<=', $end)->first();

        if ($this->type === 'investment') {
            return $snapshot ? (float) $snapshot->balance : (float) $this->opening_balance;
        }

        $base = $snapshot ? (float) $snapshot->balance : (float) $this->opening_balance;
        $since = $snapshot?->date;

        $scoped = fn ($query) => $query
            ->where('date', '<=', $end)
            ->when($since, fn ($q) => $q->where('date', '>', $since));

        $income = $scoped($this->transactions()->where('type', 'income'))->sum('amount');
        $expense = $scoped($this->transactions()->where('type', 'expense'))->sum('amount');
        $transfersOut = $scoped($this->transactions()->where('type', 'transfer'))->sum('amount');
        $transfersIn = $scoped($this->incomingTransfers()->where('type', 'transfer'))->sum('amount');

        return $base + (float) $income - (float) $expense - (float) $transfersOut + (float) $transfersIn;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'checking' => 'Checking',
            'savings' => 'Savings',
            'credit' => 'Credit Card',
            'cash' => 'Cash',
            default => ucfirst($this->type),
        };
    }

    public function activityLabel(): string
    {
        return $this->name;
    }
}
