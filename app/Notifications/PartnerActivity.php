<?php

namespace App\Notifications;

class PartnerActivity extends CoupleNotification
{
    public function __construct(private string $actorName, private string $action, private string $subjectTitle) {}

    public function type(): string
    {
        return 'partner_activity';
    }

    protected function content(): array
    {
        return [
            'title' => __('notifications.activity.'.str_replace('.', '_', $this->action), ['name' => $this->actorName]),
            // A letter's title stays off the lock screen, like chat text.
            'body' => $this->action === 'letter.sent' ? __('notifications.chat_hidden') : $this->subjectTitle,
            'url' => match (true) {
                str_starts_with($this->action, 'event.') => '/calendar',
                str_starts_with($this->action, 'letter.') => '/journal?tab=letters',
                str_starts_with($this->action, 'photo.') => '/albums?space=ours',
                default => '/tasks?space=ours',
            },
        ];
    }
}
