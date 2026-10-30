<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dream extends Model
{
    public const CATEGORIES = ['tempat', 'makanan', 'film', 'pengalaman', 'liburan', 'belajar', 'lainnya'];

    public const STATUSES = ['wish', 'planned', 'active', 'done', 'cancelled'];

    protected $fillable = ['title', 'note', 'category', 'status', 'priority', 'target_date', 'progress'];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'done_at' => 'datetime'];
    }
}
