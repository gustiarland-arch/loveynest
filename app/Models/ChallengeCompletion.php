<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeCompletion extends Model
{
    protected $fillable = ['couple_space_id', 'user_id', 'challenge_key', 'done_on'];

    protected function casts(): array
    {
        return ['done_on' => 'date'];
    }
}
