<?php

namespace App\Services\Couple;

use App\Models\ChatMessage;
use App\Models\DailyAnswer;
use App\Models\Milestone;
use App\Models\Mood;
use App\Models\Nudge;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Small derived facts about the pair: how many days together, the next
 * special date, and the interaction streak. Only reads the user's own space.
 */
class CoupleFacts
{
    private const STREAK_LOOKBACK_DAYS = 400;

    /** @return array{anniversary: ?string, days: ?int, streak: int, next: ?array{title: string, date: string, in: int}} */
    public function for(User $user): array
    {
        $space = $user->activeSpace();
        $partner = $user->partner();
        $today = CarbonImmutable::now($user->timezone)->startOfDay();

        $anniversary = $space?->anniversary_date;
        // Whole calendar days since the anniversary, counted in the user's own time zone (same number on every screen).
        $days = $anniversary ? max(0, (int) CarbonImmutable::parse($anniversary->toDateString(), $today->timezone)->diffInDays($today)) : null;

        return [
            'anniversary' => $anniversary?->toDateString(),
            'days' => $days,
            'streak' => $space && $partner ? $this->streak($user, $partner, $today) : 0,
            'next' => $space ? $this->nextMilestone($space->id, $today, $anniversary?->toDateString()) : null,
        ];
    }

    /** @return list<array{kind: string, count: int}> what the partner sent today */
    public function nudgesToday(User $user): array
    {
        $partner = $user->partner();
        if (! $partner) {
            return [];
        }
        $start = CarbonImmutable::now($user->timezone)->startOfDay()->utc();

        return Nudge::where('sender_id', $partner->id)->where('couple_space_id', $user->activeSpaceId())
            ->where('created_at', '>=', $start)->get()->groupBy('kind')
            ->map(fn ($rows, $kind) => ['kind' => $kind, 'count' => $rows->count()])->values()->all();
    }

    /** @return array{title: string, date: string, in: int}|null */
    public function nextMilestone(int $spaceId, CarbonImmutable $today, ?string $anniversary = null): ?array
    {
        $best = null;
        $consider = function (string $title, ?CarbonImmutable $date) use (&$best, $today) {
            if ($date && (! $best || $date->lt($best['at']))) {
                $best = ['title' => $title, 'at' => $date, 'in' => (int) $today->diffInDays($date)];
            }
        };

        foreach (Milestone::where('couple_space_id', $spaceId)->get() as $m) {
            $consider($m->title, $m->nextDate($today));
        }
        if ($anniversary) {
            $consider('Hari jadian', (new Milestone(['event_date' => $anniversary, 'repeat_yearly' => true]))->nextDate($today));
        }

        return $best ? ['title' => $best['title'], 'date' => $best['at']->toDateString(), 'in' => $best['in']] : null;
    }

    /**
     * Consecutive days (ending today, or yesterday if today is not done yet)
     * on which both of them did something: a mood, an answer, a nudge or a
     * chat message.
     */
    public function streak(User $user, User $partner, CarbonImmutable $today): int
    {
        $from = $today->subDays(self::STREAK_LOOKBACK_DAYS);
        $zone = $user->timezone;

        $days = fn (User $u) => collect()
            ->merge(Mood::where('user_id', $u->id)->where('mood_date', '>=', $from->toDateString())->pluck('mood_date'))
            ->merge(DailyAnswer::where('user_id', $u->id)->where('answer_date', '>=', $from->toDateString())->pluck('answer_date'))
            ->merge(Nudge::where('sender_id', $u->id)->where('created_at', '>=', $from->utc())->pluck('created_at'))
            ->merge(ChatMessage::where('sender_id', $u->id)->where('created_at', '>=', $from->utc())->pluck('created_at'))
            ->map(fn ($d) => CarbonImmutable::parse($d)->setTimezone($zone)->toDateString())
            ->unique()->flip();

        $mine = $days($user);
        $theirs = $days($partner);
        $both = fn (CarbonImmutable $d) => $mine->has($d->toDateString()) && $theirs->has($d->toDateString());

        $cursor = $both($today) ? $today : $today->subDay();
        $count = 0;
        while ($both($cursor)) {
            $count++;
            $cursor = $cursor->subDay();
        }

        return $count;
    }
}
