<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Creating or joining a space only makes sense for users without one.
 */
class RedirectIfInCoupleSpace
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->activeSpaceId()) {
            return redirect()->route('couple.invite');
        }

        return $next($request);
    }
}
