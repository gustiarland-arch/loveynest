<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * End-to-end encryption helpers for the server side.
 *
 * The browser locks text as `e2e1:<key id>:<base64url(iv + ciphertext)>` and files as
 * "LNE1" + key id + iv + ciphertext before anything is sent. The server never sees a key:
 * it only checks that what arrives is locked, stores it as is and hands it back.
 */
class E2E
{
    /** One locked text token. Plain text never matches, so it can be told apart from old rows. */
    public const TOKEN = '/^e2e1:[A-Za-z0-9]{8}:[A-Za-z0-9_-]{38,}$/';

    /** First bytes of a locked file. */
    public const FILE_MAGIC = 'LNE1';

    public static function enabled(): bool
    {
        return (bool) config('usandus.e2e.enabled');
    }

    public static function isSealed(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, 'e2e1:');
    }

    /**
     * Validation for a text field. With E2E on, the field must be one locked token; its length
     * limit covers the longest locked form of `$max` characters (4 bytes each, base64, iv, tag).
     * The plain-text limit itself is checked by the browser before it locks the text.
     *
     * @return list<mixed>
     */
    public static function text(int $max): array
    {
        if (! self::enabled()) {
            return ['string', 'max:'.$max];
        }

        return ['string', 'max:'.self::sealedLength($max), self::tokenRule()];
    }

    public static function sealedLength(int $max): int
    {
        return 16 + (int) ceil(($max * 4 + 28) / 3) * 4;
    }

    public static function tokenRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value) || ! preg_match(self::TOKEN, $value)) {
                $fail('Isi belum terkunci. Muat ulang halaman lalu coba lagi.');
            }
        };
    }

    /** Validation for an uploaded locked file. */
    public static function fileRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! $value instanceof UploadedFile || ! self::isSealedFile($value)) {
                $fail('Berkas belum terkunci. Muat ulang halaman lalu coba lagi.');
            }
        };
    }

    public static function isSealedFile(UploadedFile $file): bool
    {
        $path = $file->getRealPath();

        return $path !== false && is_readable($path) && file_get_contents($path, false, null, 0, 4) === self::FILE_MAGIC;
    }

    /** Str::limit that leaves a locked token whole; the browser shortens it after unlocking. */
    public static function limit(?string $value, int $limit): string
    {
        $value = (string) $value;

        return self::isSealed($value) ? $value : \Illuminate\Support\Str::limit($value, $limit);
    }

    /** Headers for serving a stored file: locked files go out as opaque bytes for the service worker to unlock. */
    public static function fileHeaders(string $path, string $plainType, string $cache): array
    {
        if (str_ends_with($path, '.lne')) {
            return ['Content-Type' => 'application/octet-stream', 'X-E2E' => '1', 'Cache-Control' => $cache, 'X-Content-Type-Options' => 'nosniff'];
        }

        return ['Content-Type' => $plainType, 'Cache-Control' => $cache];
    }
}
