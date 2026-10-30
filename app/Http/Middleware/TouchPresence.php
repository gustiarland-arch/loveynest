<?php

namespace App\Http\Middleware;

use App\Services\Chat\Presence;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Marks the signed-in user as recently active, for the partner's online dot. */
class TouchPresence
{
    public function __construct(private Presence $presence) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($user = $request->user()) {
            try {
                $this->presence->touch($user->id);
            } catch (\Throwable) {
                // A cache hiccup must never break a page.
            }
        }

        return $response;
    }
}
