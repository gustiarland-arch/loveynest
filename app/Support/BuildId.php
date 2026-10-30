<?php

namespace App\Support;

/** Which web build is on the server: it changes whenever a new public/build is put there. */
class BuildId
{
    public static function current(): string
    {
        static $id = null;

        $manifest = public_path('build/manifest.json');

        return $id ??= is_file($manifest) ? substr(md5_file($manifest), 0, 12) : 'dev';
    }
}
