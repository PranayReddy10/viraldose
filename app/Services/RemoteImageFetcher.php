<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copies a post's hot-linked featured image into local storage (with WebP
 * variants) so it keeps working when the source site blocks or removes it.
 * Handles JPEG, PNG, GIF, WebP and AVIF responses from news CDNs.
 */
class RemoteImageFetcher
{
    public function __construct(private ImageService $images) {}

    public static function pendingQuery()
    {
        return Post::withTrashed()->whereNotNull('image')
            ->where(fn ($q) => $q->where('image', 'like', 'http://%')->orWhere('image', 'like', 'https://%'));
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function fetch(Post $post): array
    {
        $url = (string) $post->image;
        if (! ImageService::isRemoteUrl($url)) {
            return ['ok' => true, 'message' => 'already local'];
        }
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        // Ask for classic formats first; many news CDNs would otherwise answer with AVIF.
        $error = null;
        $body = $this->download($url, $host, 'image/jpeg,image/png,image/webp,image/gif,image/*;q=0.8,*/*;q=0.5', $error);
        if ($body === null) {
            return $this->fail($post, (string) $error);
        }
        $ext = static::sniff($body);
        if ($ext === 'avif') {
            // Convert when GD can, otherwise ask the CDN again for a JPEG.
            if (function_exists('imagecreatefromavif') && ($img = @imagecreatefromavif('data://application/octet-stream;base64,'.base64_encode($body)))) {
                ob_start();
                imagejpeg($img, null, 85);
                $body = (string) ob_get_clean();
                imagedestroy($img);
                $ext = 'jpg';
            } else {
                $body = $this->download($url, $host, 'image/jpeg,image/png;q=0.9', $error);
                $ext = $body !== null ? static::sniff($body) : null;
                if ($ext === 'avif') {
                    return $this->fail($post, $host.' only serves AVIF, which this server cannot decode');
                }
            }
        }
        if ($body === null || $ext === null || strlen($body) < 600) {
            return $this->fail($post, 'Response from '.$host.' is not an image');
        }

        $date = $post->published_at ?? $post->created_at ?? Carbon::now();
        $dir = 'uploads/imported/'.$date->format('Y/m');
        $base = Str::limit(Str::slug(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME) ?: $post->slug), 50, '') ?: 'image';
        $path = "{$dir}/{$base}-".Str::lower(Str::random(6)).".{$ext}";

        Storage::disk(ImageService::LOCAL_DISK)->put($path, $body, ['visibility' => 'public']);
        $this->images->generateVariants($path);

        Post::withoutTimestamps(fn () => $post->forceFill(['image' => $path, 'image_fetch_error' => null])->saveQuietly());

        return ['ok' => true, 'message' => "saved {$path} (".round(strlen($body) / 1024).' KB)'];
    }

    private function download(string $url, string $host, string $accept, ?string &$error = null): ?string
    {
        try {
            $response = Http::timeout(25)->connectTimeout(10)->withOptions(['allow_redirects' => ['max' => 5]])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept' => $accept,
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Referer' => 'https://'.$host.'/',
                    'Sec-Fetch-Dest' => 'image',
                    'Sec-Fetch-Mode' => 'no-cors',
                ])->get($url);
        } catch (\Throwable $e) {
            $error = 'Request failed: '.Str::limit($e->getMessage(), 180);

            return null;
        }
        if (! $response->successful()) {
            $error = 'HTTP '.$response->status().' from '.$host;

            return null;
        }

        return $response->body();
    }

    /**
     * Detects the image type from the file signature (works for WebP/AVIF even when
     * PHP's getimagesize does not know them). Returns an extension or null.
     */
    public static function sniff(string $body): ?string
    {
        $head = substr($body, 0, 16);

        return match (true) {
            str_starts_with($head, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($head, "\x89PNG") => 'png',
            str_starts_with($head, 'GIF8') => 'gif',
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'webp',
            substr($head, 4, 4) === 'ftyp' && in_array(substr($head, 8, 4), ['avif', 'avis'], true) => 'avif',
            default => null, // includes SVG, HTML error pages, etc.
        };
    }

    private function fail(Post $post, string $message): array
    {
        Post::withoutTimestamps(fn () => $post->forceFill(['image_fetch_error' => Str::limit($message, 255, '')])->saveQuietly());

        return ['ok' => false, 'message' => $message];
    }

    /**
     * Process up to $limit posts, optionally stopping after $seconds.
     *
     * @return array{done: int, failed: int, remaining: int, lines: array<int, string>}
     */
    public function run(int $limit = 10, bool $retry = false, ?int $seconds = null): array
    {
        $deadline = $seconds ? microtime(true) + $seconds : null;
        $posts = static::pendingQuery()->when(! $retry, fn ($q) => $q->whereNull('image_fetch_error'))->orderBy('id')->limit($limit)->get();
        $done = $failed = 0;
        $lines = [];
        foreach ($posts as $post) {
            if ($deadline && microtime(true) > $deadline) {
                $lines[] = 'Time budget reached – the rest continues in the background.';
                break;
            }
            $result = $this->fetch($post);
            $result['ok'] ? $done++ : $failed++;
            $lines[] = ($result['ok'] ? '✔ ' : '✖ ').Str::limit($post->title, 60).' – '.$result['message'];
        }
        $remaining = static::pendingQuery()->whereNull('image_fetch_error')->count();

        return compact('done', 'failed', 'remaining', 'lines');
    }
}
