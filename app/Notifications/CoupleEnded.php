<?php

namespace App\Notifications;

class CoupleEnded extends CoupleNotification
{
    public function __construct(private string $name) {}

    public function type(): string
    {
        return 'couple_update';
    }

    protected function content(): array
    {
        return [
            'title' => __('notifications.ended_title', ['name' => $this->name]),
            'body' => __('notifications.ended_body'),
            'url' => '/onboarding',
        ];
    }
}
