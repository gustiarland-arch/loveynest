<?php

namespace App\Notifications;

/** Told to everyone once when a new version of LoveyNest has been put on the server. */
class AppUpdated extends CoupleNotification
{
    public function type(): string
    {
        return 'couple_update';
    }

    public function topic(): ?string
    {
        return 'app-update';
    }

    protected function content(): array
    {
        return [
            'title' => __('notifications.app_updated_title'),
            'body' => __('notifications.app_updated_body'),
            'url' => '/dashboard',
        ];
    }
}
