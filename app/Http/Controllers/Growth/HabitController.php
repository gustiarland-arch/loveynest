<?php

namespace App\Http\Controllers\Growth;

use App\Support\E2E;
use App\Enums\Scope;
use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\GoalEvent;
use App\Models\Habit;
use App\Models\HabitCheckin;
use App\Services\Growth\HabitStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Habits and goals, in My Space or Our Space. */
class HabitController extends Controller
{
    public function index(Request $request, HabitStats $stats): Response
    {
        $user = $request->user();
        $space = $request->query('space') === 'ours' && $user->activeSpaceId() ? 'ours' : 'mine';
        $tab = $request->query('tab') === 'goals' ? 'goals' : 'habits';
        $scope = Scope::fromSpace($space);
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $partner = $space === 'ours' ? $user->partner() : null;

        $habits = Habit::query()->inSpaceOf($user, $scope)->whereNull('archived_at')->orderBy('id')->limit(50)->get();
        $dates = HabitCheckin::whereIn('habit_id', $habits->pluck('id'))
            ->where('done_on', '>=', $today->subDays(400)->toDateString())->get(['habit_id', 'user_id', 'done_on'])
            ->groupBy(fn ($c) => $c->habit_id.':'.$c->user_id);
        $datesFor = fn (int $habitId, int $userId) => ($dates[$habitId.':'.$userId] ?? collect())->map(fn ($c) => $c->done_on->toDateString())->values();

        $habitRows = $habits->map(fn (Habit $h) => [
            'id' => $h->id,
            'title' => $h->title,
            'period' => $h->period,
            'target' => $h->period === 'daily' ? 1 : $h->target,
            'mine' => $stats->forPerson($h, $datesFor($h->id, $user->id), $today),
            'partner' => $partner ? ['name' => $partner->firstName()] + $stats->forPerson($h, $datesFor($h->id, $partner->id), $today) : null,
        ])->values();

        $goals = Goal::query()->inSpaceOf($user, $scope)->with(['events' => fn ($q) => $q->limit(5)])
            ->orderByRaw("FIELD(status, 'active', 'planned', 'done', 'cancelled')")->orderBy('target_date')->limit(100)->get()
            ->map(fn (Goal $g) => [
                'id' => $g->id, 'title' => $g->title, 'note' => $g->note, 'category' => $g->category, 'status' => $g->status,
                'target_date' => $g->target_date?->toDateString(), 'progress' => $g->progress,
                'history' => $g->events->map(fn (GoalEvent $e) => ['from' => $e->from_status, 'to' => $e->to_status, 'progress' => $e->progress, 'at' => $e->created_at?->toIso8601String()])->all(),
            ])->values();

        return Inertia::render('Growth/Index', [
            'space' => $space,
            'tab' => $tab,
            'hasCouple' => (bool) $user->activeSpaceId(),
            'habits' => $habitRows,
            'goals' => $goals,
            'summary' => [
                'checkins30' => HabitCheckin::whereIn('habit_id', $habits->pluck('id'))->where('user_id', $user->id)->where('done_on', '>=', $today->subDays(29)->toDateString())->count(),
                'goalsDone' => $goals->where('status', 'done')->count(),
                'goalsActive' => $goals->where('status', 'active')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'space' => ['required', 'in:mine,ours', fn ($a, $v, $fail) => $v === 'ours' && ! $request->user()->activeSpaceId() ? $fail('Hubungkan akun dengan pasanganmu dulu.') : null],
            'title' => ['required', ...E2E::text(80)],
            'period' => ['required', Rule::in(Habit::PERIODS)],
            'target' => ['required', 'integer', 'between:1,31'],
        ], [], ['title' => 'nama kebiasaan', 'target' => 'target']);
        abort_if($data['period'] === 'weekly' && $data['target'] > 7, 422, 'Target mingguan maksimal 7.');
        $user = $request->user();
        abort_if(Habit::query()->inSpaceOf($user, Scope::fromSpace($data['space']))->whereNull('archived_at')->count() >= 50, 422, 'Maksimal 50 kebiasaan aktif.');

        $habit = new Habit(['title' => trim($data['title']), 'period' => $data['period'], 'target' => $data['period'] === 'daily' ? 1 : $data['target']]);
        $habit->assignSpace($user, Scope::fromSpace($data['space']))->save();

        return back()->with('success', 'Kebiasaan ditambahkan.');
    }

    public function update(Request $request, int $habit): RedirectResponse
    {
        $row = $this->find($request, $habit);
        $data = $request->validate([
            'title' => ['required', ...E2E::text(80)],
            'period' => ['required', Rule::in(Habit::PERIODS)],
            'target' => ['required', 'integer', 'between:1,31'],
        ]);
        abort_if($data['period'] === 'weekly' && $data['target'] > 7, 422, 'Target mingguan maksimal 7.');
        $row->fill(['title' => trim($data['title']), 'period' => $data['period'], 'target' => $data['period'] === 'daily' ? 1 : $data['target']])->save();

        return back()->with('success', 'Kebiasaan diperbarui.');
    }

    /** Archived, not deleted: the check-in history stays. */
    public function destroy(Request $request, int $habit): RedirectResponse
    {
        $this->find($request, $habit)->forceFill(['archived_at' => now()])->save();

        return back()->with('success', 'Kebiasaan diarsipkan.');
    }

    /** Today, or one of the two days before, can be ticked or unticked; each person only ticks their own. */
    public function check(Request $request, int $habit): RedirectResponse
    {
        $row = $this->find($request, $habit);
        $user = $request->user();
        $today = CarbonImmutable::now($user->timezone)->startOfDay();
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? $today->toDateString();
        abort_unless($date <= $today->toDateString() && $date >= $today->subDays(2)->toDateString(), 422, 'Hanya 3 hari terakhir yang bisa dicentang.');
        abort_if($row->archived_at, 404);

        DB::transaction(function () use ($row, $user, $date) {
            $existing = HabitCheckin::where('habit_id', $row->id)->where('user_id', $user->id)->where('done_on', $date)->first();
            $existing ? $existing->delete() : HabitCheckin::create(['habit_id' => $row->id, 'user_id' => $user->id, 'done_on' => $date]);
        });

        return back();
    }

    public function storeGoal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'space' => ['required', 'in:mine,ours', fn ($a, $v, $fail) => $v === 'ours' && ! $request->user()->activeSpaceId() ? $fail('Hubungkan akun dengan pasanganmu dulu.') : null],
            'title' => ['required', ...E2E::text(120)],
            'note' => ['nullable', ...E2E::text(1000)],
            'category' => ['nullable', 'string', 'max:30'],
            'target_date' => ['nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
        ], [], ['title' => 'judul', 'target_date' => 'tanggal target']);
        $user = $request->user();
        DB::transaction(function () use ($user, $data) {
            $goal = new Goal(['title' => trim($data['title']), 'note' => $data['note'] ?? null, 'category' => $data['category'] ?? null, 'target_date' => $data['target_date'] ?? null, 'status' => 'planned', 'progress' => 0]);
            $goal->assignSpace($user, Scope::fromSpace($data['space']))->save();
            GoalEvent::create(['goal_id' => $goal->id, 'user_id' => $user->id, 'from_status' => null, 'to_status' => 'planned', 'progress' => 0]);
        });

        return back()->with('success', 'Tujuan ditambahkan.');
    }

    public function updateGoal(Request $request, int $goal): RedirectResponse
    {
        $row = Goal::findOrFail($goal);
        abort_unless($row->isAccessibleBy($request->user()), 404);
        $data = $request->validate([
            'title' => ['sometimes', ...E2E::text(120)],
            'note' => ['sometimes', 'nullable', ...E2E::text(1000)],
            'category' => ['sometimes', 'nullable', 'string', 'max:30'],
            'target_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::in(Goal::STATUSES)],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
        ]);

        DB::transaction(function () use ($row, $data, $request) {
            $from = $row->status;
            $row->fill($data);
            if (($data['status'] ?? null) === 'done') {
                $row->progress = 100;
            }
            $row->save();
            if (isset($data['status']) && $data['status'] !== $from || isset($data['progress']) && $row->wasChanged('progress')) {
                GoalEvent::create(['goal_id' => $row->id, 'user_id' => $request->user()->id, 'from_status' => $from, 'to_status' => $row->status, 'progress' => $row->progress]);
            }
        });

        return back();
    }

    public function destroyGoal(Request $request, int $goal): RedirectResponse
    {
        $row = Goal::findOrFail($goal);
        abort_unless($row->isAccessibleBy($request->user()), 404);
        $row->delete();

        return back()->with('success', 'Tujuan dihapus.');
    }

    private function find(Request $request, int $id): Habit
    {
        $row = Habit::findOrFail($id);
        abort_unless($row->isAccessibleBy($request->user()), 404);

        return $row;
    }
}
