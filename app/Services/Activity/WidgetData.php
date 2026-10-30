<?php

namespace App\Services\Activity;

use App\Enums\Scope;
use App\Models\Event;
use App\Models\JournalEntry;
use App\Models\Mood;
use App\Models\Photo;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The small facts shown by the dashboard widgets and the /widget page.
 * Everything is scoped to what this user may see: their own mood, the
 * partner's mood only while paired, and shared photos and letters of the
 * current space.
 */
class WidgetData
{
    /** @return array{mine: ?array, partner: ?array} */
    public function moods(User $user): array
    {
        $today = CarbonImmutable::now($user->timezone)->toDateString();
        $partner = $user->partner();

        return [
            'mine' => $this->present(Mood::where('user_id', $user->id)->where('mood_date', $today)->first()),
            'partner' => $partner ? Mood::where('user_id', $partner->id)->where('mood_date', $today)->first()?->forPartner() : null,
        ];
    }

    /** @return array{id: int, album_id: int}|null */
    public function latestPhoto(User $user): ?array
    {
        $spaceId = $user->activeSpaceId();
        if (! $spaceId) {
            return null;
        }
        $photo = Photo::query()
            ->whereHas('album', fn ($q) => $q->where('scope', 'shared')->where('couple_space_id', $spaceId))
            ->latest('id')->first(['id', 'album_id']);

        return $photo ? ['id' => $photo->id, 'album_id' => $photo->album_id] : null;
    }

    /** @return array{id: int, title: string, from: ?string, unread: bool}|null */
    public function latestLetter(User $user): ?array
    {
        $spaceId = $user->activeSpaceId();
        if (! $spaceId) {
            return null;
        }
        $letter = JournalEntry::query()->inSpaceOf($user, Scope::Shared)
            ->where('kind', 'letter')->with('creator:id,name')->latest('id')->first();

        return $letter ? [
            'id' => $letter->id,
            'title' => $letter->title,
            'from' => $letter->created_by === $user->id ? null : $letter->creator?->firstName(),
            'unread' => $letter->created_by !== $user->id && $letter->read_at === null,
        ] : null;
    }

    public function nextEvent(User $user): ?Event
    {
        return Event::query()->visibleTo($user)->upcoming()->with('creator:id,name')->first();
    }

    private function present(?Mood $mood): ?array
    {
        return $mood ? ['level' => $mood->level, 'note' => $mood->note] : null;
    }
}
