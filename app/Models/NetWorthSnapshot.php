<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetWorthSnapshot extends Model
{
    use HasFactory;

    protected $fillable = ['household_id', 'account_id', 'date', 'balance'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'balance' => 'decimal:2',
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
}
