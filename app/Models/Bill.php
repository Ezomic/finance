<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    /**
     * Every due date this bill lands on within [$from, $to], walking
     * backward/forward from nextDueDate() by the bill's cadence.
     *
     * @return Collection<int, Carbon>
     */
    public function occurrencesBetween(Carbon $from, Carbon $to): Collection
    {
        $step = fn (Carbon $date, int $direction): Carbon => match ($this->frequency) {
            'weekly' => $direction > 0 ? $date->copy()->addWeek() : $date->copy()->subWeek(),
            'yearly' => $direction > 0 ? $date->copy()->addYear() : $date->copy()->subYear(),
            default => $direction > 0 ? $date->copy()->addMonthNoOverflow() : $date->copy()->subMonthNoOverflow(),
        };

        $cursor = $this->nextDueDate();

        for ($guard = 0; $cursor->greaterThan($to) && $guard < 1000; $guard++) {
            $cursor = $step($cursor, -1);
        }

        $occurrences = collect();

        for ($guard = 0; $cursor->lessThanOrEqualTo($to) && $guard < 1000; $guard++) {
            if ($cursor->greaterThanOrEqualTo($from)) {
                $occurrences->push($cursor->copy());
            }
            $cursor = $step($cursor, 1);
        }

        return $occurrences;
    }

    /**
     * @return Collection<int, Carbon>
     */
    public function occurrencesInMonth(Carbon $month): Collection
    {
        return $this->occurrencesBetween($month->copy()->startOfMonth(), $month->copy()->endOfMonth());
    }
}
