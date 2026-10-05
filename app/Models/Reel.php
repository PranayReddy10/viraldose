<?php

namespace App\Models;

use App\Services\EmbedRenderer;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Reel extends Model
{
    use HasFactory;

    public const SOURCES = [
        'upload' => 'Upload video file (MP4)',
        'image' => 'Upload a photo (image reel)',
        'url' => 'Direct video URL (.mp4)',
        'youtube' => 'YouTube Shorts / video URL',
        'instagram' => 'Instagram Reel URL',
    ];

    protected $fillable = [
        'user_id', 'post_id', 'category_id', 'title', 'slug', 'source_type', 'video_path', 'external_url', 'external_id',
        'thumbnail', 'caption', 'is_active', 'sort_order', 'views', 'published_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'published_at' => 'datetime', 'views' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Reel $reel) {
            $base = Str::limit(Str::slug($reel->slug ?: $reel->title), 200, '') ?: 'reel';
            $slug = $base;
            $i = 2;
            while (static::where('slug', $slug)->when($reel->id, fn ($q) => $q->where('id', '!=', $reel->id))->exists()) {
                $slug = $base.'-'.$i++;
            }
            $reel->slug = $slug;
            $reel->published_at ??= now();
            if ($reel->source_type === 'youtube' && $reel->external_url) {
                $reel->external_id = EmbedRenderer::youtubeId($reel->external_url);
                $reel->thumbnail = $reel->thumbnail ?: ($reel->external_id ? "https://i.ytimg.com/vi/{$reel->external_id}/hqdefault.jpg" : null);
            }
            if ($reel->source_type === 'instagram' && $reel->external_url && preg_match('#/(?:p|reel|reels|tv)/([A-Za-z0-9_-]+)#', $reel->external_url, $m)) {
                $reel->external_id = $m[1];
            }
        });
        static::saved(fn () => Cache::forget('home.reels'));
        static::deleted(fn () => Cache::forget('home.reels'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('published_at', '<=', now());
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function url(): string
    {
        return route('reels.show', $this->slug);
    }

    public function videoUrl(): ?string
    {
        return match ($this->source_type) {
            'upload' => ImageService::publicUrl($this->video_path),
            'url' => $this->video_path,
            default => null,
        };
    }

    public function embedUrl(): ?string
    {
        return match ($this->source_type) {
            'youtube' => $this->external_id ? 'https://www.youtube-nocookie.com/embed/'.$this->external_id.'?playsinline=1&rel=0&modestbranding=1&loop=1&playlist='.$this->external_id : null,
            'instagram' => $this->external_id ? 'https://www.instagram.com/reel/'.$this->external_id.'/embed/' : null,
            default => null,
        };
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail ? ImageService::url($this->thumbnail, 'medium') : null;
    }

    /** Full-size photo for image reels (the photo is stored in `thumbnail`). */
    public function imageUrl(): ?string
    {
        return $this->thumbnail ? ImageService::url($this->thumbnail, 'large') : null;
    }

    public function isPlayable(): bool
    {
        return in_array($this->source_type, ['upload', 'url'], true);
    }

    public function isImage(): bool
    {
        return $this->source_type === 'image';
    }

    /** Can this reel be posted to Instagram from here (photo or a direct video file)? */
    public function canShareToInstagram(): bool
    {
        return ($this->isImage() && $this->thumbnail) || ($this->isPlayable() && $this->videoUrl());
    }

    public function socialShares()
    {
        return $this->hasMany(SocialShare::class);
    }

    public static function homeStrip(int $limit = 10)
    {
        return Cache::remember('home.reels', 600, fn () => static::live()->ordered()->limit($limit)->get());
    }
}
