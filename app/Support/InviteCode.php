<?php

namespace App\Support;

/**
 * Invitation codes: 8 characters from Crockford's Base32 alphabet (no I, L, O, U),
 * shown as XXXX-XXXX. Only an HMAC of the normalised code is ever stored.
 */
class InviteCode
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(int $length = 8): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * Accepts what people actually type: lowercase, spaces, dashes, and the
     * look-alikes O→0, I/L→1.
     */
    public static function normalize(string $input): string
    {
        $code = strtoupper(preg_replace('/[\s\-]+/', '', $input) ?? '');

        return strtr($code, ['O' => '0', 'I' => '1', 'L' => '1']);
    }

    public static function hash(string $input): string
    {
        return hash_hmac('sha256', self::normalize($input), (string) config('app.key'));
    }

    public static function format(string $code): string
    {
        return implode('-', str_split($code, 4));
    }
}
