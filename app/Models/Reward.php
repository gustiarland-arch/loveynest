<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    protected $fillable = ['couple_space_id', 'created_by', 'title', 'given_at'];

    protected function casts(): array
    {
        return ['given_at' => 'datetime'];
    }
}
