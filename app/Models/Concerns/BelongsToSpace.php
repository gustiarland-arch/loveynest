<?php

namespace App\Models\Concerns;

use App\Enums\Scope;
use App\Models\CoupleSpace;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Items that live either in one user's My Space (personal) or in a couple's
 * Our Space (shared). Every read goes through these scopes; every single-item
 * access goes through isAccessibleBy() via the item's policy.
 */
trait BelongsToSpace
{
    public function initializeBelongsToSpace(): void
    {
        $this->mergeCasts(['scope' => Scope::class]);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<CoupleSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(CoupleSpace::class, 'couple_space_id');
    }

    /**
     * Items the user may see: their own personal items plus the shared items
     * of the space they are an active member of right now.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $spaceId = $user->activeSpaceId();

        $query->where(function (Builder $q) use ($user, $spaceId) {
            $q->where(fn (Builder $p) => $p->where('scope', Scope::Personal)->where('owner_user_id', $user->id));
            if ($spaceId) {
                $q->orWhere(fn (Builder $s) => $s->where('scope', Scope::Shared)->where('couple_space_id', $spaceId));
            }
        });
    }

    /** Exactly one of the user's two spaces, never a mix. */
    public function scopeInSpaceOf(Builder $query, User $user, Scope $scope): void
    {
        if ($scope === Scope::Personal) {
            $query->where('scope', Scope::Personal)->where('owner_user_id', $user->id);

            return;
        }

        $spaceId = $user->activeSpaceId();
        $query->where('scope', Scope::Shared)->where('couple_space_id', $spaceId ?? 0);
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->scope === Scope::Personal
            ? $this->owner_user_id === $user->id
            : $this->couple_space_id !== null && $user->isActiveMemberOf($this->couple_space_id);
    }

    /**
     * Sets ownership from the signed-in user, never from request input.
     */
    public function assignSpace(User $user, Scope $scope): static
    {
        $this->scope = $scope;
        $this->created_by = $user->id;
        $this->owner_user_id = $scope === Scope::Personal ? $user->id : null;
        $this->couple_space_id = $scope === Scope::Shared ? $user->activeSpaceId() : null;

        return $this;
    }

    public function isShared(): bool
    {
        return $this->scope === Scope::Shared;
    }

    /** @return list<int> user ids who should hear about this item */
    public function audienceIds(): array
    {
        if ($this->scope === Scope::Personal) {
            return [$this->owner_user_id];
        }

        return CoupleSpace::find($this->couple_space_id)?->members()->whereNull('left_at')->pluck('user_id')->all() ?? [];
    }
}
