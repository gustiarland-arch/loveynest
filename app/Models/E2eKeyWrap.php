<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A space key locked for one device; only that device's private key can open it. */
class E2eKeyWrap extends Model
{
    protected $fillable = ['e2e_space_key_id', 'e2e_device_id', 'wrapped', 'wrapped_by'];

    public function spaceKey(): BelongsTo
    {
        return $this->belongsTo(E2eSpaceKey::class, 'e2e_space_key_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(E2eDevice::class, 'e2e_device_id');
    }
}
