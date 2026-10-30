<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends one payload to a set of push subscriptions and keeps their health
 * up to date. A 404/410 from the push service means the browser dropped the
 * subscription, so it is retired; other failures are counted.
 */
class WebPushSender
{
    public static function isConfigured(): bool
    {
        return filled(config('usandus.vapid.public_key')) && filled(config('usandus.vapid.private_key'));
    }

    /**
     * Why the VAPID setup cannot work, or null when it looks fine: 'duplicate' when the .env lists the keys twice,
     * 'mismatch' when the private key does not belong to the public key (the push service then refuses every push).
     */
    public static function problem(): ?string
    {
        if (! self::isConfigured()) {
            return null;
        }

        $env = @file_get_contents(base_path('.env'));
        if ($env !== false && preg_match_all('/^\s*VAPID_(PUBLIC|PRIVATE)_KEY\s*=/m', $env) > 2) {
            return 'duplicate';
        }

        return self::keysMatch((string) config('usandus.vapid.public_key'), (string) config('usandus.vapid.private_key')) === false ? 'mismatch' : null;
    }

    /** @return bool|null null when it cannot be checked on this server */
    public static function keysMatch(string $public, string $private): ?bool
    {
        if (! function_exists('openssl_pkey_get_private')) {
            return null;
        }

        $b64 = fn (string $v) => base64_decode(strtr($v, '-_', '+/').str_repeat('=', (4 - strlen($v) % 4) % 4), true);
        $d = $b64($private);
        $point = $b64($public);
        if ($d === false || strlen($d) !== 32 || $point === false || strlen($point) !== 65) {
            return false;
        }

        // Minimal ECPrivateKey (RFC 5915) for prime256v1 around the raw 32-byte secret.
        $der = hex2bin('30310201010420').$d.hex2bin('a00a06082a8648ce3d030107');
        $pem = "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64)."-----END EC PRIVATE KEY-----\n";
        $key = @openssl_pkey_get_private($pem);
        if (! $key) {
            return null;
        }
        $ec = openssl_pkey_get_details($key)['ec'] ?? null;
        if (! $ec) {
            return null;
        }

        return hash_equals("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT), $point);
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $payload
     * @return array{sent: int, expired: int, failed: int, status?: int, reason?: string}
     */
    public function send(Collection $subscriptions, array $payload, int $ttl, ?string $topic = null): array
    {
        $result = ['sent' => 0, 'expired' => 0, 'failed' => 0];
        if ($subscriptions->isEmpty() || ! self::isConfigured()) {
            return $result;
        }

        // Resolved lazily: building WebPush validates the VAPID keys.
        $webPush = app(WebPush::class);
        $byEndpoint = [];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $options = array_filter(['TTL' => $ttl, 'urgency' => 'high', 'topic' => $topic]);

        foreach ($subscriptions as $subscription) {
            $byEndpoint[$subscription->endpoint] = $subscription;
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]), $json, $options);
        }

        foreach ($webPush->flush() as $report) {
            $subscription = $byEndpoint[$report->getEndpoint()] ?? null;
            if (! $subscription) {
                continue;
            }

            if ($report->isSuccess()) {
                $subscription->forceFill(['last_success_at' => now(), 'failure_count' => 0])->save();
                $result['sent']++;
            } elseif ($report->isSubscriptionExpired() || str_contains((string) $report->getResponse()?->getBody(), 'VapidPkHashMismatch')) {
                // 404/410, or a subscription made with an older server key (it can never work again): retire it so it is re-created.
                $result['status'] = $report->getResponse()?->getStatusCode() ?? 0;
                $subscription->forceFill(['expired_at' => now()])->save();
                $result['expired']++;
            } else {
                // Kept so the test button can say why the push service refused (e.g. 403 BadJwtToken).
                $result['status'] = $report->getResponse()?->getStatusCode() ?? 0;
                $result['reason'] = mb_substr((string) ($report->getResponse() ? (string) $report->getResponse()->getBody() : $report->getReason()), 0, 120);
                $failures = $subscription->failure_count + 1;
                $subscription->forceFill([
                    'failure_count' => min($failures, 255),
                    'expired_at' => $failures >= config('usandus.push.max_failures') ? now() : null,
                ])->save();
                $result['failed']++;
            }

            // The endpoint is a secret capability URL: log only its host and our id.
            Log::info('push.delivery', [
                'subscription' => $subscription->id,
                'host' => parse_url($report->getEndpoint(), PHP_URL_HOST),
                'status' => $report->getResponse()?->getStatusCode(),
                'success' => $report->isSuccess(),
            ]);
        }

        return $result;
    }
}
