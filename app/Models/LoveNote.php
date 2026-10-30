<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoveNote extends Model
{
    protected $fillable = ['couple_space_id', 'sender_id', 'body', 'deliver_at'];

    protected function casts(): array
    {
        return ['deliver_at' => 'datetime', 'delivered_at' => 'datetime'];
    }
}
