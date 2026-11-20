<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Employee photos: the uploaded original is kept, and a small square copy is what pages show.
 * Uploads can be up to 4 MB, while the largest avatar is 80px, so pages load the small copy.
 */
class EmployeePhoto
{
    /** Side of the square copy: sharp at 3x for the largest avatar (80px). */
    public const SIZE = 256;

    private const DIR = 'employees/photos';

    /** Store a new upload and make its small copy straight away. */
    public static function store(UploadedFile $file): string
    {
        $path = $file->store(self::DIR, 'public');
        self::thumbnail($path);

        return $path;
    }

    /** Remove the original and its small copy. */
    public static function delete(?string $path): void
    {
        if (! $path) {
            return;
        }
        Storage::disk('public')->delete([$path, self::thumbPath($path)]);
    }

    /**
     * The file to send for a photo: its small copy, made on first use for photos uploaded before copies existed.
     * Falls back to the original when the image can't be read.
     */
    public static function servePath(string $path): string
    {
        return self::thumbnail($path) ?? $path;
    }

    public static function thumbPath(string $path): string
    {
        return self::DIR.'/thumbs/'.pathinfo($path, PATHINFO_FILENAME).'.'.(function_exists('imagewebp') ? 'webp' : 'jpg');
    }

    /** Make the small copy if it isn't there yet; returns its path, or null if it couldn't be made. */
    public static function thumbnail(string $path): ?string
    {
        $disk = Storage::disk('public');
        $thumb = self::thumbPath($path);
        if ($disk->exists($thumb)) {
            return $thumb;
        }
        if (! extension_loaded('gd') || ! $disk->exists($path)) {
            return null;
        }

        $source = @imagecreatefromstring($disk->get($path));
        if (! $source) {
            return null;
        }
        $source = self::orient($source, $disk->path($path));

        // Centre square crop, like the round avatars (object-cover) show it.
        $w = imagesx($source);
        $h = imagesy($source);
        $side = min($w, $h);
        $size = min(self::SIZE, $side);
        $out = imagecreatetruecolor($size, $size);
        $webp = function_exists('imagewebp');
        if ($webp) {
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        } else {
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        }
        imagecopyresampled($out, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);

        ob_start();
        $webp ? imagewebp($out, null, 82) : imagejpeg($out, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($out);
        imagedestroy($source);

        if (! $bytes) {
            return null;
        }
        $disk->put($thumb, $bytes);

        return $thumb;
    }

    /** Phone photos are often stored sideways with an EXIF rotation flag; apply it so the copy is upright. */
    private static function orient(\GdImage $image, string $file): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }
        $orientation = (@exif_read_data($file) ?: [])['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated) {
                imagedestroy($image);

                return $rotated;
            }
        }

        return $image;
    }
}
