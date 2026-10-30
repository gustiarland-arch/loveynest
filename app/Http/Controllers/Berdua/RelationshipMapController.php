<?php

namespace App\Http\Controllers\Berdua;

use App\Enums\Scope;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Milestone;
use App\Models\Photo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The couple's journey as a path of stops, plus the places tagged on shared photos. Only stored data. */
class RelationshipMapController extends Controller
{
    private const MAX_STOPS = 60;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $space = $user->activeSpace();
        abort_unless($space, 404);
        $today = CarbonImmutable::now($user->timezone)->startOfDay();

        $stops = collect();
        if ($space->met_date) {
            $stops->push(['kind' => 'met', 'title' => null, 'date' => $space->met_date->toDateString()]);
        }
        if ($space->anniversary_date) {
            $stops->push(['kind' => 'together', 'title' => null, 'date' => $space->anniversary_date->toDateString()]);
        }
        foreach (Milestone::where('couple_space_id', $space->id)->get() as $m) {
            $stops->push(['kind' => 'milestone', 'title' => $m->title, 'date' => $m->event_date->toDateString()]);
        }
        $dates = Event::query()->inSpaceOf($user, Scope::Shared)->where('category', 'kencan')
            ->where('starts_at', '<', now())->where('repeat', 'none')->orderByDesc('starts_at')->limit(40)->get();
        foreach ($dates as $e) {
            $stops->push(['kind' => 'date', 'title' => $e->title, 'date' => CarbonImmutable::instance($e->starts_at)->setTimezone($user->timezone)->toDateString(), 'rating' => $e->rating]);
        }

        $photos = Photo::query()
            ->whereHas('album', fn ($q) => $q->inSpaceOf($user, Scope::Shared))
            ->whereNotNull('location')->where('location', '!=', '')
            ->orderByDesc('id')->limit(300)->get(['id', 'location', 'taken_on', 'created_at', 'is_favorite']);

        $places = $photos->groupBy(fn ($p) => mb_strtolower(trim($p->location)))->map(function ($group) {
            $first = $group->sortBy('id')->first();
            $dates = $group->map(fn ($p) => ($p->taken_on ?? $p->created_at)->toDateString())->sort();

            return [
                'name' => trim($first->location),
                'count' => $group->count(),
                'photoId' => ($group->firstWhere('is_favorite', true) ?? $first)->id,
                'first' => $dates->first(),
                'last' => $dates->last(),
            ];
        })->sortByDesc('count')->values()->take(24);

        foreach ($places as $p) {
            $stops->push(['kind' => 'place', 'title' => $p['name'], 'date' => $p['first']]);
        }

        $stops = $stops->filter(fn ($s) => $s['date'] <= $today->toDateString())
            ->sortBy('date')->values();
        $trimmed = $stops->count() > self::MAX_STOPS;
        if ($trimmed) {
            $stops = $stops->slice(-self::MAX_STOPS)->values();
        }

        return Inertia::render('Berdua/RelationshipMap', [
            'stops' => $stops, 'places' => $places, 'trimmed' => $trimmed,
            'partner' => $user->partner()?->firstName(),
        ]);
    }
}
