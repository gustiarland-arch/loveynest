<?php

namespace App\Providers;

use App\Support\MimeSniffer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Minishlink\WebPush\WebPush;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WebPush::class, fn () => new WebPush(
            ['VAPID' => [
                'subject' => self::vapidSubject(),
                'publicKey' => config('usandus.vapid.public_key'),
                'privateKey' => config('usandus.vapid.private_key'),
            ]],
            ['TTL' => config('usandus.push.ttl.default')],
            // Library warnings (e.g. missing GMP) go to the log instead of failing the request.
            logger: Log::channel(),
        ));
    }

    /**
     * Apple's push service rejects a VAPID subject on a made-up domain (".local", "localhost", "example")
     * with BadJwtToken. On an https site the site's own address is a valid subject, so use that instead.
     */
    private static function vapidSubject(): string
    {
        $subject = (string) config('usandus.vapid.subject');
        $url = (string) config('app.url');
        if (preg_match('/\.local\b|localhost|example\.(com|org|net)|^\s*$/i', $subject) && str_starts_with($url, 'https://')) {
            return rtrim($url, '/');
        }

        return $subject;
    }

    public function boot(): void
    {
        // Uploads still work when the host has no fileinfo extension.
        if (! function_exists('finfo_open')) {
            \Symfony\Component\Mime\MimeTypes::getDefault()->registerGuesser(new MimeSniffer);
        }

        Model::preventLazyLoading(! $this->app->isProduction());

        // Generous enough for a few typos from one household, tight enough to stop bulk sign-ups.
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perHour(40)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('couple-join', fn (Request $request) => [
            Limit::perMinute(5)->by('user:'.$request->user()?->id),
            Limit::perHour(20)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('chat-send', fn (Request $request) => Limit::perMinute(30)->by('user:'.$request->user()?->id));
        // Polling every 4s is 15/min; the ceiling leaves room for history and read markers.
        RateLimiter::for('chat-typing', fn (Request $request) => Limit::perMinute(40)->by('user:'.$request->user()?->id));
        RateLimiter::for('chat-poll', fn (Request $request) => Limit::perMinute(60)->by('user:'.$request->user()?->id));

        RateLimiter::for('push', fn (Request $request) => Limit::perHour(20)->by('user:'.$request->user()?->id));
        RateLimiter::for('push-test', fn (Request $request) => Limit::perMinute(3)->by('user:'.$request->user()?->id));

        RateLimiter::for('invitations', fn (Request $request) => Limit::perHour(10)->by('user:'.$request->user()?->id));
    }
}
