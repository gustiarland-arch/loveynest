<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaylistItem extends Model
{
    protected $fillable = ['title', 'artist', 'url', 'note', 'is_ours'];

    protected function casts(): array
    {
        return ['is_ours' => 'boolean'];
    }
}
