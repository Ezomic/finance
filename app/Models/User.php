<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_household_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function households()
    {
        return $this->belongsToMany(Household::class)->withPivot('role')->withTimestamps();
    }

    public function currentHousehold()
    {
        return $this->belongsTo(Household::class, 'current_household_id');
    }

    public function ownsHousehold(Household $household): bool
    {
        return $this->households()->where('households.id', $household->id)->wherePivot('role', 'owner')->exists();
    }
}
