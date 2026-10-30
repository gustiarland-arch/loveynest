<?php

namespace App\Support;

use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Fallback for servers where PHP's fileinfo extension is off (common on shared hosting).
 * Without any guesser, every upload fails with "Unable to guess the MIME type". This reads
 * the file's first bytes for the few formats the app accepts and says nothing about the rest.
 */
class MimeSniffer implements MimeTypeGuesserInterface
{
    public function isGuesserSupported(): bool
    {
        return true;
    }

    public function guessMimeType(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        return self::sniff((string) file_get_contents($path, false, null, 0, 32));
    }

    /** The format named by the first bytes of a file, or null when it is not one the app knows. */
    public static function sniff(string $head): ?string
    {
        return match (true) {
            str_starts_with($head, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($head, 'GIF87a'), str_starts_with($head, 'GIF89a') => 'image/gif',
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'image/webp',
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WAVE' => 'audio/wav',
            substr($head, 4, 4) === 'ftyp' && in_array(substr($head, 8, 4), ['heic', 'heix', 'mif1', 'heim', 'heis'], true) => 'image/heic',
            substr($head, 4, 4) === 'ftyp' => 'video/mp4',
            str_starts_with($head, "\x1A\x45\xDF\xA3") => 'video/webm',
            str_starts_with($head, 'OggS') => 'audio/ogg',
            str_starts_with($head, 'ID3'), str_starts_with($head, "\xFF\xFB") => 'audio/mpeg',
            str_starts_with($head, '%PDF') => 'application/pdf',
            default => null,
        };
    }
}
