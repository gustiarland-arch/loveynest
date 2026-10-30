<?php

namespace App\Http\Controllers;

use App\Enums\Scope;
use App\Http\Resources\EventResource;
use App\Http\Resources\TaskResource;
use App\Models\Event;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MySpaceController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $tasks = fn () => Task::query()->inSpaceOf($user, Scope::Personal);
        $weekStart = CarbonImmutable::now($user->timezone)->startOfWeek()->utc();

        return Inertia::render('MySpace', [
            'tasks' => TaskResource::collection($tasks()->open()->agendaOrder()->with('subtasks')->limit(5)->get())->resolve(),
            'events' => EventResource::collection(Event::query()->inSpaceOf($user, Scope::Personal)->upcoming()->limit(5)->get())->resolve(),
            'stats' => [
                'open' => $tasks()->open()->count(),
                'overdue' => $tasks()->open()->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'doneThisWeek' => $tasks()->where('status', 'done')->where('completed_at', '>=', $weekStart)->count(),
            ],
        ]);
    }
}
