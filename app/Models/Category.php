<?php

namespace App\Models;

use App\Support\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['household_id', 'parent_id', 'name', 'type', 'color'];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('name');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function activityLabel(): string
    {
        return $this->name;
    }
}
