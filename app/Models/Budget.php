<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = ['household_id', 'category_id', 'month', 'amount'];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function spent(): float
    {
        return (float) Transaction::where('category_id', $this->category_id)
            ->where('type', 'expense')
            ->forMonth($this->month)
            ->sum('amount');
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
}
