<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\CoupleSpace;
use App\Models\Reflection;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** A monthly look back (private until shared) and the small agreements you make together. */
class ReflectionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $space = $this->space($request);
        $partner = $user->partner();
        $month = CarbonImmutable::now($user->timezone)->startOfMonth();

        $row = fn (Reflection $r) => ['month' => $r->month_start->toDateString(), 'went_well' => $r->went_well, 'to_improve' => $r->to_improve, 'next_month' => $r->next_month, 'shared' => $r->shared];
        $mine = Reflection::where('couple_space_id', $space->id)->where('user_id', $user->id)->orderByDesc('month_start')->limit(12)->get();
        $theirs = $partner ? Reflection::where('couple_space_id', $space->id)->where('user_id', $partner->id)->where('shared', true)->orderByDesc('month_start')->limit(12)->get() : collect();

        return Inertia::render('Berdua/Reflection', [
            'month' => $month->toDateString(),
            'current' => ($c = $mine->first(fn ($r) => $r->month_start->toDateString() === $month->toDateString())) ? $row($c) : null,
            'history' => $mine->reject(fn ($r) => $r->month_start->toDateString() === $month->toDateString())->map($row)->values(),
            'partner' => $partner ? ['name' => $partner->firstName()] : null,
            'partnerRows' => $theirs->map($row)->values(),
            'agreements' => Agreement::where('couple_space_id', $space->id)->orderByRaw("FIELD(status, 'active', 'paused', 'done')")->orderByDesc('id')->limit(100)->get(['id', 'title', 'note', 'status']),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = $request->user();
        $space = $this->space($request);
        $data = $request->validate([
            'went_well' => ['nullable', ...E2E::text(500)], 'to_improve' => ['nullable', ...E2E::text(500)],
            'next_month' => ['nullable', ...E2E::text(500)], 'shared' => ['sometimes', 'boolean'],
        ]);
        Reflection::updateOrCreate(
            ['couple_space_id' => $space->id, 'user_id' => $user->id, 'month_start' => CarbonImmutable::now($user->timezone)->startOfMonth()->toDateString()],
            $data,
        );

        return back()->with('success', 'Refleksi tersimpan.');
    }

    public function storeAgreement(Request $request): RedirectResponse
    {
        $space = $this->space($request);
        $data = $request->validate(['title' => ['required', ...E2E::text(120)], 'note' => ['nullable', ...E2E::text(300)]]);
        $agreement = new Agreement($data);
        $agreement->couple_space_id = $space->id;
        $agreement->created_by = $request->user()->id;
        $agreement->save();

        return back()->with('success', 'Kesepakatan ditambahkan.');
    }

    public function updateAgreement(Request $request, int $agreement): RedirectResponse
    {
        $row = Agreement::where('couple_space_id', $this->space($request)->id)->findOrFail($agreement);
        $row->update($request->validate(['title' => ['sometimes', ...E2E::text(120)], 'note' => ['nullable', ...E2E::text(300)], 'status' => ['sometimes', Rule::in(Agreement::STATUSES)]]));

        return back();
    }

    public function destroyAgreement(Request $request, int $agreement): RedirectResponse
    {
        Agreement::where('couple_space_id', $this->space($request)->id)->findOrFail($agreement)->delete();

        return back()->with('success', 'Kesepakatan dihapus.');
    }

    private function space(Request $request): CoupleSpace
    {
        $space = $request->user()->activeSpace();
        abort_unless($space, 404);

        return $space;
    }
}
