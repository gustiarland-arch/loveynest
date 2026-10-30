<?php

namespace App\Services\Reminders;

use App\Models\Reminder;
use App\Notifications\EventReminder;
use App\Notifications\TaskReminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends due reminders exactly once, even if two dispatchers run at the same
 * time: each row is claimed with a conditional UPDATE, and only the process
 * whose UPDATE changed the row sends it.
 */
class ReminderDispatcher
{
    public const HEARTBEAT_KEY = 'usandus:scheduler:last_run';

    /** @return array{sent: int, skipped: int, failed: int, recovered: int} */
    public function dispatch(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        Cache::forever(self::HEARTBEAT_KEY, $now->toIso8601String());
        $stats = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'recovered' => $this->recoverStuck($now)];

        // After a long outage, old reminders are no longer useful.
        $stats['skipped'] += Reminder::where('status', 'pending')
            ->where('remind_at', '<', $now->subMinutes(config('usandus.reminders.stale_after_minutes')))
            ->update(['status' => 'skipped', 'updated_at' => $now]);

        $due = Reminder::where('status', 'pending')
            ->where('remind_at', '<=', $now)
            ->orderBy('remind_at')
            ->limit(config('usandus.reminders.batch'))
            ->pluck('id');

        foreach ($due as $id) {
            if (! $this->claim($id, $now)) {
                continue; // another dispatcher got it first
            }
            $outcome = $this->deliver(Reminder::with(['user', 'event', 'task'])->find($id), $now);
            $stats[$outcome]++;
        }

        return $stats;
    }

    public static function lastRun(): ?CarbonImmutable
    {
        $value = Cache::get(self::HEARTBEAT_KEY);

        return $value ? CarbonImmutable::parse($value) : null;
    }

    private function claim(int $id, CarbonImmutable $now): bool
    {
        return DB::table('reminders')
            ->where('id', $id)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'claimed_at' => $now,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => $now,
            ]) === 1;
    }

    /** @return 'sent'|'skipped'|'failed' */
    private function deliver(Reminder $reminder, CarbonImmutable $now): string
    {
        $source = $reminder->event ?? $reminder->task;
        $user = $reminder->user;

        // The item may have changed hands since the reminder was planned
        // (for example the user left the space): never notify someone who
        // can no longer see it, and never remind about a finished task.
        if (! $source || ! $user || ! $source->isAccessibleBy($user) || ($reminder->task && $reminder->task->isDone())) {
            $reminder->forceFill(['status' => 'skipped'])->save();

            return 'skipped';
        }

        try {
            $user->notify($this->notificationFor($reminder, $user->timezone));
            $reminder->forceFill(['status' => 'sent', 'sent_at' => $now])->save();

            return 'sent';
        } catch (Throwable $e) {
            $final = $reminder->attempts >= config('usandus.reminders.max_attempts');
            $reminder->forceFill(['status' => $final ? 'failed' : 'pending'])->save();
            Log::warning('reminder.failed', ['reminder' => $reminder->id, 'error' => $e::class]);

            return 'failed';
        }
    }

    public function notificationFor(Reminder $reminder, string $timezone): EventReminder|TaskReminder
    {
        if ($event = $reminder->event) {
            $start = CarbonImmutable::instance($event->starts_at)->setTimezone($event->all_day ? $event->timezone : $timezone)->locale('id');
            $when = $event->all_day ? $start->translatedFormat('l, j F') : $start->translatedFormat('l, j F · H.i');

            return new EventReminder($event->title, $when, $event->couple_space_id !== null, $reminder->id);
        }

        $task = $reminder->task;
        $due = CarbonImmutable::instance($task->due_at)->setTimezone($task->due_has_time ? $timezone : ($task->due_timezone ?? $timezone))->locale('id');
        $when = $task->due_has_time ? $due->translatedFormat('l, j F · H.i') : $due->translatedFormat('l, j F');

        return new TaskReminder($task->title, $when, $task->couple_space_id !== null, $reminder->id);
    }

    /** A dispatcher that died mid-send leaves rows in "processing"; put them back. */
    private function recoverStuck(CarbonImmutable $now): int
    {
        $stuck = Reminder::where('status', 'processing')
            ->where('claimed_at', '<', $now->subMinutes(config('usandus.reminders.stuck_after_minutes')));

        $failed = (clone $stuck)->where('attempts', '>=', config('usandus.reminders.max_attempts'))
            ->update(['status' => 'failed', 'updated_at' => $now]);

        return $failed + $stuck->update(['status' => 'pending', 'updated_at' => $now]);
    }
}
