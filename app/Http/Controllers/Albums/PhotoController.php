<?php

namespace App\Http\Controllers\Albums;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Event;
use App\Models\Photo;
use App\Services\Albums\PhotoService;
use App\Support\E2E;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class PhotoController extends Controller
{
    public const MAX_FILES = 10;

    public function store(Request $request, Album $album, PhotoService $photos): RedirectResponse
    {
        $this->authorize('update', $album);

        if (E2E::enabled()) {
            // Locked in the browser: display picture, thumbnail and size arrive together.
            $request->validate([
                'photos' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
                ...PhotoService::sealedRules('photos', true, true),
            ], ['photos.required' => 'Pilih foto dulu.', 'photos.max' => 'Maksimal '.self::MAX_FILES.' foto sekali unggah.']);
            $sealed = collect($request->file('photos'))->keys()->map(fn ($i) => [
                'thumb' => $request->file("photos_thumb.{$i}"),
                'width' => (int) $request->input("photos_width.{$i}"),
                'height' => (int) $request->input("photos_height.{$i}"),
            ])->all();
            try {
                $count = $photos->addMany($album, $request->user(), $request->file('photos'), $sealed);
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['photos' => $e->getMessage()]);
            }

            return back()->with('success', $count === 1 ? 'Foto ditambahkan.' : "{$count} foto ditambahkan.");
        }

        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:25600', 'dimensions:min_width=64,min_height=64,max_width=12000,max_height=12000'],
        ], [
            'photos.required' => 'Pilih foto dulu.',
            'photos.max' => 'Maksimal '.self::MAX_FILES.' foto sekali unggah.',
            'photos.*.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photos.*.mimetypes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photos.*.max' => 'Ukuran tiap foto maksimal 25 MB.',
            'photos.*.dimensions' => 'Ada foto yang terlalu kecil atau terlalu besar.',
            'photos.*.uploaded' => 'Foto gagal diunggah. Coba foto yang lebih kecil.',
        ]);

        try {
            $count = $photos->addMany($album, $request->user(), $request->file('photos'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['photos' => $e->getMessage()]);
        }

        return back()->with('success', $count === 1 ? 'Foto ditambahkan.' : "{$count} foto ditambahkan.");
    }

    public function destroy(Request $request, Photo $photo, PhotoService $photos): RedirectResponse
    {
        $photo->loadMissing('album');
        $this->authorize('update', $photo->album);
        Album::where('cover_photo_id', $photo->id)->update(['cover_photo_id' => null]);
        $photos->delete($photo);

        return back()->with('success', 'Foto dihapus.');
    }

    /** Caption, place, date, favourite and an optional link to a calendar event. */
    public function details(Request $request, Photo $photo): RedirectResponse
    {
        $photo->loadMissing('album');
        $this->authorize('update', $photo->album);
        $data = $request->validate([
            'caption' => ['nullable', ...E2E::text(200)],
            'location' => ['nullable', ...E2E::text(120)],
            'taken_on' => ['nullable', 'date_format:Y-m-d', 'after:1950-01-01', 'before_or_equal:today'],
            'is_favorite' => ['sometimes', 'boolean'],
            'event_id' => ['nullable', 'integer', 'min:1'],
        ]);
        // Only an event this person can already see may be linked.
        if (! empty($data['event_id']) && ! Event::query()->visibleTo($request->user())->whereKey($data['event_id'])->exists()) {
            return back()->withErrors(['event_id' => 'Acara tidak ditemukan.']);
        }
        $photo->update($data);

        return back();
    }

    /** Anyone who may not open the album gets 404, exactly as for a photo that does not exist. */
    public function file(Request $request, Photo $photo, string $variant): Response
    {
        abort_unless(in_array($variant, ['thumb', 'full'], true), 404);
        $photo->loadMissing('album');
        $this->authorize('view', $photo->album);

        $path = $variant === 'thumb' ? $photo->thumb_path : $photo->path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, E2E::fileHeaders($path, 'image/jpeg', 'private, max-age=3600'));
    }
}
