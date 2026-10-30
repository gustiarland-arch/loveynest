<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A phone or browser that holds its own private key; only its public key is stored. */
class E2eDevice extends Model
{
    protected $fillable = ['user_id', 'public_key', 'label', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wraps(): HasMany
    {
        return $this->hasMany(E2eKeyWrap::class);
    }
}
