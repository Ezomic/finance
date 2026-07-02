<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Flat list of categories ordered so each top-level category is
     * immediately followed by its own subcategories — convenient for
     * rendering nested lists and indented <select> options alike.
     */
    public function categoriesTree(?string $type = null)
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

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function netWorthSnapshots()
    {
        return $this->hasMany(NetWorthSnapshot::class);
    }

    public function netWorth(): float
    {
        return $this->accounts()->where('is_archived', false)->get()->sum->balance;
    }
}
