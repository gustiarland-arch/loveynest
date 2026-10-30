<?php

namespace App\Services\Reminders;

use App\Models\Event;
use App\Models\Reminder;
use App\Models\Task;
use Carbon\CarbonImmutable;

/**
 * Keeps the reminders table in step with events and tasks. Called inside the
 * same transaction as the change, so a reminder never outlives its source.
 * Pending rows are replaced; rows already sent are never touched again.
 */
class ReminderScheduler
{
    /** Morning reminder time for tasks that only have a due date. */
    private const DATE_ONLY_TASK_HOUR = 8;

    /** Lead time for high-priority tasks with a due time. */
    private const TASK_LEAD_MINUTES = 60;

    public function syncEvent(Event $event): void
    {
        $event->reminders()->where('status', 'pending')->delete();

        if ($event->reminder_minutes === null) {
            return;
        }

        $remindAt = CarbonImmutable::instance($event->starts_at)->subMinutes($event->reminder_minutes);
        $this->schedule($event->audienceIds(), $remindAt, ['event_id' => $event->id]);
    }

    /**
     * Only high-priority, open tasks with a due date get a reminder; anything
     * more would be noise.
     */
    public function syncTask(Task $task): void
    {
        $task->reminders()->where('status', 'pending')->delete();

        if ($task->isDone() || $task->priority !== 'high' || $task->due_at === null) {
            return;
        }

        $due = CarbonImmutable::instance($task->due_at);
        $remindAt = $task->due_has_time
            ? $due->subMinutes(self::TASK_LEAD_MINUTES)
            : $due->setTimezone($task->due_timezone ?? 'UTC')->setTime(self::DATE_ONLY_TASK_HOUR, 0)->utc();

        $this->schedule($task->audienceIds(), $remindAt, ['task_id' => $task->id]);
    }

    /** @param list<int> $userIds */
    private function schedule(array $userIds, CarbonImmutable $remindAt, array $source): void
    {
        if ($remindAt->isPast()) {
            return;
        }

        foreach (array_unique($userIds) as $userId) {
            // insertOrIgnore + the unique key make this idempotent.
            Reminder::query()->insertOrIgnore([[
                'user_id' => $userId,
                'event_id' => $source['event_id'] ?? null,
                'task_id' => $source['task_id'] ?? null,
                'remind_at' => $remindAt->utc()->format('Y-m-d H:i:s'),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]]);
        }
    }
}
