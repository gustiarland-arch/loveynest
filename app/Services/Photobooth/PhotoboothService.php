<?php

namespace App\Services\Photobooth;

use App\Enums\Scope;
use App\Models\Album;
use App\Models\Photo;
use App\Models\PhotoboothSession;
use App\Models\PhotoboothShot;
use App\Models\User;
use App\Notifications\PhotoboothUpdate;
use App\Services\Activity\ActivityRecorder;
use App\Services\Albums\PhotoService;
use App\Support\E2E;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Shots are square JPEGs on the private disk. The browser draws the finished
 * strip (fonts and frame live there) and the server only re-encodes it into the
 * shared "Photobooth" album, like any other upload.
 */
class PhotoboothService
{
    public const SHOT_EDGE = 1080;

    public const ALBUM_TITLE = 'Photobooth';

    public function __construct(private PhotoService $photos, private ActivityRecorder $activity) {}

    /** The round still being shot or waiting for its strip, if any. */
    public function current(int $spaceId): ?PhotoboothSession
    {
        return PhotoboothSession::where('couple_space_id', $spaceId)->whereNull('saved_at')->latest('id')->first();
    }

    public function addShot(PhotoboothSession $session, User $user, int $position, UploadedFile $file): void
    {
        $partner = $user->partner();
        $second = $session->secondId($user);
        $count = $session->shotCount();
        if ($position < 0 || $position >= $count) {
            throw new InvalidArgumentException('Nomor foto tidak dikenal.');
        }
        if ($session->ownerOf($position, $second) !== $user->id) {
            throw new InvalidArgumentException('Belum giliranmu untuk foto ini.');
        }

        $path = $this->storeSquare($file);
        $isNew = false;

        try {
            DB::transaction(function () use ($session, $user, $position, $path, $count, &$isNew, &$old) {
                $locked = PhotoboothSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
                if ($locked->completed_at) {
                    throw new InvalidArgumentException('Semua foto sudah lengkap.');
                }
                $taken = $locked->shots()->pluck('user_id', 'position');
                $next = collect(range(0, $count - 1))->first(fn ($p) => ! $taken->has($p));

                if ($taken->has($position)) {
                    // A retake replaces your own shot while the round is open.
                    if ($taken[$position] !== $user->id) {
                        throw new InvalidArgumentException('Foto ini milik pasanganmu.');
                    }
                    $shot = $locked->shots()->where('position', $position)->first();
                    $old = $shot->path;
                    $shot->update(['path' => $path]);
                } elseif ($position === $next) {
                    $locked->shots()->create(['position' => $position, 'user_id' => $user->id, 'path' => $path]);
                    $isNew = true;
                    if ($taken->count() + 1 >= $count) {
                        $locked->forceFill(['completed_at' => now()])->save();
                    }
                } else {
                    throw new InvalidArgumentException('Foto sebelumnya belum diambil.');
                }
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        if (! empty($old)) {
            Storage::disk('local')->delete($old);
        }

        $session->refresh();
        $next = $position + 1;
        if ($isNew && ! $session->completed_at && $partner && $session->ownerOf($next, $second) === $partner->id) {
            $this->notify($partner, $user, 'turn');
        }
    }

    /**
     * Saves the picture the browser drew into the shared album. Returns false when the other phone saved it first.
     * With $replace, a picture saved earlier (and still editable) is swapped for the new version instead.
     */
    public function saveStrip(PhotoboothSession $session, User $user, UploadedFile $file, bool $replace = false, ?array $sealed = null): bool
    {
        if (! $session->completed_at) {
            throw new InvalidArgumentException('Foto belum lengkap.');
        }

        $stored = $sealed ? $this->photos->storeSealed($file, $sealed['thumb'], $sealed['width'], $sealed['height']) : $this->photos->store($file);
        $oldFiles = [];
        $saved = DB::transaction(function () use ($session, $user, $stored, $replace, &$oldFiles) {
            $locked = PhotoboothSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->saved_at && ! $replace) {
                return false;
            }

            if ($locked->saved_at && $locked->photo_id && ($photo = Photo::find($locked->photo_id))) {
                $oldFiles = array_unique([$photo->path, $photo->thumb_path]);
                $photo->update(['path' => $stored['path'], 'thumb_path' => $stored['thumb_path'], 'width' => $stored['width'], 'height' => $stored['height']]);
                $photo->album?->touch();
                $locked->touch();

                return 'replaced';
            }

            $album = $this->album($user);
            $photo = $album->photos()->create([
                'uploaded_by' => $user->id,
                'path' => $stored['path'],
                'thumb_path' => $stored['thumb_path'],
                'width' => $stored['width'],
                'height' => $stored['height'],
                'caption' => 'Photobooth',
                'taken_on' => CarbonImmutable::now($user->timezone)->toDateString(),
            ]);
            $album->touch();
            $locked->forceFill(['photo_id' => $photo->id, 'saved_at' => now()])->save();

            return $album;
        });

        if ($saved === false) {
            Storage::disk('local')->delete(array_unique([$stored['path'], $stored['thumb_path']]));

            return false;
        }
        if ($saved === 'replaced') {
            Storage::disk('local')->delete($oldFiles);

            return true;
        }

        // The shots stay for a day so the picture can be edited again; `photobooth:prune` removes them afterwards.
        $this->activity->record($session->couple_space_id, $user, 'photobooth.done', 'album', $saved->id, self::ALBUM_TITLE);
        if ($partner = $user->partner()) {
            $this->notify($partner, $user, 'done');
        }

        return true;
    }

    /** Hours a saved round keeps its original shots, for editing again. */
    public const KEEP_HOURS = 24;

    /** Removes the loose shots of rounds saved a day ago, and rounds abandoned for three days. Saved pictures stay. */
    public function prune(): int
    {
        $old = PhotoboothSession::where(fn ($q) => $q->where('saved_at', '<', now()->subHours(self::KEEP_HOURS))->orWhere(fn ($q2) => $q2->whereNull('saved_at')->where('updated_at', '<', now()->subDays(3))))->get();
        foreach ($old as $session) {
            $this->delete($session);
        }

        return $old->count();
    }

    public function delete(PhotoboothSession $session): void
    {
        $this->deleteShotFiles($session);
        $session->delete();
    }

    private function deleteShotFiles(PhotoboothSession $session): void
    {
        $paths = PhotoboothShot::where('photobooth_session_id', $session->id)->pluck('path')->all();
        Storage::disk('local')->delete($paths);
        PhotoboothShot::where('photobooth_session_id', $session->id)->delete();
    }

    private function album(User $user): Album
    {
        $album = Album::inSpaceOf($user, Scope::Shared)->where('title', self::ALBUM_TITLE)->first();
        if ($album) {
            return $album;
        }

        $album = new Album(['title' => self::ALBUM_TITLE, 'description' => 'Strip dari photobooth berdua.']);
        $album->assignSpace($user, Scope::Shared)->save();

        return $album;
    }

    private function notify(User $partner, User $actor, string $event): void
    {
        try {
            $partner->notify(new PhotoboothUpdate($actor->firstName(), $event));
        } catch (Throwable $e) {
            Log::warning('notification.failed', ['type' => 'photobooth', 'error' => $e::class]);
        }
    }

    /** Centre-crops to a square and re-encodes, so no metadata survives. */
    private function storeSquare(UploadedFile $file): string
    {
        if (E2E::enabled()) {
            // Locked in the browser (already square, no metadata): kept exactly as it arrived.
            $target = 'photobooth/'.Str::random(40).'.lne';
            if (! Storage::disk('local')->putFileAs('photobooth', $file, basename($target))) {
                throw new InvalidArgumentException('Foto tidak bisa disimpan di server. Hubungi pemilik server.');
            }

            return $target;
        }

        $source = $file->getRealPath();
        $info = $source === false ? false : @getimagesize($source);
        if ($info === false) {
            throw new InvalidArgumentException('Berkas bukan gambar yang valid.');
        }

        $disk = Storage::disk('local');
        $target = 'photobooth/'.Str::random(40).'.jpg';

        $image = function_exists('imagecreatefromstring') && $info[0] * $info[1] <= 40_000_000
            ? @imagecreatefromstring((string) file_get_contents($source))
            : false;
        if ($image === false) {
            // Without GD the browser's canvas output (already square, no metadata) is kept.
            if (! $disk->put($target, (string) file_get_contents($source))) {
                throw new InvalidArgumentException('Foto tidak bisa disimpan di server. Hubungi pemilik server.');
            }

            return $target;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $edge = min(self::SHOT_EDGE, $side);
        $square = imagecreatetruecolor($edge, $edge);
        imagecopyresampled($square, $image, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $edge, $edge, $side, $side);

        ob_start();
        imagejpeg($square, null, 85);
        $bytes = (string) ob_get_clean();
        if (! $disk->put($target, $bytes)) {
            throw new InvalidArgumentException('Foto tidak bisa disimpan di server. Hubungi pemilik server.');
        }

        return $target;
    }
}
