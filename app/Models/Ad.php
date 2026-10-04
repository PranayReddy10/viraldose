<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Ad extends Model
{
    public const SLOTS = [
        'header' => 'Header – below navigation (every page, 728×90 / responsive)',
        'home_after_hero' => 'Home – below the top stories block',
        'home_in_latest' => 'Home – inside Latest News list (after every 5th story)',
        'home_middle' => 'Home – between category sections (after every 2nd section)',
        'sidebar_top' => 'Sidebar – top (300×250 / 300×600)',
        'sidebar_middle' => 'Sidebar – after Trending',
        'sidebar_bottom' => 'Sidebar – bottom (sticky)',
        'post_before_content' => 'Article – below the featured image',
        'post_in_content' => 'Article – inside the text (after 3rd paragraph)',
        'post_after_content' => 'Article – after the text',
        'post_after_related' => 'Article – after Related Stories',
        'category_top' => 'Category / tag / search – top',
        'archive_in_grid' => 'Category / tag / search – inside the grid (after every 6th card)',
        'reels_feed' => 'Reels feed – a full-screen ad slide (after every Nth reel)',
        'mobile_sticky' => 'Mobile – sticky bottom anchor (320×50, closable)',
        'footer' => 'Footer – above the footer (every page)',
    ];

    public const DEVICES = ['all' => 'All devices', 'mobile' => 'Mobile only', 'desktop' => 'Desktop only'];

    public const PAGES = ['all' => 'All pages', 'home' => 'Home page', 'post' => 'Article pages', 'category' => 'Category / archive pages', 'reels' => 'Reels'];

    protected $fillable = ['name', 'slot', 'device', 'pages', 'code', 'image', 'url', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('ads.active'));
        static::deleted(fn () => Cache::forget('ads.active'));
    }

    public static function forSlot(string $slot, ?string $page = null)
    {
        if (! setting('ads_enabled', 1)) {
            return collect();
        }
        $all = Cache::remember('ads.active', 3600, fn () => static::where('is_active', true)->orderBy('sort_order')->get());
        $page ??= static::currentPage();

        return $all->where('slot', $slot)->filter(fn ($ad) => $ad->pages === 'all' || $ad->pages === $page)->values();
    }

    public static function currentPage(): string
    {
        $route = optional(request()->route())->getName() ?? '';

        return match (true) {
            $route === 'home' => 'home',
            in_array($route, ['post.show', 'post.flat'], true) => 'post',
            str_starts_with($route, 'reels') => 'reels',
            in_array($route, ['category.show', 'tag.show', 'author.show', 'search'], true) => 'category',
            default => 'other',
        };
    }

    public function deviceClass(): string
    {
        return match ($this->device) {
            'mobile' => 'md:hidden',
            'desktop' => 'hidden md:block',
            default => '',
        };
    }
}
