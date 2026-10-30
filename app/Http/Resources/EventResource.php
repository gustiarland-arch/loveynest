<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Support\LocalDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Form values are shown in the viewer's zone; all-day events keep the
        // calendar date they were created for.
        $zone = $this->all_day ? $this->timezone : $request->user()->timezone;
        $start = LocalDateTime::fromUtc($this->starts_at, $zone, ! $this->all_day);
        $end = LocalDateTime::fromUtc($this->ends_at, $zone, ! $this->all_day);

        return [
            'id' => $this->id,
            'space' => $this->scope->space(),
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'all_day' => $this->all_day,
            'timezone' => $this->timezone,
            'start_date' => $start['date'],
            'start_time' => $start['time'],
            'end_date' => $end['date'],
            'end_time' => $end['time'],
            'reminder_minutes' => $this->reminder_minutes,
            'repeat' => $this->repeat,
            'repeat_until' => $this->repeat_until?->toDateString(),
            'category' => $this->category,
            'rating' => $this->rating,
            'rating_note' => $this->rating_note,
            'series' => $this->series ?? null,
            // Who said they will come, for shared events only. 'mine' is your own answer.
            'rsvp' => $this->isShared() ? $this->rsvpFor($request->user()->id) : null,
            'past' => $this->endsOrStartsAt()->isPast(),
            'created_by' => $this->isShared() ? $this->creator?->firstName() : null,
        ];
    }

    /** @return array{mine: ?string, other: ?string} */
    private function rsvpFor(int $userId): array
    {
        $this->resource->loadMissing('rsvps');
        $mine = $this->rsvps->firstWhere('user_id', $userId)?->status;
        $other = $this->rsvps->first(fn ($r) => $r->user_id !== $userId)?->status;

        return ['mine' => $mine, 'other' => $other];
    }
}
