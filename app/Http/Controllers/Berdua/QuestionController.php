<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\DailyAnswer;
use App\Services\Couple\DailyQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Both answer the same question each day. The partner's answer stays hidden
 * until you have answered yours (it is not even sent to the browser).
 */
class QuestionController extends Controller
{
    public function index(Request $request, DailyQuestion $questions): Response
    {
        $user = $request->user();
        $partner = $user->partner();
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $spaceId = $user->activeSpaceId();

        $answers = DailyAnswer::where('couple_space_id', $spaceId)
            ->where('answer_date', '>=', $today->subDays(14)->toDateString())->get();
        $mineToday = $answers->first(fn ($a) => $a->user_id === $user->id && $a->answer_date->toDateString() === $today->toDateString());
        $theirsToday = $partner ? $answers->first(fn ($a) => $a->user_id === $partner->id && $a->answer_date->toDateString() === $today->toDateString()) : null;

        $history = $answers->filter(fn ($a) => $a->answer_date->lt($today))
            ->groupBy(fn ($a) => $a->answer_date->toDateString())
            ->map(fn ($rows, $date) => [
                'date' => $date,
                'question' => $questions->text($rows->first()->question_index),
                'mine' => $rows->firstWhere('user_id', $user->id)?->answer,
                'theirs' => $partner ? $rows->firstWhere('user_id', $partner->id)?->answer : null,
            ])->sortByDesc('date')->values();

        return Inertia::render('Berdua/Question', [
            'question' => $questions->text($questions->indexFor($today)),
            'partner' => $partner ? ['firstName' => $partner->firstName()] : null,
            'mine' => $mineToday?->answer,
            // Revealed only after you have answered.
            'theirs' => $mineToday ? $theirsToday?->answer : null,
            'partnerAnswered' => (bool) $theirsToday,
            'history' => $history,
        ]);
    }

    public function store(Request $request, DailyQuestion $questions): RedirectResponse
    {
        $request->merge(['answer' => trim((string) $request->input('answer'))]);
        $data = $request->validate(['answer' => ['required', ...E2E::text(500)]], ['answer.required' => 'Tulis jawabanmu dulu.', 'answer.max' => 'Jawaban maksimal 500 karakter.']);
        $user = $request->user();
        $today = CarbonImmutable::now($user->timezone)->startOfDay();

        DailyAnswer::updateOrCreate(
            ['user_id' => $user->id, 'answer_date' => $today->toDateString()],
            ['couple_space_id' => $user->activeSpaceId(), 'question_index' => $questions->indexFor($today), 'answer' => $data['answer']],
        );

        return back()->with('success', 'Jawabanmu tersimpan.');
    }
}
