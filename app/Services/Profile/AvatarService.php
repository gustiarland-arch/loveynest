<?php

namespace App\Services\Profile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Profile photos live on the private disk and are only served through an
 * authorized route. Every upload is decoded and re-encoded, so the stored file
 * is always a plain JPEG square with no metadata (EXIF, GPS) and no hidden payload.
 */
class AvatarService
{
    public const SIZE = 384;

    public function store(User $user, UploadedFile $file): void
    {
        $bytes = file_get_contents($file->getRealPath());
        $source = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new InvalidArgumentException('Berkas bukan gambar yang valid.');
        }

        $source = $this->upright($source, $file->getRealPath());
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled($square, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($square, null, 85);
        $jpeg = (string) ob_get_clean();

        $path = 'avatars/'.Str::random(40).'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        $this->delete($user);
        $user->forceFill(['avatar_path' => $path])->save();
    }

    public function delete(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }
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
