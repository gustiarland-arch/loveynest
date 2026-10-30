<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsGoal extends Model
{
    protected $fillable = ['title', 'kind', 'target_amount', 'deadline'];

    protected function casts(): array
    {
        return ['deadline' => 'date'];
    }
}
