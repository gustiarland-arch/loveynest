<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The Android app (Capacitor) carries its own copy of the screens and adds "LoveyNestApp/<n>" to the
 * WebView's user agent. Everything else about its requests (cookies, CSRF, origin) is the same as the website.
 */
class NativeApp
{
    public static function is(?Request $request = null): bool
    {
        $agent = (string) ($request ?? request())->userAgent();

        return str_contains($agent, 'LoveyNestApp/');
    }
}
