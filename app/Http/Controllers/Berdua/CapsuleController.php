<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\TimeCapsule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A sealed note for the couple. Until the open date nobody, the writer
 * included, can read it, and the text is not part of the response.
 */
class CapsuleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::now($user->timezone)->startOfDay();

        $items = TimeCapsule::where('couple_space_id', $user->activeSpaceId())->with('creator:id,name')->orderBy('open_on')->get()
            ->map(function (TimeCapsule $c) use ($today, $user) {
                $open = ! CarbonImmutable::parse($c->open_on->toDateString())->gt($today);

                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'open_on' => $c->open_on->toDateString(),
                    'open' => $open,
                    'in' => $open ? 0 : (int) $today->diffInDays(CarbonImmutable::parse($c->open_on->toDateString())),
                    'body' => $open ? $c->body : null,
                    'mine' => $c->created_by === $user->id,
                    'by' => $c->creator?->firstName(),
                ];
            })->sortBy([['open', 'asc'], ['open_on', 'asc']])->values();

        return Inertia::render('Berdua/Capsules', ['items' => $items, 'today' => $today->toDateString(), 'hasPartner' => (bool) $user->partner()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['title' => trim((string) $request->input('title')), 'body' => trim((string) $request->input('body'))]);
        $tomorrow = CarbonImmutable::now($user->timezone)->addDay()->toDateString();
        $data = $request->validate([
            'title' => ['required', ...E2E::text(120)],
            'body' => ['required', ...E2E::text(5000)],
            'open_on' => ['required', 'date_format:Y-m-d', "after_or_equal:{$tomorrow}", 'before:2100-01-01'],
        ], ['open_on.after_or_equal' => 'Tanggal buka paling cepat besok.'], ['title' => 'judul', 'body' => 'isi', 'open_on' => 'tanggal buka']);

        $capsule = new TimeCapsule($data);
        $capsule->couple_space_id = $user->activeSpaceId();
        $capsule->created_by = $user->id;
        $capsule->save();

        return back()->with('success', 'Kapsul waktu disegel.');
    }

    /** Only the writer may remove a capsule. */
    public function destroy(Request $request, int $capsule): RedirectResponse
    {
        $row = TimeCapsule::where('couple_space_id', $request->user()->activeSpaceId())->where('created_by', $request->user()->id)->findOrFail($capsule);
        $row->delete();

        return back()->with('success', 'Kapsul dihapus.');
    }
}
