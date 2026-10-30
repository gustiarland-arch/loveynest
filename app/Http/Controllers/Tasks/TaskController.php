<?php

namespace App\Http\Controllers\Tasks;

use App\Enums\Scope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\Tasks\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    private const DONE_LIMIT = 100;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $space = $request->query('space') === 'ours' && $user->activeSpaceId() ? 'ours' : 'mine';
        $filter = in_array($request->query('filter'), ['active', 'done'], true) ? $request->query('filter') : 'active';
        $scope = Scope::fromSpace($space);

        $base = fn () => Task::query()->inSpaceOf($user, $scope);

        $search = trim((string) $request->query('q'));
        $priority = in_array($request->query('priority'), Task::PRIORITIES, true) ? $request->query('priority') : null;
        $category = trim((string) $request->query('category')) ?: null;
        $due = in_array($request->query('due'), ['today', 'week', 'overdue', 'none'], true) ? $request->query('due') : null;
        $who = in_array($request->query('who'), ['me', 'partner', 'both'], true) && $space === 'ours' ? $request->query('who') : null;
        $narrow = function ($q) use ($search, $priority, $category, $due, $who, $user) {
            $q->when($search !== '', fn ($w) => $w->where(fn ($s) => $s->where('title', 'like', '%'.addcslashes(mb_substr($search, 0, 60), '\\%_').'%')->orWhere('description', 'like', '%'.addcslashes(mb_substr($search, 0, 60), '\\%_').'%')))
                ->when($priority, fn ($w) => $w->where('priority', $priority))
                ->when($category, fn ($w) => $w->where('category', $category))
                ->when($due === 'today', fn ($w) => $w->whereBetween('due_at', [now($user->timezone)->startOfDay()->utc(), now($user->timezone)->endOfDay()->utc()]))
                ->when($due === 'week', fn ($w) => $w->whereBetween('due_at', [now($user->timezone)->startOfDay()->utc(), now($user->timezone)->addDays(7)->endOfDay()->utc()]))
                ->when($due === 'overdue', fn ($w) => $w->whereNotNull('due_at')->where('due_at', '<', now()))
                ->when($due === 'none', fn ($w) => $w->whereNull('due_at'))
                ->when($who === 'me', fn ($w) => $w->where('assignee_id', $user->id))
                ->when($who === 'partner', fn ($w) => $w->whereNotNull('assignee_id')->where('assignee_id', '!=', $user->id))
                ->when($who === 'both', fn ($w) => $w->whereNull('assignee_id'));
        };

        $tasks = $filter === 'done'
            ? $base()->where('status', 'done')->tap($narrow)->latest('completed_at')->limit(self::DONE_LIMIT)
            : $base()->open()->tap($narrow)->agendaOrder();

        return Inertia::render('Tasks/Index', [
            'space' => $space,
            'filter' => $filter,
            'hasCouple' => (bool) $user->activeSpaceId(),
            'tasks' => TaskResource::collection($tasks->with(['creator:id,name', 'completer:id,name', 'assignee', 'subtasks'])->get())->resolve(),
            'query' => ['q' => $search, 'priority' => $priority, 'category' => $category, 'due' => $due, 'who' => $who],
            'categories' => $base()->whereNotNull('category')->distinct()->orderBy('category')->limit(30)->pluck('category')->all(),
            'counts' => [
                'active' => $base()->open()->count(),
                'done' => $base()->where('status', 'done')->count(),
                'overdue' => $base()->open()->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            ],
        ]);
    }

    public function store(TaskRequest $request, TaskService $tasks): RedirectResponse
    {
        $task = $tasks->create($request->user(), $request->scope(), $request->taskAttributes(), $request->subtaskRows());

        return back()->with('success', $task->isShared() ? 'Tugas bersama ditambahkan.' : 'Tugas ditambahkan.');
    }

    public function update(TaskRequest $request, Task $task, TaskService $tasks): RedirectResponse
    {
        $this->authorize('update', $task);
        $tasks->update($task, $request->user(), $request->taskAttributes(), $request->subtaskRows());

        return back()->with('success', 'Tugas diperbarui.');
    }

    public function updateStatus(Request $request, Task $task, TaskService $tasks): RedirectResponse
    {
        $this->authorize('update', $task);
        $status = $request->validate(['status' => ['required', Rule::in(Task::STATUSES)]])['status'];

        $tasks->update($task, $request->user(), ['status' => $status]);

        return back()->with('success', $status === 'done' ? 'Selesai! Satu langkah lebih ringan.' : 'Tugas dibuka lagi.');
    }

    public function toggleSubtask(Request $request, Task $task, int $subtask, TaskService $tasks): RedirectResponse
    {
        $this->authorize('update', $task);
        $done = $request->validate(['done' => ['required', 'boolean']])['done'];
        $row = $task->subtasks()->whereKey($subtask)->firstOrFail();
        $row->update(['done' => $done]);

        return back();
    }

    public function destroy(Request $request, Task $task, TaskService $tasks): RedirectResponse
    {
        $this->authorize('delete', $task);
        $tasks->delete($task, $request->user());

        return back()->with('success', 'Tugas dihapus.');
    }
}
