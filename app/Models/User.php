<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'timezone', 'locale', 'nickname', 'gender', 'profile_completed_at', 'birthday', 'dashboard_prefs', 'quiet_start', 'quiet_end', 'digest'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date:Y-m-d',
            'profile_completed_at' => 'datetime',
            'digest_sent_on' => 'date:Y-m-d',
            'dashboard_prefs' => 'array',
        ];
    }

    /** @return HasMany<CoupleMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(CoupleMember::class);
    }

    /** @return HasOne<CoupleMember, $this> */
    public function activeMembership(): HasOne
    {
        return $this->hasOne(CoupleMember::class)->whereNull('left_at');
    }

    public function activeSpace(): ?CoupleSpace
    {
        return $this->activeMembership?->space;
    }

    public function activeSpaceId(): ?int
    {
        return $this->activeMembership?->couple_space_id;
    }

    public function isActiveMemberOf(int $spaceId): bool
    {
        return $this->activeSpaceId() === $spaceId;
    }

    /**
     * The other active member of the user's space, if there is one.
     */
    public function partner(): ?User
    {
        return $this->activeSpace()?->activeMembers()
            ->where('users.id', '!=', $this->id)
            ->first();
    }

    /**
     * Same as the framework's relation, with millisecond timestamps so
     * notifications created in the same second stay in order.
     *
     * @return MorphMany<AppNotification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(AppNotification::class, 'notifiable')->latest();
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /** URL of the profile photo, versioned so a new upload is never served from cache. */
    /**
     * Columns to select when a query only needs a person's picture.
     *
     * @return array<int, string>
     */
    public static function pictureColumns(): array
    {
        static $columns = null;

        return $columns ??= ['users.id', 'users.name', 'users.avatar_path'];
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? route('avatars.show', $this).'?v='.substr(md5($this->avatar_path), 0, 8) : null;
    }

    public function firstName(): string
    {
        return strtok($this->name, ' ') ?: $this->name;
    }

    /** Whether push should stay silent right now, by this person's own clock. */
    public function inQuietHours(): bool
    {
        if (! $this->quiet_start || ! $this->quiet_end) {
            return false;
        }
        $now = now($this->timezone)->format('H:i');
        $start = substr($this->quiet_start, 0, 5);
        $end = substr($this->quiet_end, 0, 5);

        // A window like 22:00 to 07:00 crosses midnight.
        return $start <= $end ? ($now >= $start && $now < $end) : ($now >= $start || $now < $end);
    }
}
