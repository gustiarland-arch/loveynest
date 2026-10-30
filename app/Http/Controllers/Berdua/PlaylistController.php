<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\CoupleSpace;
use App\Models\PlaylistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A shared list of songs. Links are only stored, never fetched. */
class PlaylistController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $space = $this->space($request);
        $names = $space->members()->whereNull('left_at')->with('user:id,name')->get()->mapWithKeys(fn ($m) => [$m->user_id => $m->user?->firstName()]);

        return Inertia::render('Berdua/Playlist', [
            'me' => $user->id,
            'items' => PlaylistItem::where('couple_space_id', $space->id)->orderByDesc('id')->limit(300)->get()->map(fn (PlaylistItem $i) => [
                'id' => $i->id, 'title' => $i->title, 'artist' => $i->artist, 'url' => $i->url, 'note' => $i->note,
                'is_ours' => $i->is_ours, 'by' => $names[$i->added_by] ?? null, 'mine' => $i->added_by === $user->id,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $space = $this->space($request);
        $data = $request->validate([
            'title' => ['required', ...E2E::text(120)],
            'artist' => ['nullable', ...E2E::text(120)],
            'url' => ['nullable', ...(E2E::enabled() ? E2E::text(500) : ['url:http,https', 'max:500'])],
            'note' => ['nullable', ...E2E::text(200)],
            'is_ours' => ['sometimes', 'boolean'],
        ]);
        $item = new PlaylistItem($data);
        $item->couple_space_id = $space->id;
        $item->added_by = $request->user()->id;
        $item->save();

        return back()->with('success', 'Lagu ditambahkan.');
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $row = PlaylistItem::where('couple_space_id', $this->space($request)->id)->findOrFail($item);
        $row->update($request->validate(['is_ours' => ['required', 'boolean']]));

        return back();
    }

    public function destroy(Request $request, int $item): RedirectResponse
    {
        PlaylistItem::where('couple_space_id', $this->space($request)->id)->findOrFail($item)->delete();

        return back()->with('success', 'Lagu dihapus.');
    }

    private function space(Request $request): CoupleSpace
    {
        $space = $request->user()->activeSpace();
        abort_unless($space, 404);

        return $space;
    }
}
