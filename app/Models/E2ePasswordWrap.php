<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A space key locked with a key derived from its user's login password, so the password can open it on a new device. */
class E2ePasswordWrap extends Model
{
    protected $fillable = ['user_id', 'e2e_space_key_id', 'salt', 'kdf', 'iterations', 'wrapped'];

    public function spaceKey(): BelongsTo
    {
        return $this->belongsTo(E2eSpaceKey::class, 'e2e_space_key_id');
    }
}
