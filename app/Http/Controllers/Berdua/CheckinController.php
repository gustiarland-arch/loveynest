<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\CoupleSpace;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Weekly check-in. Your answers stay yours until you choose to share them. */
class CheckinController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $space = $this->space($request);
        $week = $this->week($user->timezone);
        $partner = $user->partner();

        $mine = Checkin::where('couple_space_id', $space->id)->where('user_id', $user->id)->orderByDesc('week_start')->limit(12)->get();
        $theirs = $partner ? Checkin::where('couple_space_id', $space->id)->where('user_id', $partner->id)->where('shared', true)->orderByDesc('week_start')->limit(12)->get() : collect();
        $row = fn (Checkin $c) => ['week' => $c->week_start->toDateString(), 'feeling' => $c->feeling, 'appreciation' => $c->appreciation, 'improve' => $c->improve, 'shared' => $c->shared];

        return Inertia::render('Berdua/Checkin', [
            'week' => $week->toDateString(),
            'current' => ($c = $mine->first(fn ($c) => $c->week_start->toDateString() === $week->toDateString())) ? $row($c) : null,
            'history' => $mine->reject(fn ($c) => $c->week_start->toDateString() === $week->toDateString())->map($row)->values(),
            'partner' => $partner ? ['name' => $partner->firstName()] : null,
            'partnerRows' => $theirs->map($row)->values(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = $request->user();
        $space = $this->space($request);
        $data = $request->validate([
            'feeling' => ['required', 'integer', 'between:1,5'],
            'appreciation' => ['nullable', ...E2E::text(300)],
            'improve' => ['nullable', ...E2E::text(300)],
            'shared' => ['sometimes', 'boolean'],
        ]);
        Checkin::updateOrCreate(
            ['couple_space_id' => $space->id, 'user_id' => $user->id, 'week_start' => $this->week($user->timezone)->toDateString()],
            $data,
        );

        return back()->with('success', 'Check-in tersimpan.');
    }

    private function week(string $timezone): CarbonImmutable
    {
        return CarbonImmutable::now($timezone)->startOfWeek()->startOfDay();
    }

    private function space(Request $request): CoupleSpace
    {
        $space = $request->user()->activeSpace();
        abort_unless($space, 404);

        return $space;
    }
}
