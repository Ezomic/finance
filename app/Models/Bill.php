<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_id', 'category_id', 'account_id', 'name', 'amount',
        'due_day', 'frequency', 'last_paid_on', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'last_paid_on' => 'date',
            'is_active' => 'boolean',
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

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function nextDueDate(): Carbon
    {
        $today = Carbon::today();
        $due = Carbon::create($today->year, $today->month, min($this->due_day, 28));

        if ($due->isPast() && ! $due->isToday()) {
            $due = match ($this->frequency) {
                'weekly' => $due->addWeek(),
                'yearly' => $due->addYear(),
                default => $due->addMonthNoOverflow(),
            };
        }

        return $due;
    }

    public function isPaidThisCycle(): bool
    {
        if (! $this->last_paid_on) {
            return false;
        }

        return $this->last_paid_on->greaterThanOrEqualTo($this->nextDueDate()->copy()->subMonthNoOverflow());
    }
}
