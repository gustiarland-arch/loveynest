<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Push\WebPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

/**
 * Base for every LoveyNest notification. Content is computed when the
 * notification is created (plain strings, no models), so a queued push still
 * makes sense if the task or event is deleted before the worker runs.
 *
 * The in-app copy is written immediately and the push is sent right after the response,
 * and only after the surrounding transaction has committed. No queue worker is needed.
 */
abstract class CoupleNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /** One attempt only: a retry would re-send to devices that already got it. */
    public int $tries = 1;

    abstract public function type(): string;

    /** @return array{title: string, body: string, url: string} */
    abstract protected function content(): array;

    /** Push collapse key; a newer push with the same topic replaces the older one. */
    public function topic(): ?string
    {
        return null;
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        $preference = app(NotificationPreferences::class)->for($notifiable, $this->type());
        $channels = [];

        if ($preference['in_app']) {
            $channels[] = 'database';
        }
        if ($preference['push'] && WebPushSender::isConfigured() && $notifiable->pushSubscriptions()->active()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /** @return array<string, string> */
    public function viaConnections(): array
    {
        // Both channels run in the request (push is deferred until after the response, see WebPushChannel).
        return ['database' => 'sync', WebPushChannel::class => 'sync'];
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return ['type' => $this->type(), ...$this->content()];
    }

    /** @return array{title: string, body: string, url: string, tag: string} */
    public function toWebPush(User $notifiable): array
    {
        return [...$this->plainForPush($this->content()), 'tag' => $this->topic() ?? $this->type()];
    }

    /**
     * Payload for the Android app. Same as the web push, plus `ct`: the locked text, which the phone opens with
     * the couple key it holds (the server and Firebase only ever carry the locked form).
     *
     * @return array{title: string, body: string, url: string, tag: string, ct?: string}
     */
    public function toAppPush(User $notifiable): array
    {
        $sealed = $this->sealedText();

        return [...$this->toWebPush($notifiable), ...($sealed !== null ? ['ct' => $sealed] : [])];
    }

    /** The locked text the app may open to show in the notification, or null. */
    protected function sealedText(): ?string
    {
        return null;
    }

    /**
     * A push goes out through the push service and shows on the lock screen, so it never carries a locked
     * token (`e2e1:...`): a phone that cannot open it would show the raw code. Locked text becomes a generic line.
     *
     * @param  array{title: string, body: string, url: string}  $content
     * @return array{title: string, body: string, url: string}
     */
    private function plainForPush(array $content): array
    {
        foreach (['title', 'body'] as $field) {
            if (str_contains($content[$field], 'e2e1:')) {
                $clean = trim(preg_replace('/e2e1:[A-Za-z0-9]{8}:[A-Za-z0-9_-]+/', '', $content[$field]));
                $content[$field] = $clean !== '' && $field === 'title' ? $clean : __('notifications.chat_hidden');
            }
        }

        return $content;
    }

    public function databaseType(User $notifiable): string
    {
        return $this->type();
    }
}
