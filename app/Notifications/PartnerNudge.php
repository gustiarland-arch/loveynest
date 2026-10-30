<?php

namespace App\Notifications;

class PartnerNudge extends CoupleNotification
{
    public function __construct(private string $actorName, private string $kind) {}

    public function type(): string
    {
        return 'partner_activity';
    }

    public function topic(): ?string
    {
        return 'nudge';
    }

    protected function content(): array
    {
        return [
            'title' => __('notifications.nudge.'.$this->kind, ['name' => $this->actorName]),
            'body' => __('notifications.nudge_body'),
            'url' => '/dashboard',
        ];
    }
}
