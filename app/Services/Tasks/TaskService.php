<?php

namespace App\Services\Tasks;

use App\Enums\Scope;
use App\Models\Task;
use App\Models\User;
use App\Services\Activity\ActivityRecorder;
use App\Services\Reminders\ReminderScheduler;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(
        private ReminderScheduler $reminders,
        private ActivityRecorder $activity,
    ) {}

    /** @param  list<array{title: string, done: bool}>|null  $subtasks */
    public function create(User $user, Scope $scope, array $attributes, ?array $subtasks = null): Task
    {
        return DB::transaction(function () use ($user, $scope, $attributes, $subtasks) {
            $task = new Task($attributes);
            $task->assignSpace($user, $scope);
            $this->applyCompletion($task, $user);
            $task->save();
            $this->syncSubtasks($task, $subtasks);

            $this->reminders->syncTask($task);
            $this->record($task, $user, 'task.created');

            return $task;
        });
    }

    /** @param  list<array{title: string, done: bool}>|null  $subtasks */
    public function update(Task $task, User $user, array $attributes, ?array $subtasks = null): Task
    {
        return DB::transaction(function () use ($task, $user, $attributes, $subtasks) {
            $wasDone = $task->isDone();
            $task->fill($attributes);
            $this->applyCompletion($task, $user);
            $task->save();
            $this->syncSubtasks($task, $subtasks);

            $this->reminders->syncTask($task);
            if (! $wasDone && $task->isDone()) {
                $this->record($task, $user, 'task.completed');
                $this->spawnNextRepeat($task, $user);
            }

            return $task;
        });
    }

    public function delete(Task $task, User $user): void
    {
        DB::transaction(function () use ($task, $user) {
            $this->record($task, $user, 'task.deleted');
            $task->delete();
        });
    }

    /** Replaces the checklist; null leaves it as it is. */
    private function syncSubtasks(Task $task, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }
        $task->subtasks()->delete();
        foreach ($rows as $i => $row) {
            $task->subtasks()->create(['title' => $row['title'], 'done' => $row['done'], 'position' => $i]);
        }
    }

    /** Finishing a repeating task opens the next one, with the checklist reset. */
    private function spawnNextRepeat(Task $task, User $user): void
    {
        if ($task->repeat === 'none' || $task->due_at === null) {
            return;
        }
        // Done, reopened and done again: the next occurrence already exists, so do not make a second one.
        $alreadyNext = Task::query()
            ->where('scope', $task->scope)->where('owner_user_id', $task->owner_user_id)->where('couple_space_id', $task->couple_space_id)
            ->where('title', $task->title)->where('repeat', $task->repeat)
            ->where('due_at', '>', $task->due_at)->whereKeyNot($task->id)->exists();
        if ($alreadyNext) {
            return;
        }
        $next = match ($task->repeat) {
            'daily' => $task->due_at->addDay(),
            'weekly' => $task->due_at->addWeek(),
            default => $task->due_at->addMonthNoOverflow(),
        };
        // Skip occurrences that are already in the past (task finished late).
        while ($next->isPast()) {
            $next = match ($task->repeat) {
                'daily' => $next->addDay(),
                'weekly' => $next->addWeek(),
                default => $next->addMonthNoOverflow(),
            };
        }

        $copy = $task->replicate(['status', 'completed_at', 'completed_by']);
        $copy->forceFill(['status' => 'todo', 'completed_at' => null, 'completed_by' => null, 'due_at' => $next, 'scope' => $task->scope, 'owner_user_id' => $task->owner_user_id, 'couple_space_id' => $task->couple_space_id, 'created_by' => $task->created_by])->save();
        foreach ($task->subtasks as $sub) {
            $copy->subtasks()->create(['title' => $sub->title, 'done' => false, 'position' => $sub->position]);
        }
        $this->reminders->syncTask($copy);
    }

    private function applyCompletion(Task $task, User $user): void
    {
        if ($task->isDone() && $task->completed_at === null) {
            $task->completed_at = now();
            $task->completed_by = $user->id;
        } elseif (! $task->isDone()) {
            $task->completed_at = null;
            $task->completed_by = null;
        }
    }

    private function record(Task $task, User $user, string $action): void
    {
        if ($task->isShared()) {
            $this->activity->record($task->couple_space_id, $user, $action, 'task', $task->id, $task->title);
        }
    }
}
