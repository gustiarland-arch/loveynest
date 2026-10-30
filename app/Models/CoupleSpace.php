<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'created_by'])]
class CoupleSpace extends Model
{
    public const MAX_MEMBERS = 2;

    protected function casts(): array
    {
        return ['dissolved_at' => 'datetime', 'anniversary_date' => 'date:Y-m-d', 'met_date' => 'date:Y-m-d'];
    }

    /** @return HasMany<CoupleMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CoupleMember::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function activeMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'couple_members')
            ->wherePivotNull('left_at')
            ->withPivot(['slot', 'joined_at'])
            ->orderByPivot('slot');
    }

    /** @return HasMany<CoupleInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(CoupleInvitation::class);
    }

    /** @return HasOne<ChatConversation, $this> */
    public function conversation(): HasOne
    {
        return $this->hasOne(ChatConversation::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isFull(): bool
    {
        return $this->members()->whereNull('left_at')->count() >= self::MAX_MEMBERS;
    }
}
