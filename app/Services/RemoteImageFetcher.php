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
        try {
            $response = Http::timeout(25)->connectTimeout(10)->withOptions(['allow_redirects' => ['max' => 5]])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Referer' => 'https://'.$host.'/',
                ])->get($url);
        } catch (\Throwable $e) {
            return $this->fail($post, 'Request failed: '.Str::limit($e->getMessage(), 180));
        }
        if (! $response->successful()) {
            return $this->fail($post, 'HTTP '.$response->status().' from '.$host);
        }
        $body = $response->body();
        $info = @getimagesizefromstring($body);
        if (! $info || strlen($body) < 2000) {
            return $this->fail($post, 'Response from '.$host.' is not an image');
        }
        $ext = match ($info[2]) {
            IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp', default => 'jpg',
        };
        if ($info[2] === IMAGETYPE_AVIF ?? false) {
            return $this->fail($post, 'AVIF images are not supported by this server');
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
