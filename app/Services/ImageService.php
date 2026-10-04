<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded images on the public disk and generates responsive
 * variants (WebP + JPEG fallback) using GD. No external dependency needed.
 *
 * Stored structure: uploads/YYYY/MM/<name>.<ext> plus
 *                   uploads/YYYY/MM/<name>-{small|medium|large}.webp
 */
class ImageService
{
    /** @var array<string, int> size name => max width */
    public const SIZES = [
        'small' => 400,
        'medium' => 800,
        'large' => 1200,
    ];

    public const DISK = 'public';

    public function store(UploadedFile $file, string $folder = 'uploads'): string
    {
        $disk = Storage::disk(self::DISK);
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }
        $dir = trim($folder, '/').'/'.now()->format('Y/m');
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';
        $base = Str::limit($base, 60, '').'-'.Str::lower(Str::random(6));
        $path = "{$dir}/{$base}.{$ext}";

        $disk->makeDirectory($dir);
        $file->storeAs($dir, "{$base}.{$ext}", self::DISK);

        $this->generateVariants($path);

        return $path;
    }

    public function generateVariants(string $path): void
    {
        if (! function_exists('imagecreatefromstring')) {
            return;
        }
        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($path)) {
            return;
        }
        $raw = $disk->get($path);
        $src = @imagecreatefromstring($raw);
        if (! $src) {
            return;
        }
        $width = imagesx($src);
        $height = imagesy($src);
        $info = pathinfo($path);
        $dir = $info['dirname'];
        $name = $info['filename'];

        // Normalise the original: cap at 1600px wide to keep storage sane.
        if ($width > 1600) {
            $resized = $this->resize($src, $width, $height, 1600);
            $this->save($resized, $info['extension'] ?? 'jpg', $disk->path($path));
            imagedestroy($src);
            $src = $resized;
            $width = imagesx($src);
            $height = imagesy($src);
        }

        foreach (self::SIZES as $size => $max) {
            $img = $width > $max ? $this->resize($src, $width, $height, $max) : $src;
            $target = $disk->path("{$dir}/{$name}-{$size}.webp");
            if (function_exists('imagewebp')) {
                imagewebp($img, $target, 82);
            } else {
                imagejpeg($img, $disk->path("{$dir}/{$name}-{$size}.jpg"), 82);
            }
            if ($img !== $src) {
                imagedestroy($img);
            }
        }
        imagedestroy($src);
    }

    private function resize(\GdImage $src, int $w, int $h, int $maxWidth): \GdImage
    {
        $newW = $maxWidth;
        $newH = (int) round($h * ($maxWidth / $w));
        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        return $dst;
    }

    private function save(\GdImage $img, string $ext, string $target): void
    {
        match (strtolower($ext)) {
            'png' => imagepng($img, $target, 8),
            'gif' => imagegif($img, $target),
            'webp' => imagewebp($img, $target, 85),
            default => imagejpeg($img, $target, 85),
        };
    }

    public function delete(?string $path): void
    {
        if (blank($path)) {
            return;
        }
        $disk = Storage::disk(self::DISK);
        $info = pathinfo($path);
        $disk->delete($path);
        foreach (array_keys(self::SIZES) as $size) {
            $disk->delete("{$info['dirname']}/{$info['filename']}-{$size}.webp");
            $disk->delete("{$info['dirname']}/{$info['filename']}-{$size}.jpg");
        }
    }

    /**
     * Public URL for an image variant. Falls back to the original when the
     * variant does not exist (e.g. remote URLs or imported legacy images).
     */
    public static function url(?string $path, string $size = 'large'): ?string
    {
        if (blank($path)) {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }
        $info = pathinfo($path);
        $variant = "{$info['dirname']}/{$info['filename']}-{$size}.webp";
        static $cache = [];
        if (! array_key_exists($variant, $cache)) {
            $cache[$variant] = Storage::disk(self::DISK)->exists($variant);
        }

        return asset('storage/'.($cache[$variant] ? $variant : $path));
    }

    public static function srcset(?string $path): ?string
    {
        if (blank($path) || Str::startsWith($path, ['http://', 'https://', '//'])) {
            return null;
        }
        $parts = [];
        foreach (self::SIZES as $size => $width) {
            $url = static::url($path, $size);
            if ($url) {
                $parts[] = "{$url} {$width}w";
            }
        }

        return $parts ? implode(', ', array_unique($parts)) : null;
    }
}
