<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['goal_id', 'user_id', 'from_status', 'to_status', 'progress'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
