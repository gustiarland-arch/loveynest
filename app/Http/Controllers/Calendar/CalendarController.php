<?php

namespace App\Http\Controllers\Calendar;

use App\Support\E2E;
use App\Enums\Scope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\EventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Services\Calendar\EventService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $hasCouple = (bool) $user->activeSpaceId();
        $space = in_array($request->query('space'), ['mine', 'ours'], true) ? $request->query('space') : 'all';
        if ($space === 'ours' && ! $hasCouple) {
            $space = 'mine';
        }

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? CarbonImmutable::createFromFormat('Y-m-d H:i:s', $request->query('month').'-01 00:00:00', $user->timezone)
            : CarbonImmutable::now($user->timezone)->startOfMonth();
        if ($month->year < 2000 || $month->year > 2099) {
            $month = CarbonImmutable::now($user->timezone)->startOfMonth();
        }

        $scoped = function () use ($user, $space): Builder {
            $query = Event::query()->with('creator:id,name');

            return $space === 'all'
                ? $query->visibleTo($user)
                : $query->inSpaceOf($user, Scope::fromSpace($space));
        };

        // Pad by a week each side so the week view can cross a month edge.
        $from = $month->subDays(7)->utc();
        $to = $month->addMonth()->addDays(7)->utc();
        $events = $scoped()->where('repeat', 'none')->overlapping($from, $to)->orderBy('starts_at')->get();
        $recurring = $scoped()->where('repeat', '!=', 'none')->where('starts_at', '<', $to)
            ->where(fn ($q) => $q->whereNull('repeat_until')->orWhere('repeat_until', '>=', $from->toDateString()))->limit(100)->get();
        $expanded = $recurring->flatMap(fn (Event $e) => $this->occurrences($e, $from, $to));

        return Inertia::render('Calendar/Index', [
            'month' => $month->format('Y-m'),
            'space' => $space,
            'hasCouple' => $hasCouple,
            'events' => EventResource::collection($events->concat($expanded)->sortBy('starts_at')->values())->resolve(),
            'upcoming' => EventResource::collection($scoped()->upcoming()->limit(5)->get())->resolve(),
        ]);
    }

    public function store(EventRequest $request, EventService $events): RedirectResponse
    {
        $event = $events->create($request->user(), $request->scope(), $request->eventAttributes());

        return back()->with('success', $event->isShared() ? 'Acara bersama ditambahkan.' : 'Acara ditambahkan.');
    }

    public function update(EventRequest $request, Event $event, EventService $events): RedirectResponse
    {
        $this->authorize('update', $event);
        $events->update($event, $request->user(), $request->eventAttributes());

        return back()->with('success', 'Acara diperbarui.');
    }

    public function destroy(Request $request, Event $event, EventService $events): RedirectResponse
    {
        $this->authorize('delete', $event);
        $events->delete($event, $request->user());

        return back()->with('success', 'Acara dihapus.');
    }

    /** "Will you come?" on a shared event. Only the two of you can answer. */
    public function rsvp(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('view', $event);
        abort_unless($event->isShared(), 422, 'Hanya acara bersama yang bisa dijawab.');
        $data = $request->validate(['status' => ['required', 'in:yes,maybe,no']]);
        EventRsvp::updateOrCreate(['event_id' => $event->id, 'user_id' => $request->user()->id], ['status' => $data['status']]);

        return back();
    }

    /** Date and time for one rated "kencan", after it happened. */
    public function rate(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($event->isShared() && $event->category === 'kencan' && $event->repeat === 'none', 422, 'Hanya acara kencan bersama yang bisa diberi rating.');
        abort_if($event->starts_at->isFuture(), 422, 'Beri rating setelah acaranya lewat.');
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'rating_note' => ['nullable', ...E2E::text(200)]]);
        $event->forceFill(['rating' => $data['rating'], 'rating_note' => $data['rating_note'] ?? null])->save();

        return back()->with('success', 'Rating tersimpan.');
    }

    /** Past shared dates with their ratings. */
    public function history(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->activeSpaceId(), 404);
        $rows = Event::query()->inSpaceOf($user, Scope::Shared)->where('category', 'kencan')->where('starts_at', '<', now())
            ->where('repeat', 'none')->orderByDesc('starts_at')->limit(100)->get();
        $rated = $rows->whereNotNull('rating');

        return Inertia::render('Calendar/History', [
            'dates' => EventResource::collection($rows)->resolve(),
            'average' => $rated->count() ? round($rated->avg('rating'), 1) : null,
        ]);
    }

    /** @return list<Event> Copies of a repeating event, one per occurrence that touches [from, to). */
    private function occurrences(Event $event, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $zone = $event->timezone;
        $start = CarbonImmutable::instance($event->starts_at)->setTimezone($zone);
        $end = $event->ends_at ? CarbonImmutable::instance($event->ends_at)->setTimezone($zone) : null;
        $until = $event->repeat_until ? CarbonImmutable::parse($event->repeat_until->toDateString(), $zone)->endOfDay() : null;
        $series = collect((new EventResource($event))->resolve(request()))->only(['start_date', 'start_time', 'end_date', 'end_time'])->all();

        $out = [];
        for ($i = 0; $i < 400; $i++) {
            $s = match ($event->repeat) {
                'weekly' => $start->addWeeks($i),
                'monthly' => $start->addMonthsNoOverflow($i),
                default => $start->addYearsNoOverflow($i),
            };
            if ($s->utc()->gte($to) || ($until && $s->gt($until))) {
                break;
            }
            $e = $end ? $end->addSeconds($start->diffInSeconds($s, false)) : null;
            if (($e ?? $s)->utc()->lt($from)) {
                continue;
            }
            $copy = clone $event;
            $copy->starts_at = $s->utc();
            $copy->ends_at = $e?->utc();
            $copy->setAttribute('series', $series);
            $out[] = $copy;
        }

        return $out;
    }
}
