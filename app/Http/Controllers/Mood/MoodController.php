<?php

namespace App\Http\Controllers\Mood;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\Mood;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A mood is the person's own. The partner can read it only while the two are
 * paired in an active space; nobody else ever can.
 */
class MoodController extends Controller
{
    private const HISTORY_DAYS = 30;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $partner = $user->partner();
        $today = CarbonImmutable::now($user->timezone)->toDateString();
        $from = CarbonImmutable::now($user->timezone)->subDays(self::HISTORY_DAYS - 1)->toDateString();

        $ids = array_filter([$user->id, $partner?->id]);
        $rows = Mood::whereIn('user_id', $ids)->where('mood_date', '>=', $from)->orderByDesc('mood_date')->get();

        $present = fn ($m) => ['date' => $m->mood_date->toDateString(), 'level' => $m->level, 'note' => $m->note, 'share' => $m->share, 'company' => $m->wants_company];

        return Inertia::render('Mood/Index', [
            'today' => $today,
            'partner' => $partner ? ['firstName' => $partner->firstName()] : null,
            'mine' => $rows->where('user_id', $user->id)->map($present)->values(),
            'theirs' => $partner ? $rows->where('user_id', $partner->id)->map(fn (Mood $m) => $m->forPartner())->filter()->values() : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'level' => ['required', 'integer', Rule::in(Mood::LEVELS)],
            'note' => ['nullable', ...E2E::text(140)],
            'share' => ['sometimes', Rule::in(Mood::SHARES)],
            'wants_company' => ['sometimes', 'boolean'],
        ], ['level.required' => 'Pilih suasana hatimu dulu.', 'level.in' => 'Pilih suasana hatimu dulu.', 'note.max' => 'Catatan maksimal 140 karakter.']);

        $user = $request->user();
        $note = trim((string) ($data['note'] ?? ''));
        Mood::updateOrCreate(
            ['user_id' => $user->id, 'mood_date' => CarbonImmutable::now($user->timezone)->toDateString()],
            ['level' => $data['level'], 'note' => $note === '' ? null : $note, 'share' => $data['share'] ?? 'full', 'wants_company' => (bool) ($data['wants_company'] ?? false) && ($data['share'] ?? 'full') !== 'private'],
        );

        return back()->with('success', 'Suasana hatimu tersimpan.');
    }
}
