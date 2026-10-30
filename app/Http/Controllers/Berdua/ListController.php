<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Enums\Scope;
use App\Http\Controllers\Controller;
use App\Models\ListItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Wishlist and bucket list, in My Space or Our Space. */
class ListController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $kind = in_array($request->query('kind'), ListItem::KINDS, true) ? $request->query('kind') : 'bucket';
        $space = $request->query('space') === 'ours' && $user->activeSpaceId() ? 'ours' : 'mine';

        $max = ctype_digit((string) $request->query('max')) ? (int) $request->query('max') : null;
        $items = ListItem::query()->inSpaceOf($user, Scope::fromSpace($space))->where('kind', $kind)
            ->when($max !== null && $kind === 'wishlist', fn ($q) => $q->where(fn ($w) => $w->whereNull('price')->orWhere('price', '<=', $max)))
            ->with('creator:id,name')->orderByRaw('done_at IS NOT NULL')->orderByDesc('id')->limit(200)->get()
            ->map(fn (ListItem $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'note' => $i->note,
                'price' => $i->price,
                'done' => $i->done_at !== null,
                'by' => $i->isShared() ? $i->creator?->firstName() : null,
            ]);

        return Inertia::render('Berdua/Lists', ['kind' => $kind, 'space' => $space, 'max' => $max, 'hasCouple' => (bool) $user->activeSpaceId(), 'items' => $items]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['title' => trim((string) $request->input('title')), 'note' => ($n = trim((string) $request->input('note'))) === '' ? null : $n]);
        $data = $request->validate([
            'kind' => ['required', Rule::in(ListItem::KINDS)],
            'space' => ['required', 'in:mine,ours', fn ($a, $v, $fail) => $v === 'ours' && ! $request->user()->activeSpaceId() ? $fail('Hubungkan akun dengan pasanganmu dulu.') : null],
            'title' => ['required', ...E2E::text(120)],
            'note' => ['nullable', ...E2E::text(300)],
            'price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ], [], ['title' => 'judul', 'note' => 'catatan', 'price' => 'perkiraan harga']);

        $item = new ListItem(['title' => $data['title'], 'note' => $data['note'], 'price' => $data['kind'] === 'wishlist' ? ($data['price'] ?? null) : null]);
        $item->kind = $data['kind'];
        $item->assignSpace($request->user(), Scope::fromSpace($data['space']))->save();

        return back()->with('success', 'Ditambahkan ke daftar.');
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $row = $this->find($request, $item);
        $data = $request->validate(['done' => ['sometimes', 'boolean'], 'title' => ['sometimes', ...E2E::text(120)], 'note' => ['sometimes', 'nullable', ...E2E::text(300)], 'price' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000']]);
        if (array_key_exists('done', $data)) {
            $row->done_at = $data['done'] ? now() : null;
        }
        $row->fill(array_intersect_key($data, ['title' => 1, 'note' => 1, 'price' => 1]))->save();

        return back();
    }

    public function destroy(Request $request, int $item): RedirectResponse
    {
        $this->find($request, $item)->delete();

        return back()->with('success', 'Dihapus dari daftar.');
    }

    private function find(Request $request, int $id): ListItem
    {
        $row = ListItem::findOrFail($id);
        abort_unless($row->isAccessibleBy($request->user()), 404);

        return $row;
    }
}
