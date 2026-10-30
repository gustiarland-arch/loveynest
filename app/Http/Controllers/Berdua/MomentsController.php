<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\CoupleSpace;
use App\Models\Milestone;
use App\Services\Couple\CoupleFacts;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MomentsController extends Controller
{
    public function index(Request $request, CoupleFacts $facts): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::now($user->timezone)->startOfDay();

        $items = Milestone::where('couple_space_id', $user->activeSpaceId())->get()
            ->map(function (Milestone $m) use ($today) {
                $next = $m->nextDate($today);

                return [
                    'id' => $m->id,
                    'title' => $m->title,
                    'event_date' => $m->event_date->toDateString(),
                    'repeat_yearly' => $m->repeat_yearly,
                    'next' => $next?->toDateString(),
                    'in' => $next ? (int) $today->diffInDays($next) : null,
                ];
            })
            ->sortBy(fn ($m) => $m['in'] ?? PHP_INT_MAX)->values();

        return Inertia::render('Berdua/Moments', ['facts' => $facts->for($user), 'items' => $items, 'today' => $today->toDateString()]);
    }

    public function anniversary(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['anniversary_date' => ['nullable', 'date_format:Y-m-d', 'after:1950-01-01', 'before_or_equal:today'], 'met_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:1950-01-01', 'before_or_equal:today']],
            ['anniversary_date.before_or_equal' => 'Tanggal jadian tidak boleh di masa depan.', 'met_date.before_or_equal' => 'Tanggal bertemu tidak boleh di masa depan.', 'anniversary_date.date_format' => 'Tanggal tidak valid.', 'met_date.date_format' => 'Tanggal tidak valid.'],
        );
        $changes = ['anniversary_date' => $data['anniversary_date'] ?? null];
        if (array_key_exists('met_date', $data)) {
            $changes['met_date'] = $data['met_date'];
        }
        CoupleSpace::whereKey($request->user()->activeSpaceId())->update($changes);

        return back()->with('success', 'Tanggal jadian disimpan.');
    }

    public function store(Request $request): RedirectResponse
    {
        $milestone = new Milestone($this->validated($request));
        $milestone->couple_space_id = $request->user()->activeSpaceId();
        $milestone->created_by = $request->user()->id;
        $milestone->save();

        return back()->with('success', 'Momen ditambahkan.');
    }

    public function update(Request $request, int $milestone): RedirectResponse
    {
        $this->find($request, $milestone)->update($this->validated($request));

        return back()->with('success', 'Momen diperbarui.');
    }

    public function destroy(Request $request, int $milestone): RedirectResponse
    {
        $this->find($request, $milestone)->delete();

        return back()->with('success', 'Momen dihapus.');
    }

    /** Another couple's milestone answers 404, like a missing one. */
    private function find(Request $request, int $id): Milestone
    {
        return Milestone::where('couple_space_id', $request->user()->activeSpaceId())->findOrFail($id);
    }

    /** @return array{title: string, event_date: string, repeat_yearly: bool} */
    private function validated(Request $request): array
    {
        $request->merge(['title' => trim((string) $request->input('title'))]);
        $data = $request->validate([
            'title' => ['required', ...E2E::text(80)],
            'event_date' => ['required', 'date_format:Y-m-d', 'after:1950-01-01', 'before:2100-01-01'],
            'repeat_yearly' => ['required', 'boolean'],
        ], [], ['title' => 'nama momen', 'event_date' => 'tanggal']);

        return $data;
    }
}
