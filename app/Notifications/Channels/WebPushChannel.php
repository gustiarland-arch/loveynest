<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\CoupleNotification;
use App\Services\Push\FcmSender;
use App\Services\Push\WebPushSender;
use Illuminate\Support\Facades\Log;

class WebPushChannel
{
    public function __construct(private WebPushSender $sender, private FcmSender $fcm) {}

    public function send(User $notifiable, CoupleNotification $notification): void
    {
        if ($notifiable->inQuietHours()) {
            return;
        }

        // Browsers get Web Push; the Android app gets the same payload through FCM.
        [$android, $subscriptions] = $notifiable->pushSubscriptions()->active()->get()->partition(fn ($s) => $s->content_encoding === FcmSender::ENCODING);
        $payload = $notification->toWebPush($notifiable);
        $appPayload = $notification->toAppPush($notifiable);
        $ttl = config('usandus.push.ttl.'.$notification->type(), config('usandus.push.ttl.default'));
        $topic = $notification->topic();

        // Sent right after the response, not through the queue: hosting without a queue worker or cron
        // would otherwise hold every push back (or never send it). A failure here must not break the request.
        defer(function () use ($subscriptions, $android, $payload, $appPayload, $ttl, $topic) {
            try {
                $this->sender->send($subscriptions->values(), $payload, $ttl, $topic);
            } catch (\Throwable $e) {
                Log::warning('push.send_failed', ['error' => $e::class]);
            }
            try {
                $this->fcm->send($android->values(), $appPayload, $ttl);
            } catch (\Throwable $e) {
                Log::warning('push.fcm_failed', ['error' => $e::class]);
            }
        });
    }
}
