<?php

namespace App\Http\Controllers;

use App\Enums\Scope;
use App\Http\Resources\EventResource;
use App\Http\Resources\TaskResource;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\QuoteWriter;
use App\Services\Activity\WidgetData;
use App\Services\Couple\CoupleFacts;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, WidgetData $widgets, CoupleFacts $facts, QuoteWriter $quotes): Response
    {
        $user = $request->user();
        $space = $user->activeSpace();
        $partner = $user->partner();
        $endOfToday = CarbonImmutable::now($user->timezone)->endOfDay()->utc();

        $today = Task::query()->visibleTo($user)->open()
            ->whereNotNull('due_at')->where('due_at', '<=', $endOfToday)
            ->agendaOrder()->with(['creator:id,name', 'assignee', 'subtasks'])->limit(6)->get();

        $events = Event::query()->visibleTo($user)->upcoming()->with('creator:id,name')->limit(3)->get();

        return Inertia::render('Dashboard', [
            'couple' => $space ? [
                'name' => $space->name,
                'partner' => $partner ? ['name' => $partner->name, 'firstName' => $partner->firstName(), 'avatarUrl' => $partner->avatarUrl(), 'avatarFrame' => $partner->avatar_frame] : null,
            ] : null,
            'prefs' => $this->prefs($user->dashboard_prefs),
            'widgetKeys' => array_keys(config('usandus.dashboard_widgets')),
            'quote' => $this->quote($user, $quotes),
            'facts' => $facts->for($user),
            'nudges' => $facts->nudgesToday($user),
            'moods' => $widgets->moods($user),
            'photo' => $widgets->latestPhoto($user),
            'letter' => $widgets->latestLetter($user),
            'today' => TaskResource::collection($today)->resolve(),
            'events' => EventResource::collection($events)->resolve(),
            // Only the newest unread item; the bell leads to the rest.
            'notifications' => $user->unreadNotifications()->limit(1)->get()->map->toView(),
            'counts' => [
                'mine' => Task::query()->inSpaceOf($user, Scope::Personal)->open()->count(),
                'ours' => $space ? Task::query()->inSpaceOf($user, Scope::Shared)->open()->count() : 0,
            ],
        ]);
    }

    /** @return array{order: list<string>, hidden: list<string>} */
    private function prefs(mixed $stored): array
    {
        $stored = is_array($stored) ? $stored : [];
        $known = array_keys(config('usandus.dashboard_widgets'));
        $order = array_values(array_intersect($stored['order'] ?? [], $known));

        return ['order' => array_values(array_unique([...$order, ...array_diff($known, $order)])), 'hidden' => array_values(array_intersect($stored['hidden'] ?? [], $known))];
    }

    /** One line per day, the same for both of you. */
    private function quote(User $user, QuoteWriter $quotes): string
    {
        if ($written = $quotes->stored($user)) {
            return $written;
        }
        // Written after the page is sent, so the first open of the day never waits for the AI; the next load shows it.
        defer(fn () => $quotes->ensure($user));

        $list = trans('quotes.list', [], 'id');

        return $list[CarbonImmutable::now($user->timezone)->dayOfYear() % count($list)];
    }
}
