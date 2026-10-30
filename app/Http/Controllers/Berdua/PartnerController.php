<?php

namespace App\Http\Controllers\Berdua;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A small page about your partner: what they like to be called, birthdays and key dates. */
class PartnerController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $space = $user->activeSpace();
        abort_unless($space, 404);
        $partner = $user->partner();
        $today = CarbonImmutable::now($user->timezone)->startOfDay();

        $birthday = fn ($u) => $u?->birthday ? (function () use ($u, $today) {
            $next = CarbonImmutable::create($today->year, $u->birthday->month, $u->birthday->day, 0, 0, 0, $today->timezone);
            if ($next->lt($today)) {
                $next = $next->addYear();
            }

            return ['date' => $u->birthday->toDateString(), 'in' => (int) $today->diffInDays($next), 'turns' => $next->year - $u->birthday->year];
        })() : null;
        // Whole calendar days in the user's time zone, the same count as the day counter on Beranda.
        $since = fn ($date) => $date ? max(0, (int) CarbonImmutable::parse($date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date, $user->timezone)->startOfDay()->diffInDays($today)) : null;

        return Inertia::render('Berdua/Partner', [
            'partner' => $partner ? ['name' => $partner->name, 'nickname' => $partner->nickname, 'avatarUrl' => $partner->avatarUrl(), 'avatarFrame' => $partner->avatar_frame, 'birthday' => $birthday($partner)] : null,
            'me' => ['nickname' => $user->nickname, 'birthday' => $birthday($user)],
            'daysSinceMet' => $since($space->met_date),
            'daysTogether' => $since($space->anniversary_date),
            'metDate' => $space->met_date?->toDateString(),
            'anniversary' => $space->anniversary_date?->toDateString(),
            'dates' => Milestone::where('couple_space_id', $space->id)->get()->map(function (Milestone $m) use ($today) {
                $next = $m->nextDate($today);

                return ['id' => $m->id, 'title' => $m->title, 'date' => $m->event_date->toDateString(), 'in' => $next ? (int) $today->diffInDays($next) : null];
            })->sortBy(fn ($m) => $m['in'] ?? PHP_INT_MAX)->take(8)->values(),
        ]);
    }
}
