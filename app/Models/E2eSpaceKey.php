<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A couple space's content key, known here only by its id. The newest one locks new content. */
class E2eSpaceKey extends Model
{
    protected $fillable = ['couple_space_id', 'key_id', 'created_by'];

    public function wraps(): HasMany
    {
        return $this->hasMany(E2eKeyWrap::class);
    }
}
