<?php

namespace App\Notifications;

class TaskReminder extends CoupleNotification
{
    public function __construct(private string $title, private string $when, private bool $shared, private ?int $reminderId = null) {}

    public function type(): string
    {
        return 'task_reminder';
    }

    /** One tag per reminder: the Android app rings it itself too and shows it only once. */
    public function topic(): ?string
    {
        return $this->reminderId ? 'pengingat-'.$this->reminderId : null;
    }

    protected function content(): array
    {
        return [
            'title' => $this->title,
            'body' => __($this->shared ? 'notifications.task_body_shared' : 'notifications.task_body', ['when' => $this->when]),
            'url' => $this->shared ? '/tasks?space=ours' : '/tasks',
        ];
    }
}
