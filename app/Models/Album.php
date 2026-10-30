<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSpace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use BelongsToSpace;

    protected $fillable = ['title', 'description'];

    /** @return HasMany<Photo, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }
}
