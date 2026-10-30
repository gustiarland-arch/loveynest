<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $page = $request->user()->notifications()->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => collect($page->items())->map(fn (AppNotification $n) => $n->toView()),
            'nextPage' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'page' => $page->currentPage(),
        ]);
    }

    /** Unread count and the newest unread items, for the open app to notice new ones (sound, toast, badge). */
    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->unreadNotifications()->limit(5)->get()->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? $n->type,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'url' => '/notifications/'.$n->id.'/open',
            ])->values(),
        ])->header('Cache-Control', 'no-store, private');
    }

    /** Marks one of the caller's notifications read and opens its target. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        // Scoped through the relation: another user's id is a plain 404.
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        $url = $item->data['url'] ?? '/dashboard';

        return redirect(is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : '/dashboard');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
