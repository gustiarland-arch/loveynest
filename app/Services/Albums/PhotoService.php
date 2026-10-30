<?php

namespace App\Services\Albums;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use App\Support\E2E;
use App\Services\Activity\ActivityRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Photos live on the private disk and are only served through an authorized
 * route. Every upload is decoded and re-encoded into two plain JPEGs (display
 * and thumbnail), so the stored files carry no metadata (EXIF, GPS) and no
 * hidden payload, and the names say nothing about the original.
 */
class PhotoService
{
    public const DISPLAY_EDGE = 2560;

    public const THUMB_SIZE = 480;

    public const MAX_PER_ALBUM = 300;

    public function __construct(private ActivityRecorder $activity) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return int how many photos were added
     */
    public function addMany(Album $album, User $user, array $files, ?array $sealed = null): int
    {
        if ($album->photos()->count() + count($files) > self::MAX_PER_ALBUM) {
            throw new InvalidArgumentException('Satu album maksimal '.self::MAX_PER_ALBUM.' foto.');
        }

        $added = 0;
        foreach (array_values($files) as $i => $file) {
            $this->add($album, $user, $file, $sealed[$i] ?? null);
            $added++;
        }

        if ($added && $album->isShared()) {
            $this->activity->record($album->couple_space_id, $user, 'photo.added', 'album', $album->id, $album->title.' · '.$added.' foto');
        }
        $album->touch();

        return $added;
    }

    public function delete(Photo $photo): void
    {
        Storage::disk('local')->delete([$photo->path, $photo->thumb_path]);
        $photo->delete();
    }

    public function deleteAlbum(Album $album): void
    {
        $paths = $album->photos()->get(['path', 'thumb_path'])->flatMap(fn ($p) => [$p->path, $p->thumb_path])->all();
        $album->delete();
        Storage::disk('local')->delete($paths);
    }

    /**
     * End-to-end encryption: the browser already made the display picture and the thumbnail and
     * locked both, so they are stored exactly as they arrived (the server cannot look inside).
     *
     * @return array{path: string, thumb_path: string, width: int, height: int}
     */
    public function storeSealed(UploadedFile $full, UploadedFile $thumb, int $width, int $height, string $dir = 'photos'): array
    {
        $name = Str::random(40);
        $disk = Storage::disk('local');
        $target = "{$dir}/{$name}.lne";
        $thumbTarget = "{$dir}/{$name}-t.lne";
        $ok = $disk->putFileAs($dir, $full, basename($target)) && $disk->putFileAs($dir, $thumb, basename($thumbTarget));
        if (! $ok) {
            $disk->delete([$target, $thumbTarget]);
            throw new InvalidArgumentException('Foto tidak bisa disimpan di server. Hubungi pemilik server.');
        }

        return ['path' => $target, 'thumb_path' => $thumbTarget, 'width' => max(1, min($width, 20000)), 'height' => max(1, min($height, 20000))];
    }

    /**
     * Validation for a locked photo upload (display picture + thumbnail + size), keyed by field prefix.
     *
     * @return array<string, list<mixed>>
     */
    public static function sealedRules(string $field, bool $required = true, bool $many = false): array
    {
        $each = $many ? '.*' : '';
        $presence = $required ? 'required' : 'nullable';

        return [
            "{$field}{$each}" => [$presence, 'file', 'max:30720', E2E::fileRule()],
            "{$field}_thumb{$each}" => ["required_with:{$field}", 'file', 'max:4096', E2E::fileRule()],
            "{$field}_width{$each}" => ["required_with:{$field}", 'integer', 'min:1', 'max:20000'],
            "{$field}_height{$each}" => ["required_with:{$field}", 'integer', 'min:1', 'max:20000'],
        ];
    }

    private function add(Album $album, User $user, UploadedFile $file, ?array $sealed = null): Photo
    {
        $stored = $sealed ? $this->storeSealed($file, $sealed['thumb'], $sealed['width'], $sealed['height']) : $this->store($file);

        return $album->photos()->create([
            'uploaded_by' => $user->id,
            'path' => $stored['path'],
            'thumb_path' => $stored['thumb_path'],
            'width' => $stored['width'],
            'height' => $stored['height'],
        ]);
    }

    /**
     * Decodes, re-encodes and stores one upload as a display JPEG and a
     * square thumbnail on the private disk. Also used for chat photos.
     *
     * @return array{path: string, thumb_path: string, width: int, height: int}
     */
    public function store(UploadedFile $file, string $dir = 'photos'): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw new InvalidArgumentException('Foto gagal diunggah. Coba lagi.');
        }

        $name = Str::random(40);
        $disk = Storage::disk('local');
        $target = "{$dir}/{$name}.jpg";
        $thumbTarget = "{$dir}/{$name}-t.jpg";

        // Without GD, or when the picture is too large for the memory we may use,
        // keep the original instead of failing. The type was already checked.
        $info = @getimagesize($path);
        if ($info === false) {
            throw new InvalidArgumentException('Berkas bukan gambar yang valid.');
        }
        [$width, $height] = $info;

        if (! $this->canDecode($width, $height)) {
            return $this->keepOriginal($file, $disk, $target, $width, $height);
        }

        try {
            $bytes = file_get_contents($path);
            $source = $bytes === false ? false : @imagecreatefromstring($bytes);
            unset($bytes);
            if ($source === false) {
                return $this->keepOriginal($file, $disk, $target, $width, $height);
            }

            $source = $this->upright($source, $path);
            $width = imagesx($source);
            $height = imagesy($source);

            $scale = min(1, self::DISPLAY_EDGE / max($width, $height));
            $display = $this->resample($source, $width, $height, 0, 0, (int) round($width * $scale), (int) round($height * $scale));

            $side = min($width, $height);
            $thumb = $this->resample($source, $side, $side, intdiv($width - $side, 2), intdiv($height - $side, 2), self::THUMB_SIZE, self::THUMB_SIZE);
            unset($source);

            $ok = $disk->put($target, $this->jpeg($display, 82)) && $disk->put($thumbTarget, $this->jpeg($thumb, 80));
        } catch (\Throwable) {
            $ok = false;
        }

        if (! $ok) {
            $disk->delete([$target, $thumbTarget]);

            return $this->keepOriginal($file, $disk, $target, $width, $height);
        }

        return ['path' => $target, 'thumb_path' => $thumbTarget, 'width' => imagesx($display), 'height' => imagesy($display)];
    }

    /** GD present and enough memory for the picture, its rotated copy and the two outputs. */
    private function canDecode(int $width, int $height): bool
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            return false;
        }

        $limit = $this->memoryLimit();
        if ($limit !== -1 && $limit < 1024 * 1024 * 1024) {
            @ini_set('memory_limit', '1024M');
            $limit = $this->memoryLimit();
        }
        if ($limit === -1) {
            return true;
        }

        $need = $width * $height * 9 + 64 * 1024 * 1024;

        return $need + memory_get_usage(true) < $limit;
    }

    private function memoryLimit(): int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** @return array{path: string, thumb_path: string, width: int, height: int} */
    private function keepOriginal(UploadedFile $file, $disk, string $target, int $width, int $height): array
    {
        $stream = fopen($file->getRealPath(), 'rb');
        $saved = $stream !== false && $disk->put($target, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (! $saved) {
            throw new InvalidArgumentException('Foto tidak bisa disimpan di server. Hubungi pemilik server.');
        }

        // The browser scales it down for the thumbnail.
        return ['path' => $target, 'thumb_path' => $target, 'width' => max(1, $width), 'height' => max(1, $height)];
    }

    private function resample(\GdImage $source, int $srcW, int $srcH, int $srcX, int $srcY, int $dstW, int $dstH): \GdImage
    {
        $target = imagecreatetruecolor(max(1, $dstW), max(1, $dstH));
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, max(1, $dstW), max(1, $dstH), $srcW, $srcH);

        return $target;
    }

    private function jpeg(\GdImage $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }

    /** Honour the camera orientation before the metadata is thrown away. */
    private function upright(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }
}
