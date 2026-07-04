<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = ['household_id', 'user_id', 'subject_type', 'subject_id', 'action', 'summary', 'changes'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
