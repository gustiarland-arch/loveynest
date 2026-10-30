<?php

namespace App\Support;

/** What the PHP server will actually accept, so the browser can split big uploads instead of failing. */
class UploadLimits
{
    /** Largest single file PHP accepts, in bytes. */
    public static function perFile(): int
    {
        return self::bytes(ini_get('upload_max_filesize'));
    }

    /** Largest whole request PHP accepts, in bytes. */
    public static function perRequest(): int
    {
        return self::bytes(ini_get('post_max_size'));
    }

    /** @return array{file: int, request: int} */
    public static function forClient(): array
    {
        return ['file' => self::perFile(), 'request' => self::perRequest()];
    }

    private static function bytes(string|false $value): int
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0') {
            return PHP_INT_MAX;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
