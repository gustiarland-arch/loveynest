<?php

namespace App\Support;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Masks secrets and private content in log context before it is written.
 * Keys are matched case-insensitively at any depth.
 */
class LogRedactor implements ProcessorInterface
{
    public const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'token', '_token',
        'remember_token', 'body', 'message_body', 'code', 'invite_code', 'endpoint',
        'auth', 'auth_token', 'p256dh', 'public_key', 'private_key', 'authorization',
        'cookie', 'x-xsrf-token', 'x-csrf-token', 'vapid_private_key', 'secret',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
