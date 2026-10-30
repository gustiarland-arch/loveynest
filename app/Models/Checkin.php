<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    protected $fillable = ['couple_space_id', 'user_id', 'week_start', 'feeling', 'appreciation', 'improve', 'shared'];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'shared' => 'boolean'];
    }
}
