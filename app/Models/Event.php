<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSpace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use BelongsToSpace;

    public const REMINDER_OPTIONS = [0, 10, 30, 60, 120, 1440];

    protected $fillable = ['title', 'description', 'starts_at', 'ends_at', 'all_day', 'timezone', 'reminder_minutes', 'repeat', 'repeat_until', 'category'];

    public const REPEATS = ['none', 'weekly', 'monthly', 'yearly'];

    public const CATEGORIES = ['kencan', 'keluarga', 'kerja', 'lainnya'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'reminder_minutes' => 'integer',
            'repeat_until' => 'date:Y-m-d',
        ];
    }

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /** @return HasMany<EventRsvp, $this> */
    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function endsOrStartsAt(): CarbonInterface
    {
        return $this->ends_at ?? $this->starts_at;
    }

    /** Events overlapping [from, to), both UTC. @param Builder<self> $query */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('starts_at', '<', $to)
            ->where(fn (Builder $q) => $q->where('ends_at', '>=', $from)
                ->orWhere(fn (Builder $q) => $q->whereNull('ends_at')->where('starts_at', '>=', $from)));
    }

    /** @param Builder<self> $query */
    public function scopeUpcoming(Builder $query): void
    {
        $now = now();
        $query->where(fn (Builder $q) => $q->where('starts_at', '>=', $now)->orWhere('ends_at', '>=', $now))
            ->orderBy('starts_at');
    }
}
