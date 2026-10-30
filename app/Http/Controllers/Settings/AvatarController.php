<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Profile\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class AvatarController extends Controller
{
    public function store(Request $request, AvatarService $avatars): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240', 'dimensions:min_width=64,min_height=64,max_width=8000,max_height=8000'],
        ], [
            'avatar.required' => 'Pilih foto dulu.',
            'avatar.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'avatar.mimetypes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'avatar.max' => 'Ukuran foto maksimal 10 MB.',
            'avatar.dimensions' => 'Foto terlalu kecil atau terlalu besar.',
            'avatar.uploaded' => 'Foto gagal diunggah. Coba foto yang lebih kecil.',
        ]);

        try {
            $avatars->store($request->user(), $request->file('avatar'));
        } catch (InvalidArgumentException) {
            return back()->withErrors(['avatar' => 'Berkas bukan gambar yang valid.']);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['avatar' => 'Server belum bisa menyimpan foto ini. Penyebab: '.Str::limit($e->getMessage(), 140).' (catatan lengkap ada di storage/logs/laravel.log)']);
        }

        return back()->with('success', 'Foto profil diperbarui.');
    }

    public const FRAMES = ['aurora', 'emas', 'manis', 'daun', 'abstrak', 'neon', 'kristal', 'awan', 'grafiti', 'malam'];

    public function frame(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'frame' => ['nullable', 'string', 'in:'.implode(',', self::FRAMES)],
        ], ['frame.in' => 'Bingkai tidak dikenal.']);

        $request->user()->forceFill(['avatar_frame' => $data['frame'] ?? null])->save();

        return back()->with('success', 'Bingkai profil diperbarui.');
    }

    public const CARDS = ['senja', 'malam', 'sakura', 'laut', 'hutan', 'emas', 'lavender', 'marmer', 'galaksi', 'awan'];

    /** The picture behind the name card on the profile page. */
    public function card(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'card' => ['nullable', 'string', 'in:'.implode(',', self::CARDS)],
        ], ['card.in' => 'Latar kartu tidak dikenal.']);

        if (! Schema::hasColumn('users', 'profile_card')) {
            return back()->withErrors(['card' => 'Latar kartu butuh pembaruan database: jalankan php artisan migrate --force di server.']);
        }

        $request->user()->forceFill(['profile_card' => $data['card'] ?? null])->save();

        return back()->with('success', 'Latar kartu diperbarui.');
    }

    public function destroy(Request $request, AvatarService $avatars): RedirectResponse
    {
        $avatars->delete($request->user());

        return back()->with('success', 'Foto profil dihapus.');
    }

    /** Only the owner and their active partner may see a photo; everyone else gets 404. */
    public function show(Request $request, User $user): Response
    {
        $viewer = $request->user();
        $allowed = $viewer->is($user) || $viewer->partner()?->is($user);
        abort_unless($allowed && $user->avatar_path && Storage::disk('local')->exists($user->avatar_path), 404);

        return Storage::disk('local')->response($user->avatar_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
