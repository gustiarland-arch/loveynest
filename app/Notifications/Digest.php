<?php

namespace App\Notifications;

/** A short morning summary of today's (or this week's) tasks and events. */
class Digest extends CoupleNotification
{
    public function __construct(private int $tasks, private int $events, private bool $weekly) {}

    public function type(): string
    {
        return 'task_reminder';
    }

    public function topic(): ?string
    {
        return 'digest';
    }

    protected function content(): array
    {
        return [
            'title' => __($this->weekly ? 'notifications.digest_week' : 'notifications.digest_day'),
            'body' => __('notifications.digest_body', ['tasks' => $this->tasks, 'events' => $this->events]),
            'url' => '/dashboard',
        ];
    }
}
