<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends notifications to the Android app through Firebase Cloud Messaging (HTTP v1). Messages carry data only;
 * the app shows them itself (and opens a locked chat message with the key stored on the phone).
 * The service account key is a file on the server (never in the repository or the .env):
 * storage/app/firebase-service-account.json. Without it, Android devices simply get nothing.
 * A push_subscriptions row's public_key holds the app's version code (the column is unused for FCM).
 */
class FcmSender
{
    /** Marks a push_subscriptions row that holds an FCM token instead of a Web Push endpoint. */
    public const ENCODING = 'fcm';

    public const PREFIX = 'fcm:';

    public static function credentialsPath(): string
    {
        return storage_path('app/firebase-service-account.json');
    }

    /** @return array{project_id: string, client_email: string, private_key: string}|null */
    public static function credentials(): ?array
    {
        $raw = @file_get_contents(self::credentialsPath());
        $json = $raw === false ? null : json_decode($raw, true);

        return is_array($json) && filled($json['project_id'] ?? null) && filled($json['client_email'] ?? null) && filled($json['private_key'] ?? null)
            ? $json
            : null;
    }

    public static function isConfigured(): bool
    {
        return self::credentials() !== null && function_exists('openssl_sign');
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions  rows with content_encoding "fcm"
     * @param  array{title: string, body?: string, url?: string, tag?: string, ct?: string, kind?: string}  $payload
     * @return array{sent: int, expired: int, failed: int, status?: int, reason?: string}
     */
    public function send(Collection $subscriptions, array $payload, int $ttl): array
    {
        $result = ['sent' => 0, 'expired' => 0, 'failed' => 0];
        $credentials = self::credentials();
        if ($subscriptions->isEmpty() || $credentials === null) {
            return $result;
        }

        $token = $this->accessToken($credentials);
        $url = 'https://fcm.googleapis.com/v1/projects/'.$credentials['project_id'].'/messages:send';

        foreach ($subscriptions as $subscription) {
            $deviceToken = substr((string) $subscription->endpoint, strlen(self::PREFIX));
            // The app (version 2 and up) builds the notification itself from data, so it can open a locked chat
            // message first. An older app cannot, so it still gets a ready-made generic notification.
            $legacy = (int) $subscription->public_key < 2;
            $response = Http::withToken($token)->timeout(10)->post($url, ['message' => [
                'token' => $deviceToken,
                ...($legacy ? ['notification' => ['title' => $payload['title'], 'body' => $payload['body'] ?? '']] : []),
                'data' => array_map('strval', array_filter([
                    'title' => $payload['title'],
                    'body' => $payload['body'] ?? '',
                    'url' => $payload['url'] ?? '/dashboard',
                    'tag' => $payload['tag'] ?? '',
                    'ct' => $legacy ? null : ($payload['ct'] ?? null),
                    'kind' => $payload['kind'] ?? 'push',
                ], fn ($value) => $value !== null)),
                'android' => array_filter([
                    'priority' => 'high',
                    'notification' => $legacy ? array_filter(['tag' => $payload['tag'] ?? null, 'channel_id' => 'loveynest', 'icon' => 'ic_stat_heart', 'color' => '#C88FA8']) : null,
                    'ttl' => max(0, $ttl).'s',
                    'collapse_key' => ($payload['tag'] ?? '') !== '' ? substr($payload['tag'], 0, 60) : null,
                ]),
            ]]);

            $status = $response->status();
            $code = (string) data_get($response->json(), 'error.details.0.errorCode', data_get($response->json(), 'error.status', ''));

            if ($response->successful()) {
                $subscription->forceFill(['last_success_at' => now(), 'failure_count' => 0])->save();
                $result['sent']++;
            } elseif ($status === 404 || $code === 'UNREGISTERED' || ($status === 400 && $code === 'INVALID_ARGUMENT')) {
                // The app was uninstalled or the token rotated: retire it, the app registers a new one.
                $subscription->forceFill(['expired_at' => now()])->save();
                $result['status'] = $status;
                $result['expired']++;
            } else {
                $result['status'] = $status;
                $result['reason'] = mb_substr($code ?: (string) $response->body(), 0, 120);
                $failures = $subscription->failure_count + 1;
                $subscription->forceFill([
                    'failure_count' => min($failures, 255),
                    'expired_at' => $failures >= config('usandus.push.max_failures') ? now() : null,
                ])->save();
                $result['failed']++;
                if ($status === 401 || $status === 403) {
                    Cache::forget('fcm.access_token');
                }
            }

            Log::info('push.delivery', ['subscription' => $subscription->id, 'host' => 'fcm', 'status' => $status, 'success' => $response->successful()]);
        }

        return $result;
    }

    /** OAuth access token from the service account (a signed JWT exchanged at Google), kept for 50 minutes. */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm.access_token', now()->addMinutes(50), function () use ($credentials) {
            $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
            $now = time();
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('fcm.sign_failed');
            }

            return Http::asForm()->timeout(10)->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$b64($signature),
            ])->throw()->json('access_token');
        });
    }
}
