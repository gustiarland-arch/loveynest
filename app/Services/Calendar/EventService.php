<?php

namespace App\Services\Calendar;

use App\Enums\Scope;
use App\Models\Event;
use App\Models\User;
use App\Services\Activity\ActivityRecorder;
use App\Services\Reminders\ReminderScheduler;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function __construct(
        private ReminderScheduler $reminders,
        private ActivityRecorder $activity,
    ) {}

    public function create(User $user, Scope $scope, array $attributes): Event
    {
        return DB::transaction(function () use ($user, $scope, $attributes) {
            $event = new Event($attributes);
            $event->assignSpace($user, $scope);
            $event->save();

            $this->reminders->syncEvent($event);
            $this->record($event, $user, 'event.created');

            return $event;
        });
    }

    public function update(Event $event, User $user, array $attributes): Event
    {
        return DB::transaction(function () use ($event, $user, $attributes) {
            $event->fill($attributes)->save();

            $this->reminders->syncEvent($event);
            $this->record($event, $user, 'event.updated');

            return $event;
        });
    }

    public function delete(Event $event, User $user): void
    {
        DB::transaction(function () use ($event, $user) {
            $this->record($event, $user, 'event.deleted');
            $event->delete();
        });
    }

    private function record(Event $event, User $user, string $action): void
    {
        if ($event->isShared()) {
            $this->activity->record($event->couple_space_id, $user, $action, 'event', $event->id, $event->title);
        }
    }
}
