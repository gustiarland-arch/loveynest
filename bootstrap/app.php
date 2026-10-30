<?php

// Hosts without the fileinfo extension still need a `finfo` class; it must exist before any upload code runs.
require_once __DIR__.'/../app/Support/finfo.php';

use App\Http\Middleware\EnsureActiveCoupleSpace;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfInCoupleSpace;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TouchPresence;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a proxy (Cloudflare, load balancer) every visitor shares one IP unless the proxy is trusted: TRUSTED_PROXIES=* in .env.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Written by the browser (resources/js/app.js) when the page runs as an installed app, so it is not encrypted.
        $middleware->encryptCookies(except: ['ln_app']);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            SecurityHeaders::class,
            TouchPresence::class,
        ]);

        $middleware->alias([
            'couple' => EnsureActiveCoupleSpace::class,
            'no-couple' => RedirectIfInCoupleSpace::class,
            'profile.complete' => EnsureProfileComplete::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A request bigger than PHP's post_max_size never reaches a controller. Say so plainly.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $limit = \App\Support\UploadLimits::perRequest();
            $message = 'Ukuran total unggahan melebihi batas server ('.(int) round($limit / 1048576).' MB). Unggah lebih sedikit foto sekaligus.';

            if ($request->header('X-Inertia')) {
                return Inertia::location($request->headers->get('referer') ?: url('/'));
            }

            return response()->json(['message' => $message, 'errors' => ['photo' => [$message]]], 413);
        });

        // Query exceptions embed bound values (message bodies, emails) in their
        // message. Log the SQL shape only.
        $exceptions->report(function (QueryException $e) {
            Log::error('Database query failed', [
                'sql' => $e->getSql(),
                'sqlstate' => $e->errorInfo[0] ?? null,
                'driver_code' => $e->errorInfo[1] ?? null,
                'connection' => $e->getConnectionName(),
            ]);

            return false;
        });

        // Friendly Inertia error pages instead of stack traces (when debug is off).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($status === 419 && ! $request->expectsJson()) {
                return back()->with('error', 'Halaman terlalu lama terbuka. Silakan coba lagi.');
            }

            // An address that only accepts a button press (e.g. a saved or reloaded /main/kuis/ai) goes home instead of a bare 405 page.
            if ($status === 405 && $request->isMethod('GET') && ! $request->expectsJson()) {
                return redirect('/');
            }

            // A button press (POST/PATCH/DELETE) that fails stays on the same page with a short message, so the
            // address bar never ends up on an address that cannot be opened again.
            if ($request->header('X-Inertia') && ! $request->isMethod('GET') && in_array($status, [403, 404, 422, 429, 500, 503], true)) {
                $own = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? trim($e->getMessage()) : '';
                $message = match ($status) {
                    422 => $own !== '' ? $own : 'Permintaan tidak bisa diproses.',
                    429 => 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.',
                    403 => $own !== '' && $own !== 'This action is unauthorized.' ? $own : 'Kamu tidak punya akses untuk ini.',
                    404 => 'Data ini sudah tidak ada atau tidak bisa dibuka.',
                    503 => 'Server sedang dalam perawatan. Coba lagi sebentar lagi.',
                    default => 'Terjadi kesalahan di server. Coba lagi.',
                };

                return back(303)->with('error', $message);
            }

            $showFriendly = ! app()->hasDebugModeEnabled() || in_array($status, [403, 404, 429], true);

            if ($showFriendly && in_array($status, [403, 404, 429, 500, 503], true) && ! $request->expectsJson()) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
