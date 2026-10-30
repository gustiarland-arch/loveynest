<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reflection extends Model
{
    protected $fillable = ['couple_space_id', 'user_id', 'month_start', 'went_well', 'to_improve', 'next_month', 'shared'];

    protected function casts(): array
    {
        return ['month_start' => 'date', 'shared' => 'boolean'];
    }
}
