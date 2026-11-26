<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class AvatarImage
{
    /**
     * Where the grayscale twin of an avatar lives.
     */
    public static function grayscalePath(string $path): string
    {
        return 'avatars/grey/'.basename($path);
    }

    /**
     * Write a grayscale copy of the stored avatar next to the original.
     */
    public static function generate(string $path): void
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return;
        }

        $image = @imagecreatefromstring($disk->get($path));

        if (! $image) {
            return;
        }

        imagefilter($image, IMG_FILTER_GRAYSCALE);

        ob_start();

        match ($extension) {
            'png' => imagepng($image),
            'gif' => imagegif($image),
            'webp' => imagewebp($image, null, 90),
            default => imagejpeg($image, null, 90),
        };

        $contents = ob_get_clean();
        imagedestroy($image);

        if ($contents !== false) {
            $disk->put(static::grayscalePath($path), $contents);
        }
    }

    public static function delete(string $path): void
    {
        Storage::disk('public')->delete(static::grayscalePath($path));
    }
}
