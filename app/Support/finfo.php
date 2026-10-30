<?php

use App\Support\MimeSniffer;

/*
 * Stand-in for PHP's fileinfo extension on hosts that do not have it. Laravel's storage layer
 * (league/flysystem) builds a `finfo` object whenever a file is written, so without the class
 * every upload ends in "Class finfo not found". Only loaded when the real one is missing.
 */
if (! class_exists('finfo', false)) {
    if (! defined('FILEINFO_MIME_TYPE')) {
        define('FILEINFO_NONE', 0);
        define('FILEINFO_MIME_TYPE', 16);
        define('FILEINFO_MIME_ENCODING', 1024);
        define('FILEINFO_MIME', 1040);
    }

    class finfo
    {
        public function __construct(int $flags = 0, ?string $magic_database = null)
        {
        }

        public function buffer(string $string, int $flags = 0, $context = null): string|false
        {
            return MimeSniffer::sniff(substr($string, 0, 32)) ?? 'application/octet-stream';
        }

        public function file(string $filename, int $flags = 0, $context = null): string|false
        {
            if (! is_file($filename) || ! is_readable($filename)) {
                return false;
            }

            return $this->buffer((string) file_get_contents($filename, false, null, 0, 32));
        }

        public function set_flags(int $flags): bool
        {
            return true;
        }
    }
}
