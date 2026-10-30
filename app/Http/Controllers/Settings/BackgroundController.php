<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Albums\PhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Each account picks its own backdrop: the default photo, a built-in
 * gradient, or a photo of its own. The veil over it can be made lighter or
 * stronger so text stays readable on busy pictures.
 */
class BackgroundController extends Controller
{
    public const PRESETS = ['photo', 'blossom', 'lavender', 'peach', 'mint', 'night', 'custom'];

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'preset' => ['required', Rule::in(self::PRESETS)],
            'veil' => ['required', 'integer', 'between:-30,40'],
        ]);
        if ($data['preset'] === 'custom' && ! $user->background_path) {
            return back()->withErrors(['background' => 'Unggah foto dulu.']);
        }

        $user->forceFill(['background_preset' => $data['preset'], 'background_veil' => $data['veil']])->save();

        return back()->with('success', 'Latar diperbarui.');
    }

    public function upload(Request $request, PhotoService $photos): RedirectResponse
    {
        $request->validate([
            'background' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:25600', 'dimensions:min_width=320,min_height=320,max_width=12000,max_height=12000'],
        ], [
            'background.required' => 'Pilih foto dulu.',
            'background.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'background.mimetypes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'background.max' => 'Ukuran foto maksimal 25 MB.',
            'background.dimensions' => 'Foto terlalu kecil atau terlalu besar.',
            'background.uploaded' => 'Foto gagal diunggah. Coba foto yang lebih kecil.',
        ]);

        $user = $request->user();
        try {
            $stored = $photos->store($request->file('background'), 'backgrounds');
        } catch (InvalidArgumentException) {
            return back()->withErrors(['background' => 'Berkas bukan gambar yang valid.']);
        }

        $this->deleteFiles($user);
        $user->forceFill([
            'background_path' => $stored['path'],
            'background_preset' => 'custom',
            // Photos are usually busier than the default picture; start with a stronger veil.
            'background_veil' => $user->background_veil ?? 10,
        ])->save();

        return back()->with('success', 'Foto latar dipasang.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->deleteFiles($user);
        $user->forceFill(['background_path' => null, 'background_preset' => $user->background_preset === 'custom' ? null : $user->background_preset])->save();

        return back()->with('success', 'Foto latar dihapus.');
    }

    /** A backdrop photo is only ever shown to its owner. */
    public function show(Request $request, User $user, string $variant = 'full'): Response
    {
        abort_unless($request->user()->is($user) && $user->background_path && in_array($variant, ['full', 'thumb'], true), 404);
        $path = $variant === 'thumb' ? self::thumbPath($user->background_path) : $user->background_path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    /** What the layout needs to paint the backdrop. */
    public static function forUser(User $user): array
    {
        $preset = $user->background_preset ?? 'photo';
        $url = $user->background_path ? route('backgrounds.show', $user).'?v='.substr(md5($user->background_path), 0, 8) : null;

        return [
            'preset' => $preset === 'custom' && ! $url ? 'photo' : $preset,
            'url' => $url,
            'thumb' => $url ? route('backgrounds.show', [$user, 'thumb']).'?v='.substr(md5($user->background_path), 0, 8) : null,
            'veil' => (int) ($user->background_veil ?? 0),
        ];
    }

    private static function thumbPath(string $path): string
    {
        return preg_replace('/\.jpg$/', '-t.jpg', $path);
    }

    private function deleteFiles(User $user): void
    {
        if ($user->background_path) {
            Storage::disk('local')->delete([$user->background_path, self::thumbPath($user->background_path)]);
        }
    }
}
