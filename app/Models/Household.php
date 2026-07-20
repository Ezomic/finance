<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Household extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'invite_code', 'currency'];

    protected static function booted(): void
    {
        static::creating(function (Household $household) {
            $household->invite_code ??= strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
        });
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Flat list of categories ordered so each top-level category is
     * immediately followed by its own subcategories — convenient for
     * rendering nested lists and indented <select> options alike.
     *
     * @return Collection<int, Category>
     */
    public function categoriesTree(?string $type = null): Collection
    {
        $query = $this->categories()->orderBy('type')->orderBy('name');

        if ($type) {
            $query->where('type', $type);
        }

        $flat = $query->get();
        $topLevel = $flat->whereNull('parent_id');
        $byParent = $flat->whereNotNull('parent_id')->groupBy('parent_id');

        return $topLevel
            ->flatMap(fn ($category) => collect([$category])->merge($byParent->get($category->id, collect())))
            ->values();
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Budget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /** @return HasMany<NetWorthSnapshot, $this> */
    public function netWorthSnapshots(): HasMany
    {
        return $this->hasMany(NetWorthSnapshot::class);
    }

    /** @return HasMany<ActivityLog, $this> */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function netWorth(): float
    {
        return $this->accounts()->where('is_archived', false)->get()->sum->balance;
    }
}
