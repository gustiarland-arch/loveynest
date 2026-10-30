<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArenaScore extends Model
{
    protected $fillable = ['couple_space_id', 'user_id', 'game', 'score', 'stars', 'won', 'arena_session_id'];

    protected function casts(): array
    {
        return ['won' => 'boolean'];
    }
}
