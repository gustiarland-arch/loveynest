<?php

namespace App\Http\Controllers;

use App\Enums\Scope;
use App\Http\Resources\EventResource;
use App\Http\Resources\TaskResource;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Task;
use App\Services\Chat\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OurSpaceController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $space = $user->activeSpace();

        $activity = ActivityLog::query()
            ->where('couple_space_id', $space->id)
            ->with('actor:id,name')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'actor' => $log->actor?->firstName(),
                'isMe' => $log->actor_id === $user->id,
                'action' => $log->action,
                'title' => $log->subject_title,
                'at' => $log->created_at->toIso8601String(),
            ]);

        $chat = null;
        if ($conversation = app(ChatService::class)->conversationFor($user)) {
            $last = $conversation->messages()->latest('id')->first();
            $chat = [
                'unread' => app(ChatService::class)->unreadCount($conversation, $user),
                'last' => $last ? [
                    'mine' => $last->sender_id === $user->id,
                    'body' => \App\Support\E2E::limit(ChatService::preview($last), 80),
                    'at' => $last->created_at->toIso8601ZuluString(),
                ] : null,
            ];
        }

        return Inertia::render('OurSpace', [
            'chat' => $chat,
            'space' => [
                'name' => $space->name,
                'members' => $space->activeMembers()->get(\App\Models\User::pictureColumns())
                    ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'avatarUrl' => $m->avatarUrl(), 'avatarFrame' => $m->avatar_frame, 'since' => $m->pivot->joined_at])
                    ->values(),
            ],
            'tasks' => TaskResource::collection(
                Task::query()->inSpaceOf($user, Scope::Shared)->open()->agendaOrder()->with(['creator:id,name', 'assignee', 'subtasks'])->limit(5)->get()
            )->resolve(),
            'openCount' => Task::query()->inSpaceOf($user, Scope::Shared)->open()->count(),
            'events' => EventResource::collection(
                Event::query()->inSpaceOf($user, Scope::Shared)->upcoming()->with('creator:id,name')->limit(5)->get()
            )->resolve(),
            'activity' => $activity,
        ]);
    }
}
