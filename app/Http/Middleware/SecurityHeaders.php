<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Pages and JSON carry private data: never keep them in the browser's
        // history cache, so "Back" after logout cannot show the previous user's screen.
        // Profile, album and photobooth photos are private, so the browser may keep them.
        if (! $request->routeIs('avatars.show', 'photos.file', 'photobooth.file')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        // The Vite dev server injects inline scripts and a websocket, so the
        // strict policy only applies to built assets.
        if (! Vite::isRunningHot()) {
            // Live chat connects to the Reverb socket on this same host.
            $socket = config('usandus.realtime.enabled')
                ? ' ws://'.$request->getHost().':'.config('usandus.realtime.port').' wss://'.$request->getHost().':'.config('usandus.realtime.port')
                : '';
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org",
                "media-src 'self' blob:",
                "font-src 'self'",
                "connect-src 'self'".$socket,
                "worker-src 'self'",
                "manifest-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ]));
        }

        return $response;
    }
}
