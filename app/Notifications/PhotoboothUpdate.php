<?php

namespace App\Notifications;

class PhotoboothUpdate extends CoupleNotification
{
    /** @param  'turn'|'done'  $event */
    public function __construct(private string $actorName, private string $event) {}

    public function type(): string
    {
        return 'partner_activity';
    }

    public function topic(): ?string
    {
        return 'photobooth';
    }

    protected function content(): array
    {
        return [
            'title' => __('notifications.photobooth.'.$this->event, ['name' => $this->actorName]),
            'body' => __('notifications.photobooth.'.$this->event.'_body', ['name' => $this->actorName]),
            'url' => '/photobooth',
        ];
    }
}
