<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Our Space and chat routes require an active couple-space membership.
 */
class EnsureActiveCoupleSpace
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->activeSpaceId()) {
            if ($request->expectsJson()) {
                abort(403, 'Kamu belum tergabung dalam ruang pasangan.');
            }

            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
