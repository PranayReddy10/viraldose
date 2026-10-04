<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Ad extends Model
{
    public const SLOTS = [
        'header' => 'Header (below navigation)',
        'home_middle' => 'Home page (between sections)',
        'sidebar_top' => 'Sidebar top',
        'sidebar_bottom' => 'Sidebar bottom',
        'post_before_content' => 'Article: before content',
        'post_in_content' => 'Article: inside content (after 3rd paragraph)',
        'post_after_content' => 'Article: after content',
        'category_top' => 'Category page top',
        'footer' => 'Footer',
    ];

    protected $fillable = ['name', 'slot', 'code', 'image', 'url', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('ads.active'));
        static::deleted(fn () => Cache::forget('ads.active'));
    }

    public static function forSlot(string $slot)
    {
        $all = Cache::remember('ads.active', 3600, fn () => static::where('is_active', true)->orderBy('sort_order')->get());

        return $all->where('slot', $slot)->values();
    }
}
