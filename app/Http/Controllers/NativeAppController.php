<?php

namespace App\Http\Controllers;

use App\Models\Mood;
use App\Models\Reminder;
use App\Services\Couple\CoupleFacts;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Reminders\ReminderDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** What the Android app needs beyond the screens: reminders it rings itself and the home screen widget. */
class NativeAppController extends Controller
{
    /** How far ahead the phone schedules reminders; it asks again on every start, resume and change. */
    private const DAYS_AHEAD = 14;

    private const MAX_ITEMS = 60;

    public function settings(): Response
    {
        return Inertia::render('Pengaturan/Aplikasi');
    }

    /**
     * The person's pending reminders, built like the push the server would send, so the phone can ring them
     * even offline. Same tag as the push (`pengingat-{id}`), so the app shows each one only once.
     */
    public function reminders(Request $request, ReminderDispatcher $dispatcher, NotificationPreferences $preferences): JsonResponse
    {
        $user = $request->user();
        $now = CarbonImmutable::now();
        $wanted = [
            'event' => $preferences->for($user, 'event_reminder')['push'],
            'task' => $preferences->for($user, 'task_reminder')['push'],
        ];

        $items = Reminder::query()
            ->where('user_id', $user->id)->where('status', 'pending')
            ->whereBetween('remind_at', [$now->subMinutes(5), $now->addDays(self::DAYS_AHEAD)])
            ->orderBy('remind_at')->limit(self::MAX_ITEMS)
            ->with(['event', 'task'])->get()
            ->filter(function (Reminder $reminder) use ($user, $wanted) {
                $source = $reminder->event ?? $reminder->task;
                if (! $source || ! $source->isAccessibleBy($user) || ($reminder->task && $reminder->task->isDone())) {
                    return false;
                }

                return ($reminder->event ? $wanted['event'] : $wanted['task']) && ! $this->quietAt($user, CarbonImmutable::instance($reminder->remind_at));
            })
            ->map(function (Reminder $reminder) use ($user, $dispatcher) {
                $push = $dispatcher->notificationFor($reminder, $user->timezone)->toWebPush($user);

                return [
                    'id' => $reminder->id,
                    'at' => CarbonImmutable::instance($reminder->remind_at)->getTimestampMs(),
                    'title' => $push['title'],
                    // The real title, which may be locked (e2e1:...); the app opens it with the couple key.
                    'sealedTitle' => ($reminder->event ?? $reminder->task)->title,
                    'body' => $push['body'],
                    'url' => $push['url'],
                    'tag' => $push['tag'],
                ];
            })
            ->values();

        return response()->json(['items' => $items, 'days' => self::DAYS_AHEAD]);
    }

    /** Small, private-safe summary for the home screen widget: no chat, no notes. */
    public function widget(Request $request, CoupleFacts $facts): JsonResponse
    {
        $user = $request->user();
        $partner = $user->partner();
        $today = CarbonImmutable::now($user->timezone)->toDateString();
        $mood = $partner ? Mood::where('user_id', $partner->id)->where('mood_date', $today)->first()?->forPartner() : null;
        $info = $facts->for($user);

        return response()->json([
            'partner' => $partner?->firstName(),
            'anniversary' => $info['anniversary'],
            'days' => $info['days'],
            'timezone' => $user->timezone,
            'mood' => $mood ? ['level' => $mood['level'], 'company' => (bool) $mood['company']] : null,
        ]);
    }

    /** Push is held during quiet hours (see WebPushChannel), so the phone does not ring then either. */
    private function quietAt(\App\Models\User $user, CarbonImmutable $at): bool
    {
        if (! $user->quiet_start || ! $user->quiet_end) {
            return false;
        }
        $time = $at->setTimezone($user->timezone)->format('H:i');
        $start = substr($user->quiet_start, 0, 5);
        $end = substr($user->quiet_end, 0, 5);

        return $start <= $end ? ($time >= $start && $time < $end) : ($time >= $start || $time < $end);
    }
}
