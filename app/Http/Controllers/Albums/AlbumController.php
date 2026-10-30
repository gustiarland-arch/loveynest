<?php

namespace App\Http\Controllers\Albums;

use App\Support\E2E;
use App\Enums\Scope;
use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Event;
use App\Models\Photo;
use App\Services\Albums\PhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlbumController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $space = $request->query('space') === 'ours' && $user->activeSpaceId() ? 'ours' : 'mine';

        $albums = Album::query()->inSpaceOf($user, Scope::fromSpace($space))
            ->withCount('photos')
            ->addSelect(['cover_id' => Photo::select('id')->whereColumn('album_id', 'albums.id')->orderByRaw('(photos.id = albums.cover_photo_id) desc')->latest('id')->limit(1)])
            ->orderByDesc('updated_at')->get();

        return Inertia::render('Albums/Index', [
            'space' => $space,
            'hasCouple' => (bool) $user->activeSpaceId(),
            'albums' => $albums->map(fn (Album $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'description' => $a->description,
                'count' => $a->photos_count,
                'cover' => $a->cover_id ? route('photos.file', [$a->cover_id, 'thumb']) : null,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, creating: true);
        $album = new Album(['title' => $data['title'], 'description' => $data['description'] ?? null]);
        $album->assignSpace($request->user(), Scope::fromSpace($data['space']))->save();

        return to_route('albums.show', $album)->with('success', 'Album dibuat. Tambahkan fotonya.');
    }

    public function show(Request $request, Album $album): Response
    {
        $this->authorize('view', $album);

        return Inertia::render('Albums/Show', [
            'album' => [
                'id' => $album->id,
                'title' => $album->title,
                'description' => $album->description,
                'space' => $album->scope->space(),
                'cover' => $album->cover_photo_id,
            ],
            'events' => Event::query()->visibleTo($request->user())->orderByDesc('starts_at')->limit(60)->get(['id', 'title', 'starts_at'])->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'date' => $e->starts_at->toDateString()]),
            'photos' => $album->photos()->with('uploader:id,name')->orderByDesc('id')->get()->map(fn (Photo $p) => [
                'id' => $p->id,
                'thumb' => route('photos.file', [$p->id, 'thumb']),
                'full' => route('photos.file', [$p->id, 'full']),
                'by' => $album->isShared() ? $p->uploader?->firstName() : null,
                'date' => $p->created_at->toIso8601String(),
                'caption' => $p->caption, 'location' => $p->location, 'taken_on' => $p->taken_on?->toDateString(),
                'favorite' => $p->is_favorite, 'event_id' => $p->event_id,
            ]),
            'maxPerAlbum' => PhotoService::MAX_PER_ALBUM,
            'uploadLimits' => \App\Support\UploadLimits::forClient(),
        ]);
    }

    public function cover(Request $request, Album $album): RedirectResponse
    {
        $this->authorize('update', $album);
        $data = $request->validate(['photo_id' => ['nullable', 'integer']]);
        abort_if(! empty($data['photo_id']) && ! $album->photos()->whereKey($data['photo_id'])->exists(), 422, 'Foto bukan bagian dari album ini.');
        $album->forceFill(['cover_photo_id' => $data['photo_id'] ?? null])->save();

        return back()->with('success', 'Sampul album diperbarui.');
    }

    public function update(Request $request, Album $album): RedirectResponse
    {
        $this->authorize('update', $album);
        $data = $this->validated($request, creating: false);
        $album->fill(['title' => $data['title'], 'description' => $data['description'] ?? null])->save();

        return back()->with('success', 'Album diperbarui.');
    }

    public function destroy(Request $request, Album $album, PhotoService $photos): RedirectResponse
    {
        $this->authorize('delete', $album);
        $photos->deleteAlbum($album);

        return to_route('albums.index', ['space' => $album->scope->space()])->with('success', 'Album dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $request->merge([
            'title' => trim((string) $request->input('title')),
            'description' => ($d = trim((string) $request->input('description'))) === '' ? null : $d,
        ]);

        return $request->validate([
            'space' => $creating ? ['required', 'in:mine,ours', function ($a, $v, $fail) use ($request) {
                if ($v === 'ours' && ! $request->user()->activeSpaceId()) {
                    $fail('Hubungkan akun dengan pasanganmu dulu untuk memakai Our Space.');
                }
            }] : ['prohibited'],
            'title' => ['required', ...E2E::text(80)],
            'description' => ['nullable', ...E2E::text(300)],
        ], [], ['title' => 'judul album', 'description' => 'deskripsi']);
    }
}
