<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HabitCheckin extends Model
{
    protected $fillable = ['habit_id', 'user_id', 'done_on'];

    protected function casts(): array
    {
        return ['done_on' => 'date'];
    }
}
