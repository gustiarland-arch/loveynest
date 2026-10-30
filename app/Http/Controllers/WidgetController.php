<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Services\Activity\WidgetData;
use App\Services\Chat\ChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** One compact screen to pin as a shortcut: the day at a glance. */
class WidgetController extends Controller
{
    public function __invoke(Request $request, WidgetData $widgets, ChatService $chat): Response
    {
        $user = $request->user();
        $partner = $user->partner();
        $event = $widgets->nextEvent($user);
        $conversation = $chat->conversationFor($user);

        return Inertia::render('Widget', [
            'partner' => $partner ? ['firstName' => $partner->firstName(), 'avatarUrl' => $partner->avatarUrl(), 'avatarFrame' => $partner->avatar_frame] : null,
            'moods' => $widgets->moods($user),
            'event' => $event ? EventResource::make($event)->resolve() : null,
            'unreadChat' => $conversation && $partner ? $chat->unreadCount($conversation, $user) : 0,
            'photo' => $widgets->latestPhoto($user),
            'letter' => $widgets->latestLetter($user),
        ]);
    }
}
