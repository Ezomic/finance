<?php

namespace App\Models;

use App\Support\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function transferAccount()
    {
        return $this->belongsTo(Account::class, 'transfer_account_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function splits()
    {
        return $this->hasMany(TransactionSplit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForMonth($query, $month)
    {
        $start = \Illuminate\Support\Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $query->whereBetween('date', [$start, $end]);
    }

    public function activityLabel(): string
    {
        return number_format((float) $this->amount, 2).($this->description ? " ({$this->description})" : '');
    }
}
