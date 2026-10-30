<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the open chat of the couple that something new is waiting. It carries
 * only the message id, never the text: the client fetches the message through
 * the normal authorized endpoint, so the socket never holds private content.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $spaceId, public int $messageId) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('couple.'.$this->spaceId.'.chat')];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /** @return array{id: int} */
    public function broadcastWith(): array
    {
        return ['id' => $this->messageId];
    }
}
