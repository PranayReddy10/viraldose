<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded media on the configured disk (local "public" or DigitalOcean
 * Spaces) and generates responsive WebP variants with GD.
 *
 * Stored references look like:
 *   uploads/2026/01/name.jpg            -> local public disk
 *   spaces://uploads/2026/01/name.jpg   -> DigitalOcean Spaces
 * so the storage driver can be switched later without breaking old media.
 */
class ImageService
{
    /** @var array<string, int> size name => max width */
    public const SIZES = [
        'small' => 400,
        'medium' => 800,
        'large' => 1200,
    ];

    public const LOCAL_DISK = 'public';

    public const SPACES_DISK = 'spaces';

    /**
     * Disk new uploads go to, from Settings → Storage.
     */
    public static function uploadDisk(): string
    {
        return setting('storage_driver') === self::SPACES_DISK && setting('spaces_bucket') ? self::SPACES_DISK : self::LOCAL_DISK;
    }

    /**
     * Split a stored reference into [disk, relative path].
     *
     * @return array{0: string, 1: string}
     */
    public static function resolve(string $reference): array
    {
        if (str_starts_with($reference, 'spaces://')) {
            return [self::SPACES_DISK, substr($reference, 9)];
        }

        return [self::LOCAL_DISK, ltrim($reference, '/')];
    }

    public static function isRemoteUrl(?string $path): bool
    {
        return $path !== null && Str::startsWith($path, ['http://', 'https://', '//']);
    }

    public function store(UploadedFile $file, string $folder = 'uploads', bool $variants = true): string
    {
        $diskName = static::uploadDisk();
        $disk = Storage::disk($diskName);
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $dir = trim($folder, '/').'/'.now()->format('Y/m');
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $base = Str::limit($base, 60, '').'-'.Str::lower(Str::random(6));
        $relative = "{$dir}/{$base}.{$ext}";

        $disk->putFileAs($dir, $file, "{$base}.{$ext}", ['visibility' => 'public']);

        $reference = $diskName === self::SPACES_DISK ? 'spaces://'.$relative : $relative;

        if ($variants && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
            $this->generateVariants($reference);
        }

        return $reference;
    }

    public function generateVariants(string $reference): void
    {
        if (! function_exists('imagecreatefromstring')) {
            return;
        }
        [$diskName, $path] = static::resolve($reference);
        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            return;
        }
        $raw = (string) $disk->get($path);
        $src = @imagecreatefromstring($raw);
        if (! $src && function_exists('imagecreatefromwebp') && str_starts_with($raw, 'RIFF')) {
            $src = @imagecreatefromwebp('data://application/octet-stream;base64,'.base64_encode($raw));
        }
        if (! $src) {
            return; // unsupported by this server's GD: the original file is served as is
        }
        $width = imagesx($src);
        $height = imagesy($src);
        $info = pathinfo($path);
        $dir = $info['dirname'];
        $name = $info['filename'];

        // Cap the original at 1600px wide to keep storage sane.
        if ($width > 1600) {
            $resized = $this->resize($src, $width, $height, 1600);
            $disk->put($path, $this->encode($resized, $info['extension'] ?? 'jpg'), ['visibility' => 'public']);
            imagedestroy($src);
            $src = $resized;
            $width = imagesx($src);
            $height = imagesy($src);
        }

        foreach (self::SIZES as $size => $max) {
            $img = $width > $max ? $this->resize($src, $width, $height, $max) : $src;
            $disk->put("{$dir}/{$name}-{$size}.webp", $this->encode($img, 'webp'), ['visibility' => 'public']);
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

    private function encode(\GdImage $img, string $ext): string
    {
        ob_start();
        match (strtolower($ext)) {
            'png' => imagepng($img, null, 8),
            'gif' => imagegif($img),
            'webp' => function_exists('imagewebp') ? imagewebp($img, null, 82) : imagejpeg($img, null, 82),
            default => imagejpeg($img, null, 85),
        };

        return (string) ob_get_clean();
    }

    public function delete(?string $reference): void
    {
        if (blank($reference) || static::isRemoteUrl($reference)) {
            return;
        }
        [$diskName, $path] = static::resolve($reference);
        $disk = Storage::disk($diskName);
        $info = pathinfo($path);
        $disk->delete($path);
        foreach (array_keys(self::SIZES) as $size) {
            $disk->delete("{$info['dirname']}/{$info['filename']}-{$size}.webp");
        }
    }

    /**
     * Public URL of a stored reference (original file).
     */
    public static function publicUrl(?string $reference): ?string
    {
        if (blank($reference)) {
            return null;
        }
        if (static::isRemoteUrl($reference)) {
            return $reference;
        }
        [$diskName, $path] = static::resolve($reference);
        if ($diskName === self::LOCAL_DISK) {
            return asset('storage/'.$path);
        }

        return Storage::disk($diskName)->url($path);
    }

    /**
     * URL for an image variant; falls back to the original when the variant is missing.
     */
    public static function url(?string $reference, string $size = 'large'): ?string
    {
        if (blank($reference) || static::isRemoteUrl($reference)) {
            return $reference;
        }
        [$diskName, $path] = static::resolve($reference);
        $info = pathinfo($path);
        $variant = "{$info['dirname']}/{$info['filename']}-{$size}.webp";

        if ($diskName === self::LOCAL_DISK) {
            static $cache = [];
            if (! array_key_exists($variant, $cache)) {
                $cache[$variant] = Storage::disk($diskName)->exists($variant);
            }

            return asset('storage/'.($cache[$variant] ? $variant : $path));
        }

        // Remote disk: variants are always generated at upload time, so skip the round-trip.
        return Storage::disk($diskName)->url($variant);
    }

    public static function srcset(?string $reference): ?string
    {
        if (blank($reference) || static::isRemoteUrl($reference)) {
            return null;
        }
        $parts = [];
        foreach (self::SIZES as $size => $width) {
            if ($url = static::url($reference, $size)) {
                $parts[] = "{$url} {$width}w";
            }
        }

        return $parts ? implode(', ', array_unique($parts)) : null;
    }
}
