<?php

namespace App\Services;

use App\Models\IndexingLog;
use App\Models\Post;
use App\Services\Google\IndexingApi;
use Illuminate\Support\Facades\Log;

/**
 * Tells search engines about new/updated/removed articles:
 * Google Indexing API + IndexNow (Bing, Yandex, …). Every call is logged.
 */
class SearchEnginePinger
{
    public function __construct(private IndexingApi $google, private IndexNow $indexNow) {}

    /**
     * @return array<string, string> provider => 'ok' | error message
     */
    public function notify(Post $post, string $type = 'URL_UPDATED', bool $force = false): array
    {
        $results = [];
        if ($post->noindex && $type !== 'URL_DELETED') {
            return ['skipped' => 'Post is marked noindex.'];
        }
        $url = $post->url();

        if ($this->google->isReady() && ($force || setting('google_auto_index', 1))) {
            try {
                $this->google->publish($url, $type);
                IndexingLog::record('google_indexing', $type, $url, true, 'Accepted', $post->id);
                $results['google'] = 'ok';
            } catch (\Throwable $e) {
                IndexingLog::record('google_indexing', $type, $url, false, $e->getMessage(), $post->id);
                $results['google'] = $e->getMessage();
                Log::warning('Google Indexing API failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        if (IndexNow::enabled() && $type !== 'URL_DELETED') {
            try {
                $code = $this->indexNow->submit([$url]);
                IndexingLog::record('indexnow', $type, $url, true, "HTTP {$code}", $post->id);
                $results['indexnow'] = 'ok';
            } catch (\Throwable $e) {
                IndexingLog::record('indexnow', $type, $url, false, $e->getMessage(), $post->id);
                $results['indexnow'] = $e->getMessage();
            }
        }

        if ($results) {
            Post::withoutTimestamps(fn () => $post->forceFill(['indexing_requested_at' => now()])->saveQuietly());
        }

        return $results;
    }

    public function notifyIfJustPublished(Post $post, bool $wasPublished): void
    {
        if ($post->isPublished() && ! $wasPublished) {
            $this->notify($post);
        } elseif ($post->isPublished() && $post->indexing_requested_at && $post->updated_at->gt($post->indexing_requested_at->addMinutes(30))) {
            // Significant edit of an already-indexed article: tell Google it changed.
            $this->notify($post);
        }
    }
}
