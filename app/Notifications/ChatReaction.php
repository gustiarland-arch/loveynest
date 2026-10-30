<?php

namespace App\Notifications;

class ChatReaction extends CoupleNotification
{
    public function __construct(private string $actorName, private string $emoji) {}

    public function type(): string
    {
        return 'chat_message';
    }

    public function topic(): string
    {
        return 'chat';
    }

    protected function content(): array
    {
        return [
            'title' => __('notifications.reaction_title', ['name' => $this->actorName, 'emoji' => $this->emoji]),
            'body' => __('notifications.chat_hidden'),
            'url' => '/chat',
        ];
    }
}
