<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\AiQuizQuestion;
use App\Models\ChallengeCompletion;
use App\Models\Checkin;
use App\Models\CoupleSpace;
use App\Models\Dream;
use App\Models\HabitCheckin;
use App\Models\QuizAnswer;
use App\Models\Reward;
use App\Models\SavingsGoal;
use App\Services\Ai\QuizWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Light games for two: a guess-each-other quiz, small challenges, question
 * cards and badges. All of it is skippable, and nothing counts against you.
 * The quiz is a conversation starter, not a psychological test.
 */
class PlayController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $space = $this->space($request);
        $partner = $user->partner();
        $items = $this->questions($space);
        $today = CarbonImmutable::now($user->timezone)->toDateString();

        $answers = QuizAnswer::where('couple_space_id', $space->id)->get()->groupBy('user_id');
        $mine = ($answers[$user->id] ?? collect())->keyBy('question_index');
        $theirs = $partner ? ($answers[$partner->id] ?? collect())->keyBy('question_index') : collect();

        $quiz = collect($items)->values()->map(function (array $item, int $position) use ($mine, $theirs) {
            $i = $item['index'];
            $m = $mine[$i] ?? null;
            $p = $theirs[$i] ?? null;
            $both = $m && $p;

            return [
                'index' => $i, 'number' => $position + 1, 'ai' => $item['ai'], 'question' => $item['q'], 'choices' => $item['c'],
                'mine' => $m ? ['self' => $m->self_choice, 'guess' => $m->guess_choice] : null,
                'partnerAnswered' => (bool) $p,
                // Their answers stay hidden until both of you have answered this question.
                'reveal' => $both ? ['partnerSelf' => $p->self_choice, 'partnerGuess' => $p->guess_choice] : null,
            ];
        })->values();
        $revealed = $quiz->filter(fn ($q) => $q['reveal']);

        $done = ChallengeCompletion::where('couple_space_id', $space->id)->where('user_id', $user->id)->where('done_on', '>=', CarbonImmutable::now($user->timezone)->subDays(60)->toDateString())->get();

        return Inertia::render('Berdua/Play', [
            'tab' => in_array($request->query('tab'), ['quiz', 'challenge', 'cards', 'badges', 'rewards'], true) ? $request->query('tab') : 'quiz',
            'partner' => $partner ? ['name' => $partner->firstName()] : null,
            'quiz' => $quiz,
            'ai' => ['enabled' => app(QuizWriter::class)->enabled(), 'count' => AiQuizQuestion::where('couple_space_id', $space->id)->count()],
            'score' => ['matched' => $revealed->filter(fn ($q) => $q['mine']['guess'] === $q['reveal']['partnerSelf'])->count(), 'of' => $revealed->count()],
            'challenges' => collect(trans('quiz.challenges', [], 'id'))->map(fn ($text, $key) => [
                'key' => $key, 'text' => $text,
                'today' => $done->contains(fn ($c) => $c->challenge_key === $key && $c->done_on->toDateString() === $today),
                'times' => $done->where('challenge_key', $key)->count(),
            ])->values(),
            'cards' => trans('quiz.cards', [], 'id'),
            'rewards' => Reward::where('couple_space_id', $space->id)->latest('id')->limit(100)->get()->map(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'given' => (bool) $r->given_at])->values(),
            'badges' => $this->badges($space, $user->id, $done->count(), $mine->count()),
        ]);
    }

    public function answer(Request $request): RedirectResponse
    {
        $user = $request->user();
        $space = $this->space($request);
        $items = collect($this->questions($space))->keyBy('index');
        $data = $request->validate(['question_index' => ['required', 'integer', 'min:0'], 'self_choice' => ['required', 'integer', 'min:0'], 'guess_choice' => ['required', 'integer', 'min:0']]);
        $item = $items->get((int) $data['question_index']);
        abort_unless($item, 422, 'Soal tidak ditemukan.');
        $max = count($item['c']) - 1;
        abort_if($data['self_choice'] > $max || $data['guess_choice'] > $max, 422, 'Pilihan tidak valid.');

        QuizAnswer::updateOrCreate(
            ['couple_space_id' => $space->id, 'user_id' => $user->id, 'question_index' => $data['question_index']],
            ['self_choice' => $data['self_choice'], 'guess_choice' => $data['guess_choice']],
        );

        return back();
    }

    /** New questions written by the AI. Both of you get the same ones. */
    public function aiGenerate(Request $request, QuizWriter $writer): RedirectResponse
    {
        $user = $request->user();
        $space = $this->space($request);
        $data = $request->validate(['topic' => ['nullable', 'string', 'max:60']]);

        if (AiQuizQuestion::where('couple_space_id', $space->id)->count() >= 120) {
            return back()->withErrors(['ai' => 'Soal AI sudah banyak (maksimal 120). Hapus yang lama dulu.']);
        }
        // A few rounds a day is plenty, and keeps a free Gemini quota safe.
        $key = "ai-quiz:{$space->id}:".now()->toDateString();
        if ((int) Cache::get($key, 0) >= 6) {
            return back()->withErrors(['ai' => 'Hari ini sudah 6 kali membuat soal. Coba lagi besok.']);
        }

        try {
            $questions = $writer->write($data['topic'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addDay());
        foreach ($questions as $q) {
            AiQuizQuestion::create([
                'couple_space_id' => $space->id, 'created_by' => $user->id, 'topic' => ($data['topic'] ?? null) ?: null,
                'question' => $q['question'], 'choices' => $q['choices'],
            ]);
        }

        return back()->with('success', count($questions).' soal baru dari AI ditambahkan.');
    }

    public function aiClear(Request $request): RedirectResponse
    {
        $space = $this->space($request);
        $ids = AiQuizQuestion::where('couple_space_id', $space->id)->get()->map->index()->all();
        DB::transaction(function () use ($space, $ids) {
            QuizAnswer::where('couple_space_id', $space->id)->whereIn('question_index', $ids)->delete();
            AiQuizQuestion::where('couple_space_id', $space->id)->delete();
        });

        return back()->with('success', 'Soal AI dihapus.');
    }

    /**
     * Built-in questions first, then the AI ones written for this space.
     *
     * @return list<array{index: int, q: string, c: list<string>, ai: bool}>
     */
    private function questions(CoupleSpace $space): array
    {
        $list = collect(trans('quiz.items', [], 'id'))->map(fn ($item, $i) => ['index' => (int) $i, 'q' => $item['q'], 'c' => $item['c'], 'ai' => false]);
        $ai = AiQuizQuestion::where('couple_space_id', $space->id)->orderBy('id')->get()
            ->map(fn (AiQuizQuestion $a) => ['index' => $a->index(), 'q' => $a->question, 'c' => array_values($a->choices), 'ai' => true]);

        return $list->concat($ai)->values()->all();
    }

    public function challenge(Request $request): RedirectResponse
    {
        $user = $request->user();
        $space = $this->space($request);
        $data = $request->validate(['key' => ['required', 'string']]);
        abort_unless(array_key_exists($data['key'], trans('quiz.challenges', [], 'id')), 422, 'Tantangan tidak dikenal.');
        $today = CarbonImmutable::now($user->timezone)->toDateString();

        $where = ['couple_space_id' => $space->id, 'user_id' => $user->id, 'challenge_key' => $data['key'], 'done_on' => $today];
        $existing = ChallengeCompletion::where($where)->first();
        $existing ? $existing->delete() : ChallengeCompletion::create($where);

        return back();
    }

    /** Only badges that follow from real records. */
    /** A reward the two of you made up, like "dinner of my choice". */
    public function rewardStore(Request $request): RedirectResponse
    {
        $space = $this->space($request);
        $data = $request->validate(['title' => ['required', ...E2E::text(120)]]);
        Reward::create(['couple_space_id' => $space->id, 'created_by' => $request->user()->id, 'title' => trim($data['title'])]);

        return back();
    }

    public function rewardToggle(Request $request, int $reward): RedirectResponse
    {
        $row = Reward::where('couple_space_id', $this->space($request)->id)->findOrFail($reward);
        $row->update(['given_at' => $row->given_at ? null : now()]);

        return back();
    }

    public function rewardDestroy(Request $request, int $reward): RedirectResponse
    {
        Reward::where('couple_space_id', $this->space($request)->id)->findOrFail($reward)->delete();

        return back();
    }

    private function badges(CoupleSpace $space, int $userId, int $challengeCount, int $quizAnswered): array
    {
        $earned = [
            'first_checkin' => Checkin::where('couple_space_id', $space->id)->where('user_id', $userId)->exists(),
            'habit_week' => HabitCheckin::where('user_id', $userId)->where('done_on', '>=', now()->subDays(7)->toDateString())->count() >= 7,
            'challenges5' => $challengeCount >= 5,
            'quiz10' => $quizAnswered >= count(trans('quiz.items', [], 'id')),
            'saver' => SavingsGoal::where('couple_space_id', $space->id)->where('saved_amount', '>', 0)->exists(),
            'dreamer' => Dream::where('couple_space_id', $space->id)->where('status', 'done')->exists(),
            'hundred' => $space->anniversary_date !== null && CarbonImmutable::parse($space->anniversary_date)->diffInDays(now(), false) >= 100,
        ];

        return collect(trans('quiz.badges', [], 'id'))->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'earned' => (bool) ($earned[$key] ?? false)])->values()->all();
    }

    private function space(Request $request): CoupleSpace
    {
        $space = $request->user()->activeSpace();
        abort_unless($space, 404);

        return $space;
    }
}
