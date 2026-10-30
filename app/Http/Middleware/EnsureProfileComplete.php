<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A brand-new account (just email and password) fills in its profile before creating or joining a couple space.
 */
class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->profile_completed_at && $request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect()->route('profile.setup');
        }

        return $next($request);
    }
}
