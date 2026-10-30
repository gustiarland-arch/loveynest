<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Settings\BackgroundController;
use App\Services\Chat\ChatService;
use App\Services\Chat\Presence;
use App\Services\Push\FcmSender;
use App\Support\NativeApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        // The Android app ships its own build, so the website's asset version must not force it to reload.
        if (NativeApp::is($request)) {
            return null;
        }

        return parent::version($request);
    }

    /**
     * Props shared with every page. Only what the UI needs about the signed-in
     * user is exposed, never the whole model.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'version' => config('usandus.version'),
                // Public by design; the browser needs it to (re)subscribe to push.
                'vapidPublicKey' => config('usandus.vapid.public_key'),
                // The Android app asks for notifications only when the server can send them through FCM.
                'fcm' => NativeApp::is($request) && FcmSender::isConfigured(),
                // Public app key only; the secret never leaves the server.
                'realtime' => config('usandus.realtime.enabled') ? [
                    'key' => config('usandus.realtime.key'),
                    'port' => config('usandus.realtime.port'),
                    'scheme' => config('usandus.realtime.scheme'),
                ] : null,
                // Navigation only shows sections whose routes exist.
                'features' => [
                    'tasks' => Route::has('tasks.index'),
                    'calendar' => Route::has('calendar'),
                    'chat' => Route::has('chat'),
                    'albums' => Route::has('albums.index'),
                    'journal' => Route::has('journal.index'),
                ],
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'timezone' => $user->timezone,
                    'avatarUrl' => $user->avatarUrl(), 'avatarFrame' => $user->avatar_frame,
                    'hasSpace' => (bool) $user->activeSpaceId(),
                    'background' => BackgroundController::forUser($user),
                    'spaceTheme' => $user->activeSpace()?->theme ?? 'rose',
                ] : null,
            ],
            // End-to-end encryption: which key locks new content (the key itself stays on the devices).
            'e2e' => fn () => \App\Http\Controllers\Settings\E2eController::shared($user),
            // Unread chat count for the nav badge; refreshed on every visit, not polled.
            'badges' => fn () => $user ? [
                'chat' => ($conversation = app(ChatService::class)->conversationFor($user))
                    ? app(ChatService::class)->unreadCount($conversation, $user)
                    : 0,
                'notifications' => $user->unreadNotifications()->count(),
            ] : null,
            // For pickers such as a shared task's assignee.
            'partner' => fn () => ($partner = $user?->partner()) ? [
                'id' => $partner->id,
                'name' => $partner->name,
                'firstName' => $partner->firstName(),
                'avatarUrl' => $partner->avatarUrl(), 'avatarFrame' => $partner->avatar_frame,
            ] : null,
            // Online dot next to the partner's avatar; a hint from the cache, not a guarantee.
            'partnerOnline' => fn () => ($partner = $user?->partner()) ? app(Presence::class)->isOnline($partner->id) : false,
            // Always sent, also on partial reloads (`only: [...]`), so a message after a button press is never lost.
            'flash' => Inertia::always(fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]),
        ];
    }
}
