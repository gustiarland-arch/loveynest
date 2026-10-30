<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Lets the partner's open chat show "typing…" without waiting for the next poll. */
class ChatTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $spaceId, public int $userId, public bool $typing) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('couple.'.$this->spaceId.'.chat')];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    /** @return array{user: int, typing: bool} */
    public function broadcastWith(): array
    {
        return ['user' => $this->userId, 'typing' => $this->typing];
    }
}
