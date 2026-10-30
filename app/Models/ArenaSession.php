<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One game being played by the two of you, from the invitation until someone leaves. */
class ArenaSession extends Model
{
    protected $fillable = ['couple_space_id', 'game', 'host_id', 'guest_id', 'status', 'seed', 'state', 'accepted_at', 'ended_at'];

    protected function casts(): array
    {
        return ['state' => 'array', 'accepted_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /** @return BelongsTo<User, $this> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function includes(User $user): bool
    {
        return $user->id === $this->host_id || $user->id === $this->guest_id;
    }

    public function otherId(User $user): int
    {
        return $user->id === $this->host_id ? $this->guest_id : $this->host_id;
    }
}
