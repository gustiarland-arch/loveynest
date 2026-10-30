<?php

namespace App\Http\Controllers\Berdua;

use App\Enums\Scope;
use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\PhotoboothSession;
use App\Models\PhotoboothShot;
use App\Services\Albums\PhotoService;
use App\Services\Photobooth\PhotoboothService;
use App\Support\E2E;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Square shots taken in turns (or on one phone), saved as one picture in the shared "Photobooth" album. */
class PhotoboothController extends Controller
{
    private const IMAGE_RULES = ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:15360', 'dimensions:min_width=200,min_height=200,max_width=12000,max_height=12000'];

    private const IMAGE_MESSAGES = [
        'mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
        'mimetypes' => 'Foto harus berformat JPG, PNG, atau WebP.',
        'max' => 'Ukuran foto maksimal 15 MB.',
        'dimensions' => 'Foto terlalu kecil atau terlalu besar.',
        'uploaded' => 'Foto gagal diunggah. Coba lagi.',
    ];

    public function index(Request $request, PhotoboothService $booth): Response
    {
        $user = $request->user();
        $spaceId = $user->activeSpaceId();
        $partner = $user->partner();
        $current = $booth->current($spaceId);
        // A picture saved in the last day can be opened in the editor again while its shots are kept.
        $editing = false;
        if (! $current && $request->integer('edit')) {
            $current = PhotoboothSession::where('couple_space_id', $spaceId)->whereNotNull('saved_at')->where('saved_at', '>', now()->subHours(PhotoboothService::KEEP_HOURS))->find($request->integer('edit'));
            $editing = (bool) $current;
        }

        $history = PhotoboothSession::where('couple_space_id', $spaceId)->whereNotNull('saved_at')->whereHas('photo')
            ->latest('saved_at')->limit(30)->get(['id', 'photo_id', 'layout', 'theme', 'saved_at'])
            ->map(fn (PhotoboothSession $s) => [
                'id' => $s->id,
                'layout' => $s->layout,
                'theme' => $s->theme,
                'editable' => $s->saved_at->gt(now()->subHours(PhotoboothService::KEEP_HOURS)),
                'thumb' => "/photos/{$s->photo_id}/thumb",
                'full' => "/photos/{$s->photo_id}/full",
                'date' => $s->saved_at->setTimezone($user->timezone)->toDateString(),
                'fresh' => $s->saved_at->gt(now()->subMinutes(15)),
            ]);

        return Inertia::render('Berdua/Photobooth', [
            'current' => $current ? $this->present($current, $user->id, $current->secondId($user)) + ['editing' => $editing] : null,
            'history' => $history,
            'hasPartner' => (bool) $partner,
            'names' => ['me' => $user->firstName(), 'partner' => $partner?->firstName()],
            'albumId' => Album::inSpaceOf($user, Scope::Shared)->where('title', PhotoboothService::ALBUM_TITLE)->value('id'),
        ]);
    }

    public function store(Request $request, PhotoboothService $booth): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'mode' => ['required', Rule::in(PhotoboothSession::MODES)],
            'layout' => ['required', Rule::in(array_keys(PhotoboothSession::LAYOUTS))],
            'theme' => ['required', Rule::in(PhotoboothSession::THEMES)],
        ]);
        if ($data['mode'] === 'turns' && ! $user->partner()) {
            return back()->withErrors(['mode' => 'Mode gantian butuh pasangan yang sudah bergabung.']);
        }
        if ($booth->current($user->activeSpaceId())) {
            return back()->withErrors(['mode' => 'Selesaikan atau batalkan photobooth yang sedang berjalan dulu.']);
        }

        $session = new PhotoboothSession($data);
        $session->couple_space_id = $user->activeSpaceId();
        $session->created_by = $user->id;
        $session->save();

        return back();
    }

    public function shot(Request $request, int $session, PhotoboothService $booth): RedirectResponse
    {
        $row = $this->find($request, $session);
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0', 'max:'.($row->shotCount() - 1)],
            'photo' => E2E::enabled() ? ['required', 'file', 'max:15360', E2E::fileRule()] : self::IMAGE_RULES,
        ], $this->messages('photo'));

        try {
            $booth->addShot($row, $request->user(), (int) $data['position'], $request->file('photo'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }

        return back();
    }

    public function strip(Request $request, int $session, PhotoboothService $booth): RedirectResponse
    {
        $row = $this->find($request, $session);
        $request->validate([
            ...(E2E::enabled() ? PhotoService::sealedRules('strip') : ['strip' => self::IMAGE_RULES]),
            'replace' => ['sometimes', 'boolean'],
        ], $this->messages('strip'));
        $sealed = E2E::enabled() ? ['thumb' => $request->file('strip_thumb'), 'width' => $request->integer('strip_width'), 'height' => $request->integer('strip_height')] : null;

        try {
            $saved = $booth->saveStrip($row, $request->user(), $request->file('strip'), $request->boolean('replace'), $sealed);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['strip' => $e->getMessage()]);
        }

        return back()->with('success', $saved ? 'Tersimpan di album Photobooth.' : 'Pasanganmu sudah menyimpan fotonya.');
    }

    /** Either of you may cancel a round or remove it from the list; a saved strip stays in the album. */
    public function destroy(Request $request, int $session, PhotoboothService $booth): RedirectResponse
    {
        $booth->delete($this->find($request, $session));

        return back();
    }

    /** Anyone outside the couple gets 404, exactly as for a shot that does not exist. */
    public function file(Request $request, int $shot): HttpResponse
    {
        $row = PhotoboothShot::whereKey($shot)
            ->whereHas('session', fn ($q) => $q->where('couple_space_id', $request->user()->activeSpaceId()))
            ->firstOrFail();
        abort_unless(Storage::disk('local')->exists($row->path), 404);

        return Storage::disk('local')->response($row->path, null, E2E::fileHeaders($row->path, 'image/jpeg', 'private, max-age=3600'));
    }

    private function find(Request $request, int $session): PhotoboothSession
    {
        return PhotoboothSession::where('couple_space_id', $request->user()->activeSpaceId())->findOrFail($session);
    }

    /** @return array<string, string> */
    private function messages(string $field): array
    {
        return collect(self::IMAGE_MESSAGES)->mapWithKeys(fn ($m, $rule) => ["{$field}.{$rule}" => $m])->all()
            + ["{$field}.required" => 'Ambil foto dulu.'];
    }

    /** @return array<string, mixed> */
    private function present(PhotoboothSession $s, int $me, ?int $secondId): array
    {
        $shots = $s->shots()->get()->keyBy('position');
        $slots = collect(range(0, $s->shotCount() - 1))->map(function (int $p) use ($s, $shots, $me, $secondId) {
            $shot = $shots->get($p);
            $owner = $s->ownerOf($p, $secondId);

            return [
                'position' => $p,
                // The shot id changes nothing on a retake, so the time keeps the browser from showing the old picture.
                'url' => $shot ? "/photobooth/foto/{$shot->id}?v={$shot->updated_at?->timestamp}" : null,
                'mine' => $owner === $me,
            ];
        });
        $next = $slots->first(fn ($slot) => $slot['url'] === null);

        return [
            'id' => $s->id,
            'mode' => $s->mode,
            'layout' => $s->layout,
            'theme' => $s->theme,
            'count' => $s->shotCount(),
            'mine' => $s->created_by === $me,
            'slots' => $slots->values(),
            'next' => $next['position'] ?? null,
            'myTurn' => $next !== null && $next['mine'],
            'done' => $s->completed_at !== null,
            'date' => ($s->completed_at ?? $s->created_at)->toIso8601String(),
            'saved' => $s->saved_at !== null,
        ];
    }
}
