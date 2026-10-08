<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders a 1080×1350 (Instagram portrait) JPEG "news card": featured image,
 * dark gradient, category badge, headline, site name. Uses GD + bundled
 * DejaVu fonts so it works on any host. Output is stored on the public disk
 * so Instagram can fetch it by URL.
 */
class ShareCardGenerator
{
    public const WIDTH = 1080;

    public const HEIGHT = 1350;

    public function generate(Post $post, bool $force = false): string
    {
        $dir = 'uploads/social';
        $file = "{$dir}/post-{$post->id}-".substr(md5('v2'.$post->title.$post->image.$post->updated_at), 0, 8).'.jpg';
        $diskName = ImageService::uploadDisk();
        $disk = Storage::disk($diskName);
        $reference = $diskName === ImageService::SPACES_DISK ? 'spaces://'.$file : $file;
        if (! $force && $this->exists($disk, $file)) {
            return $reference;
        }

        $w = self::WIDTH;
        $h = self::HEIGHT;
        $canvas = imagecreatetruecolor($w, $h);
        // Headline cards made by the content agent already contain the title as text; using them as the
        // background would print the headline twice (cropped). Treat them like "no image".
        $bg = static::isTextCard($post->image, $post) ? null : $this->loadImage($post->image);
        $plain = ! $bg;
        if ($bg) {
            $this->coverCopy($canvas, $bg, $w, $h);
            imagedestroy($bg);
        } else {
            [$r, $g, $b] = sscanf(ltrim($post->category?->color ?: '#dc2626', '#'), '%02x%02x%02x');
            for ($y = 0; $y < $h; $y++) {
                $t = $y / $h;
                imageline($canvas, 0, $y, $w, $y, imagecolorallocate($canvas, (int) ($r * (1 - $t * .7)), (int) ($g * (1 - $t * .7)), (int) ($b * (1 - $t * .7))));
            }
        }

        // Bottom gradient for legibility
        $gradientTop = (int) ($h * 0.42);
        for ($y = $gradientTop; $y < $h; $y++) {
            $alpha = (int) (127 - 127 * min(1, ($y - $gradientTop) / ($h - $gradientTop)) * 0.92);
            imageline($canvas, 0, $y, $w, $y, imagecolorallocatealpha($canvas, 8, 8, 12, $alpha));
        }

        $bold = resource_path('fonts/DejaVuSans-Bold.ttf');
        $regular = resource_path('fonts/DejaVuSans.ttf');
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $muted = imagecolorallocate($canvas, 220, 220, 225);
        $pad = 64;

        // Headline (wrapped, auto-sized)
        $title = Str::limit(trim($post->title), 140, '…');
        $size = mb_strlen($title) > 90 ? 50 : (mb_strlen($title) > 60 ? 58 : 68);
        $lines = $this->wrap($title, $bold, $size, $w - 2 * $pad);
        while (count($lines) > 5 && $size > 40) {
            $size -= 4;
            $lines = $this->wrap($title, $bold, $size, $w - 2 * $pad);
        }
        $lineHeight = (int) ($size * 1.25);
        $footerY = $h - $pad;
        // Without a photo, centre the badge + headline block vertically instead of hugging the footer.
        $lift = $plain ? max(0, (int) (($h - 120 - count($lines) * $lineHeight) / 2) - 140) : 0;
        $footerY -= $lift;
        $y = $footerY - 70 - count($lines) * $lineHeight;
        foreach ($lines as $line) {
            $y += $lineHeight;
            imagettftext($canvas, $size, 0, $pad, $y, $white, $bold, $line);
        }

        // Category badge above the headline
        $badge = Str::upper($post->category?->name ?: 'NEWS');
        [$br, $bgc, $bb] = sscanf(ltrim($post->category?->color ?: '#dc2626', '#'), '%02x%02x%02x');
        $badgeColor = imagecolorallocate($canvas, $br, $bgc, $bb);
        $bbox = imagettfbbox(24, 0, $bold, $badge);
        $bw = abs($bbox[2] - $bbox[0]) + 36;
        $by = $footerY - 70 - count($lines) * $lineHeight - 30;
        // On the plain category-colour background a same-colour badge disappears: invert it.
        imagefilledrectangle($canvas, $pad, $by - 44, $pad + $bw, $by + 2, $plain ? $white : $badgeColor);
        imagettftext($canvas, 24, 0, $pad + 18, $by - 10, $plain ? $badgeColor : $white, $bold, $badge);

        $footerY += $lift;

        // Footer: logo (falls back to the site name) + handle
        $logoFile = public_path('images/logo-white.png');
        if (is_file($logoFile) && ($logo = @imagecreatefrompng($logoFile))) {
            $lw = imagesx($logo);
            $lh = imagesy($logo);
            $targetH = 64;
            $targetW = (int) round($lw * $targetH / $lh);
            imagealphablending($canvas, true);
            imagecopyresampled($canvas, $logo, $pad, $footerY - $targetH + 8, 0, 0, $targetW, $targetH, $lw, $lh);
            imagedestroy($logo);
        } else {
            imagettftext($canvas, 30, 0, $pad, $footerY, $white, $bold, Str::upper(site_name()));
        }
        $handle = '@'.ltrim((string) setting('instagram_username', 'viraldose_news'), '@');
        $hb = imagettfbbox(24, 0, $regular, $handle);
        imagettftext($canvas, 24, 0, $w - $pad - abs($hb[2] - $hb[0]), $footerY, $muted, $regular, $handle);

        // Top-right "swipe/read" strip
        imagefilledrectangle($canvas, 0, 0, $w, 10, $badgeColor);

        $this->write($disk, $file, $canvas);

        return $reference;
    }

    /**
     * Instagram-feed JPEG (4:5, 1080×1350) from any stored image: blurred cover
     * background with the photo fitted inside. Used for photo reels, whose
     * originals may be WebP/PNG or 9:16 – both rejected by the Graph API.
     */
    /** Featured images generated by the content agent are stored as "…-card.jpg". */
    public static function isTextCard(?string $image, ?Post $post = null): bool
    {
        if (blank($image)) {
            return false;
        }
        if (preg_match('/-card\.(jpe?g|png|webp)$/i', $image)) {
            return true;
        }
        if (preg_match('/-(photo|ai)\.(jpe?g|png|webp)$/i', $image)) {
            return false;
        }

        // Agent images uploaded before the "-card" suffix existed: named after the post slug.
        return $post?->created_via === 'agent' && $post->slug
            && str_starts_with(basename($image), Str::limit($post->slug, 30, ''));
    }

    public function photoCard(string $image, string $key, bool $force = false): string
    {
        $file = 'uploads/social/'.$key.'-'.substr(md5($image), 0, 8).'.jpg';
        $diskName = ImageService::uploadDisk();
        $disk = Storage::disk($diskName);
        $reference = $diskName === ImageService::SPACES_DISK ? 'spaces://'.$file : $file;
        if (! $force && $this->exists($disk, $file)) {
            return $reference;
        }
        $src = $this->loadImage($image);
        if (! $src) {
            throw new \RuntimeException('The image could not be read for the Instagram card.');
        }
        $w = self::WIDTH;
        $h = self::HEIGHT;
        $canvas = imagecreatetruecolor($w, $h);
        $this->coverCopy($canvas, $src, $w, $h);
        for ($i = 0; $i < 25; $i++) {
            imagefilter($canvas, IMG_FILTER_GAUSSIAN_BLUR);
        }
        imagefilter($canvas, IMG_FILTER_BRIGHTNESS, -40);
        // Fit the photo inside the canvas, keeping its ratio.
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min($w / $sw, $h / $sh);
        $dw = (int) round($sw * $scale);
        $dh = (int) round($sh * $scale);
        imagecopyresampled($canvas, $src, (int) (($w - $dw) / 2), (int) (($h - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);
        $this->write($disk, $file, $canvas);

        return $reference;
    }

    /** Raw JPEG bytes of a generated card (works for local and Spaces references). */
    public function bytes(string $reference): string
    {
        [$disk, $path] = ImageService::resolve($reference);

        return (string) Storage::disk($disk)->get($path);
    }

    public static function url(string $file): string
    {
        return ImageService::publicUrl($file);
    }

    private function exists($disk, string $file): bool
    {
        try {
            return $disk->exists($file);
        } catch (\Throwable) {
            return false;
        }
    }

    private function write($disk, string $file, \GdImage $canvas): void
    {
        ob_start();
        imagejpeg($canvas, null, 88);
        $bytes = (string) ob_get_clean();
        imagedestroy($canvas);
        if (! $disk->put($file, $bytes, ['visibility' => 'public'])) {
            throw new \RuntimeException('The card could not be saved to storage.');
        }
    }

    private function loadImage(?string $image): ?\GdImage
    {
        if (! $image) {
            return null;
        }
        try {
            if (ImageService::isRemoteUrl($image)) {
                $host = (string) parse_url($image, PHP_URL_HOST);
                $raw = Http::timeout(15)->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept' => 'image/jpeg,image/png,image/webp,image/*;q=0.8,*/*;q=0.5',
                    'Referer' => 'https://'.$host.'/',
                ])->get($image)->body();
            } else {
                [$disk, $path] = ImageService::resolve($image);
                $raw = Storage::disk($disk)->get($path);
            }
            if (! $raw) {
                return null;
            }
            $img = @imagecreatefromstring($raw);
            if (! $img && function_exists('imagecreatefromwebp') && str_starts_with($raw, 'RIFF')) {
                $img = @imagecreatefromwebp('data://application/octet-stream;base64,'.base64_encode($raw));
            }
            if (! $img && function_exists('imagecreatefromavif') && str_contains(substr($raw, 0, 16), 'ftyp')) {
                $img = @imagecreatefromavif('data://application/octet-stream;base64,'.base64_encode($raw));
            }

            return $img ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function coverCopy(\GdImage $dst, \GdImage $src, int $w, int $h): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cw = (int) ($w / $scale);
        $ch = (int) ($h / $scale);
        $sx = (int) (($sw - $cw) / 2);
        $sy = (int) (($sh - $ch) / 2);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $w, $h, $cw, $ch);
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $try = trim($current.' '.$word);
            $box = imagettfbbox($size, 0, $font, $try);
            if (abs($box[2] - $box[0]) > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $try;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }
}
