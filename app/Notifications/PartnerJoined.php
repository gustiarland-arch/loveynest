<?php

namespace App\Notifications;

class PartnerJoined extends CoupleNotification
{
    public function __construct(private string $partnerName) {}

    public function type(): string
    {
        return 'couple_update';
    }

    protected function content(): array
    {
        return [
            'title' => __('notifications.joined_title', ['name' => $this->partnerName]),
            'body' => __('notifications.joined_body'),
            'url' => '/our-space',
        ];
    }
}
