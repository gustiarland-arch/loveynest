<?php

namespace App\Notifications;

use App\Support\E2E;
use Illuminate\Support\Str;

class NewChatMessage extends CoupleNotification
{
    /** @param ?string $preview Only set when the recipient allows message previews. */
    public function __construct(private string $senderName, private ?string $preview) {}

    public function type(): string
    {
        return 'chat_message';
    }

    public function topic(): string
    {
        return 'chat';
    }

    /**
     * A locked message goes out locked: the phone's service worker opens it with the couple key and shortens it there.
     * A very long one would not fit in a push message, so it is announced without its text.
     */
    private function body(): string
    {
        if ($this->preview === null) {
            return __('notifications.chat_hidden');
        }
        if (E2E::isSealed($this->preview)) {
            return strlen($this->preview) <= 1800 ? $this->preview : __('notifications.chat_hidden');
        }

        return Str::limit($this->preview, 80);
    }

    protected function sealedText(): ?string
    {
        return $this->preview !== null && E2E::isSealed($this->preview) && strlen($this->preview) <= 1800 ? $this->preview : null;
    }

    protected function content(): array
    {
        return [
            'title' => __('notifications.chat_title', ['name' => $this->senderName]),
            'body' => $this->body(),
            'url' => '/chat',
        ];
    }
}
