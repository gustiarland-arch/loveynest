<?php

namespace App\Http\Requests\Push;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Accepts the JSON of a browser PushSubscription. The endpoint must be HTTPS
 * on a known push service, and the keys must have the sizes the Web Push
 * encryption expects (65-byte P-256 point, 16-byte auth secret).
 */
class PushSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:2048', 'url:https', $this->allowedHost(...)],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:120', $this->base64UrlBytes(65)],
            'keys.auth' => ['required', 'string', 'max:40', $this->base64UrlBytes(16)],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
            'device_label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string|null> */
    public function subscriptionData(): array
    {
        return [
            'endpoint' => $this->input('endpoint'),
            'public_key' => $this->input('keys.p256dh'),
            'auth_token' => $this->input('keys.auth'),
            'content_encoding' => $this->input('contentEncoding') ?: 'aes128gcm',
            'device_label' => $this->filled('device_label') ? Str::limit(strip_tags($this->input('device_label')), 100, '') : null,
        ];
    }

    private function allowedHost(string $attribute, mixed $value, Closure $fail): void
    {
        $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
        $port = parse_url((string) $value, PHP_URL_PORT);

        foreach (config('usandus.push.allowed_hosts') as $pattern) {
            if (($port === null || $port === 443) && Str::is(strtolower(trim($pattern)), $host)) {
                return;
            }
        }
        $fail(__('push.unknown_service'));
    }

    private function base64UrlBytes(int $length): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($length) {
            $decoded = is_string($value) && preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $value)
                ? base64_decode(strtr($value, '-_', '+/'), true)
                : false;
            if ($decoded === false || strlen($decoded) !== $length || ($length === 65 && $decoded[0] !== "\x04")) {
                $fail(__('push.invalid_keys'));
            }
        };
    }
}
