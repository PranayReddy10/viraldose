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
        $file = "{$dir}/post-{$post->id}-".substr(md5($post->title.$post->image.$post->updated_at), 0, 8).'.jpg';
        $disk = Storage::disk(ImageService::LOCAL_DISK);
        if (! $force && $disk->exists($file)) {
            return $file;
        }

        $w = self::WIDTH;
        $h = self::HEIGHT;
        $canvas = imagecreatetruecolor($w, $h);
        $bg = $this->loadImage($post);
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
        imagefilledrectangle($canvas, $pad, $by - 44, $pad + $bw, $by + 2, $badgeColor);
        imagettftext($canvas, 24, 0, $pad + 18, $by - 10, $white, $bold, $badge);

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

        ob_start();
        imagejpeg($canvas, null, 88);
        $disk->put($file, (string) ob_get_clean(), ['visibility' => 'public']);
        imagedestroy($canvas);

        return $file;
    }

    public static function url(string $file): string
    {
        return ImageService::publicUrl($file);
    }

    private function loadImage(Post $post): ?\GdImage
    {
        if (! $post->image) {
            return null;
        }
        try {
            if (ImageService::isRemoteUrl($post->image)) {
                $raw = Http::timeout(15)->get($post->image)->body();
            } else {
                [$disk, $path] = ImageService::resolve($post->image);
                $raw = Storage::disk($disk)->get($path);
            }
            $img = $raw ? @imagecreatefromstring($raw) : false;

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
