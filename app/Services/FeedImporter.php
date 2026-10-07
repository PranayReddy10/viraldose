<?php

namespace App\Services;

use App\Models\Post;
use App\Models\RssFeed;
use App\Models\Tag;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Imports articles from RSS 2.0 / Atom feeds into posts (as drafts unless the
 * feed is set to auto-publish). Items are de-duplicated by GUID per feed.
 */
class FeedImporter
{
    /** Feed items with less text than this are treated as teasers and the source page is fetched. */
    public const THIN_CONTENT_CHARS = 600;

    public function __construct(private HtmlSanitizer $sanitizer, private SearchEnginePinger $pinger, private ArticleExtractor $extractor) {}

    /**
     * @param  int|null  $seconds  Time budget for fetching full articles (web requests); the rest is refilled later.
     */
    public function import(RssFeed $feed, ?int $seconds = null): int
    {
        $feed = $feed->fresh() ?? $feed;
        $deadline = $seconds ? microtime(true) + $seconds : null;
        try {
            $items = $this->fetch($feed->url);
        } catch (\Throwable $e) {
            $feed->update(['last_error' => Str::limit($e->getMessage(), 500), 'last_fetched_at' => now()]);

            return 0;
        }

        $imported = 0;
        foreach (array_slice($items, 0, $feed->max_items) as $item) {
            if ($item['guid'] === '' || $item['title'] === '') {
                continue;
            }
            $exists = Post::withTrashed()->where('rss_feed_id', $feed->id)->where('feed_guid', $item['guid'])->exists()
                || ($item['link'] && Post::withTrashed()->where('source_url', $item['link'])->exists());
            if ($exists) {
                continue;
            }
            $publishedAt = $item['date'] ? min($item['date'], now()) : now();
            $image = $feed->import_images ? $item['image'] : null;
            $content = $item['content'] ?: '<p>'.e($item['summary']).'</p>';
            $fetchedAt = null;
            if ($feed->fetch_full_content && $item['link'] && $this->extractor->textLength($content) < self::THIN_CONTENT_CHARS && (! $deadline || microtime(true) < $deadline)) {
                $full = $this->fullArticle($item['link'], $content);
                $fetchedAt = now();
                if ($full) {
                    $content = $full['html'];
                    $image = $image ?: ($feed->import_images ? $full['image'] : null);
                }
            }
            $content = $this->sanitizer->clean($this->extractor->tidy($content, $item['link'] ?: $feed->url, $image));
            $post = Post::create([
                'user_id' => $feed->user_id,
                'category_id' => $feed->category_id,
                'language' => $feed->language ?: 'en',
                'title' => Str::limit($item['title'], 200, ''),
                'excerpt' => Str::limit($item['summary'], 500, ''),
                'content' => $content,
                'image' => $image,
                'content_fetched_at' => $fetchedAt,
                'status' => $feed->auto_publish ? Post::STATUS_PUBLISHED : Post::STATUS_DRAFT,
                'published_at' => $publishedAt,
                'source_name' => $feed->name,
                'source_url' => $item['link'],
                'rss_feed_id' => $feed->id,
                'created_via' => 'feed',
                'feed_guid' => Str::limit($item['guid'], 500, ''),
            ]);
            if ($item['tags']) {
                $post->tags()->sync(Tag::syncFromString(implode(',', $item['tags'])));
            }
            if ($post->isPublished()) {
                try {
                    $this->pinger->notify($post);
                } catch (\Throwable) {
                }
            }
            $imported++;
        }

        $feed->update([
            'last_fetched_at' => now(),
            'last_error' => null,
            'imported_count' => $feed->imported_count + $imported,
        ]);

        return $imported;
    }

    /**
     * Download the source page and return its article when it is longer than what the feed gave us.
     *
     * @return array{html: string, image: ?string}|null
     */
    public function fullArticle(string $url, string $current = ''): ?array
    {
        try {
            $article = $this->extractor->fromUrl($url);
        } catch (\Throwable) {
            return null;
        }
        if (! $article || $article['text_length'] <= $this->extractor->textLength($current)) {
            return null;
        }

        return ['html' => $article['html'], 'image' => $article['image']];
    }

    /**
     * Fetch full articles for already-imported posts that only hold a teaser.
     *
     * @return array{done: int, failed: int, remaining: int}
     */
    public function refill(?RssFeed $feed = null, int $limit = 10, bool $retry = false, ?int $seconds = null): array
    {
        $deadline = $seconds ? microtime(true) + $seconds : null;
        $query = $this->thinPosts($feed, $retry);
        $done = $failed = 0;
        foreach ($query->orderByDesc('id')->limit($limit)->get() as $post) {
            if ($deadline && microtime(true) > $deadline) {
                break;
            }
            $done += $this->pullContent($post) ? 1 : 0;
            $failed += $post->content_fetched_at && $this->extractor->textLength($post->content) < self::THIN_CONTENT_CHARS ? 1 : 0;
        }

        return ['done' => $done, 'failed' => $failed, 'remaining' => $this->thinPosts($feed, false)->count()];
    }

    /**
     * Replace a post's content with the full article from its source URL. Returns true when content changed.
     */
    public function pullContent(Post $post): bool
    {
        $post->forceFill(['content_fetched_at' => now()])->saveQuietly();
        if (! $post->source_url) {
            return false;
        }
        $full = $this->fullArticle($post->source_url, (string) $post->content);
        if (! $full) {
            return false;
        }
        $image = $post->image ?: $full['image'];
        $post->forceFill([
            'content' => $this->sanitizer->clean($this->extractor->tidy($full['html'], $post->source_url, $image)),
            'image' => $image,
        ])->save();

        return true;
    }

    private function thinPosts(?RssFeed $feed, bool $retry)
    {
        return Post::query()->whereNotNull('source_url')
            ->when($feed, fn ($q) => $q->where('rss_feed_id', $feed->id), fn ($q) => $q->whereNotNull('rss_feed_id'))
            ->when(! $retry, fn ($q) => $q->whereNull('content_fetched_at'))
            ->whereRaw('LENGTH(content) < ?', [self::THIN_CONTENT_CHARS * 3]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetch(string $url): array
    {
        $response = Http::timeout(25)->withHeaders(['User-Agent' => 'ViralDoseBot/1.0 (+'.config('app.url').')'])->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException("Feed returned HTTP {$response->status()}");
        }

        return $this->parse($response->body());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $doc) {
            throw new \RuntimeException('Feed is not valid XML.');
        }

        $items = [];
        if (isset($doc->channel->item)) {           // RSS 2.0
            foreach ($doc->channel->item as $item) {
                $items[] = $this->rssItem($item);
            }
        } elseif (isset($doc->entry)) {             // Atom
            foreach ($doc->entry as $entry) {
                $items[] = $this->atomEntry($entry);
            }
        } elseif (isset($doc->item)) {              // RSS 1.0
            foreach ($doc->item as $item) {
                $items[] = $this->rssItem($item);
            }
        }

        return $items;
    }

    private function rssItem(\SimpleXMLElement $item): array
    {
        $content = $item->children('http://purl.org/rss/1.0/modules/content/');
        $media = $item->children('http://search.yahoo.com/mrss/');
        $dc = $item->children('http://purl.org/dc/elements/1.1/');
        $html = trim((string) ($content->encoded ?? '')) ?: trim((string) $item->description);
        $link = trim((string) $item->link);
        $image = null;
        if (isset($media->content) && (string) $media->content->attributes()->url) {
            $image = (string) $media->content->attributes()->url;
        } elseif (isset($media->thumbnail) && (string) $media->thumbnail->attributes()->url) {
            $image = (string) $media->thumbnail->attributes()->url;
        } elseif (isset($item->enclosure) && Str::startsWith((string) $item->enclosure->attributes()->type, 'image/')) {
            $image = (string) $item->enclosure->attributes()->url;
        }
        $tags = [];
        foreach ($item->category as $cat) {
            $tags[] = trim((string) $cat);
        }

        return $this->normalize(
            title: (string) $item->title,
            link: $link,
            guid: trim((string) $item->guid) ?: $link,
            summary: (string) $item->description,
            content: $html,
            image: $image,
            date: (string) ($item->pubDate ?: ($dc->date ?? '')),
            tags: $tags,
        );
    }

    private function atomEntry(\SimpleXMLElement $entry): array
    {
        $link = '';
        foreach ($entry->link as $l) {
            $rel = (string) $l->attributes()->rel;
            if ($rel === '' || $rel === 'alternate') {
                $link = (string) $l->attributes()->href;
                break;
            }
        }
        $media = $entry->children('http://search.yahoo.com/mrss/');
        $image = isset($media->thumbnail) ? (string) $media->thumbnail->attributes()->url : null;
        $tags = [];
        foreach ($entry->category as $cat) {
            $tags[] = trim((string) $cat->attributes()->term);
        }

        return $this->normalize(
            title: (string) $entry->title,
            link: $link,
            guid: trim((string) $entry->id) ?: $link,
            summary: (string) $entry->summary,
            content: (string) $entry->content,
            image: $image,
            date: (string) ($entry->published ?: $entry->updated),
            tags: $tags,
        );
    }

    private function normalize(string $title, string $link, string $guid, string $summary, string $content, ?string $image, string $date, array $tags): array
    {
        if (! $image && preg_match('/<img[^>]+src="([^"]+)"/i', $content, $m)) {
            $image = $m[1];
        }
        $summaryText = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($summary ?: $content), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        try {
            $parsed = $date ? Carbon::parse($date) : null;
        } catch (\Throwable) {
            $parsed = null;
        }

        return [
            'title' => trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'link' => $link,
            'guid' => $guid,
            'summary' => Str::limit($summaryText, 500, ''),
            'content' => $content,
            'image' => $image && Str::startsWith($image, ['http://', 'https://']) ? $image : null,
            'date' => $parsed,
            'tags' => array_values(array_filter(array_unique($tags))),
        ];
    }
}
