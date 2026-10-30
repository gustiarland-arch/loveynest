<?php

namespace App\Notifications;

class ArenaInvite extends CoupleNotification
{
    public function __construct(private string $hostName, private string $gameTitle) {}

    public function type(): string
    {
        return 'couple_update';
    }

    public function topic(): ?string
    {
        return 'arena';
    }

    protected function content(): array
    {
        return [
            'title' => __('notifications.arena_title', ['name' => $this->hostName]),
            'body' => __('notifications.arena_body', ['game' => $this->gameTitle]),
            'url' => '/arena',
        ];
    }
}
