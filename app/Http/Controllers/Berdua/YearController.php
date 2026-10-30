<?php

namespace App\Http\Controllers\Berdua;

use App\Http\Controllers\Controller;
use App\Models\CoupleSpace;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** A look back at one year, counted from what is really stored. Nothing is invented. */
class YearController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $space = $user->activeSpace();
        abort_unless($space, 404);
        $nowYear = CarbonImmutable::now($user->timezone)->year;
        $year = (int) $request->query('year', $nowYear);
        $year = min(max($year, 2000), $nowYear);
        $from = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $user->timezone)->utc();
        $to = $from->addYear();
        $between = fn ($q, $col) => $q->where($col, '>=', $from)->where($col, '<', $to);

        $messages = $between(DB::table('chat_messages')->join('chat_conversations', 'chat_conversations.id', '=', 'chat_messages.chat_conversation_id')
            ->where('chat_conversations.couple_space_id', $space->id)->whereNull('chat_messages.deleted_at'), 'chat_messages.created_at');
        $spent = (clone $between(DB::table('date_expenses')->where('couple_space_id', $space->id)->whereNull('voided_at'), 'spent_on'))->sum('amount');

        $stats = [
            'messages' => (clone $messages)->count(),
            'messagesMine' => (clone $messages)->where('chat_messages.sender_id', $user->id)->count(),
            'photos' => $between(DB::table('photos')->join('albums', 'albums.id', '=', 'photos.album_id')->where('albums.couple_space_id', $space->id), 'photos.created_at')->count(),
            'tasksDone' => $between(DB::table('tasks')->where('couple_space_id', $space->id), 'completed_at')->count(),
            'events' => $between(DB::table('events')->where('couple_space_id', $space->id), 'created_at')->count(),
            'answers' => $between(DB::table('daily_answers')->where('couple_space_id', $space->id), 'answer_date')->count(),
            'moods' => $between(DB::table('moods')->where('user_id', $user->id), 'mood_date')->count(),
            'habitChecks' => $between(DB::table('habit_checkins')->where('user_id', $user->id), 'done_on')->count(),
            'dreamsDone' => $between(DB::table('dreams')->where('couple_space_id', $space->id)->where('status', 'done'), 'done_at')->count(),
            'spent' => (int) $spent,
            'saved' => (int) DB::table('savings_goals')->where('couple_space_id', $space->id)->sum('saved_amount'),
        ];
        $topMood = DB::table('moods')->where('user_id', $user->id)->where('mood_date', '>=', $from->toDateString())->where('mood_date', '<', $to->toDateString())
            ->selectRaw('level, count(*) c')->groupBy('level')->orderByDesc('c')->first();

        return Inertia::render('Berdua/Year', [
            'year' => $year, 'maxYear' => $nowYear, 'stats' => $stats, 'topMood' => $topMood?->level,
            'daysTogether' => $space->anniversary_date ? max(0, (int) CarbonImmutable::parse($space->anniversary_date->toDateString(), $request->user()->timezone)->diffInDays(CarbonImmutable::now($request->user()->timezone)->startOfDay())) : null,
            'partner' => $user->partner()?->firstName(),
        ]);
    }
}
