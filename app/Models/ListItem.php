<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSpace;
use Illuminate\Database\Eloquent\Model;

class ListItem extends Model
{
    use BelongsToSpace;

    public const KINDS = ['wishlist', 'bucket'];

    protected $fillable = ['title', 'note', 'price'];

    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }
}
