<?php

namespace App\Models;

use App\Services\EmbedRenderer;
use App\Services\ImageService;
use App\Support\PostUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];

    public const TYPES = [
        'article' => 'Article',
        'video' => 'Video',
        'gallery' => 'Gallery',
        'audio' => 'Audio',
    ];

    protected $fillable = [
        'user_id', 'category_id', 'title', 'slug', 'excerpt', 'content', 'image', 'image_alt', 'image_caption',
        'status', 'published_at', 'is_featured', 'is_breaking', 'is_slider', 'is_recommended', 'allow_comments',
        'meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'noindex', 'reading_time',
        'source_name', 'source_url', 'legacy_id', 'language', 'post_type', 'video_url', 'audio_url', 'rss_feed_id', 'feed_guid',
        'index_status', 'index_coverage', 'index_checked_at', 'last_crawled_at', 'indexing_requested_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_breaking' => 'boolean',
            'is_slider' => 'boolean',
            'is_recommended' => 'boolean',
            'allow_comments' => 'boolean',
            'noindex' => 'boolean',
            'views' => 'integer',
            'reading_time' => 'integer',
            'index_checked_at' => 'datetime',
            'last_crawled_at' => 'datetime',
            'indexing_requested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            $post->slug = static::uniqueSlug($post->slug ?: $post->title, $post->id);
            $post->reading_time = static::estimateReadingTime($post->content);
            if (blank($post->excerpt)) {
                $post->excerpt = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $post->content))), 200);
            }
            if ($post->status === self::STATUS_PUBLISHED && ! $post->published_at) {
                $post->published_at = now();
            }
        });
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget('home.data');
        Cache::forget('sidebar.data');
        Cache::forget('breaking.posts');
    }

    public static function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($value), 200, '') ?: 'post';
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public static function estimateReadingTime(?string $html): int
    {
        $words = str_word_count(strip_tags((string) $html));

        return max(1, (int) ceil($words / 200));
    }

    // Relationships

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->where('status', Comment::STATUS_APPROVED)->whereNull('parent_id')
            ->with(['replies' => fn ($q) => $q->where('status', Comment::STATUS_APPROVED)->oldest()])
            ->latest();
    }

    public function viewLogs(): HasMany
    {
        return $this->hasMany(PostView::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(PostFile::class)->orderBy('id');
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(RssFeed::class, 'rss_feed_id');
    }

    public function indexingLogs(): HasMany
    {
        return $this->hasMany(IndexingLog::class)->latest('created_at');
    }

    // Scopes

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->where('published_at', '<=', now());
    }

    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query->published()->orderByDesc('published_at');
    }

    public function scopeInCategoryTree(Builder $query, Category $category): Builder
    {
        return $query->whereIn('category_id', $category->treeIds());
    }

    public function scopeForListing(Builder $query): Builder
    {
        return $query->select([
            'id', 'user_id', 'category_id', 'title', 'slug', 'excerpt', 'image', 'image_alt', 'post_type', 'video_url',
            'published_at', 'views', 'reading_time', 'is_featured', 'is_breaking',
        ])->with(['category:id,name,slug,color', 'author:id,name,slug']);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('meta_keywords', 'like', $like);
        });
    }

    // Helpers

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->published_at && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->published_at && $this->published_at->gt(now());
    }

    public function url(): string
    {
        return url(PostUrl::path($this));
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function seoDescription(): string
    {
        return Str::limit(trim($this->meta_description ?: (string) $this->excerpt), 160, '');
    }

    public function imageUrl(string $size = 'large'): ?string
    {
        return ImageService::url($this->image, $size);
    }

    public function imageSrcset(): ?string
    {
        return ImageService::srcset($this->image);
    }

    public function imageAltText(): string
    {
        return $this->image_alt ?: $this->title;
    }

    public function isVideo(): bool
    {
        return $this->post_type === 'video' && EmbedRenderer::video($this->video_url) !== null;
    }

    /**
     * @return array{type: string, src: string, poster: ?string, id: ?string}|null
     */
    public function video(): ?array
    {
        return EmbedRenderer::video($this->video_url);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->post_type] ?? 'Article';
    }

    public function recordView(?string $sessionKey = null): void
    {
        static::withoutTimestamps(fn () => $this->increment('views'));
        PostView::query()->upsert(
            [['post_id' => $this->id, 'viewed_on' => now()->toDateString(), 'count' => 1]],
            ['post_id', 'viewed_on'],
            ['count' => DB::raw('post_views.count + 1')],
        );
    }

    public static function trending(int $days = 7, int $limit = 6)
    {
        $ids = PostView::query()
            ->selectRaw('post_id, SUM(count) as total')
            ->where('viewed_on', '>=', now()->subDays($days)->toDateString())
            ->groupBy('post_id')
            ->orderByDesc('total')
            ->limit($limit * 2)
            ->pluck('post_id');

        $posts = static::published()->forListing()->whereIn('id', $ids)->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids->all()))
            ->take($limit)
            ->values();

        if ($posts->count() < $limit) {
            $more = static::published()->forListing()
                ->whereNotIn('id', $posts->pluck('id'))
                ->where('published_at', '>=', now()->subDays(30))
                ->orderByDesc('views')->orderByDesc('published_at')
                ->limit($limit - $posts->count())->get();
            $posts = $posts->concat($more);
        }

        return $posts;
    }
}
