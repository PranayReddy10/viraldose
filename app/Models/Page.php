<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'content', 'meta_title', 'meta_description', 'is_active', 'show_in_footer', 'noindex', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_footer' => 'boolean',
            'noindex' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page) {
            $base = Str::slug($page->slug ?: $page->title) ?: 'page';
            $slug = $base;
            $i = 2;
            while (static::where('slug', $slug)->when($page->id, fn ($q) => $q->where('id', '!=', $page->id))->exists()) {
                $slug = $base.'-'.$i++;
            }
            $page->slug = $slug;
        });
        static::saved(fn () => Cache::forget('footer.pages'));
        static::deleted(fn () => Cache::forget('footer.pages'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function url(): string
    {
        return route('page.show', $this->slug);
    }

    public static function footerPages()
    {
        return Cache::remember('footer.pages', 3600, fn () => static::active()->where('show_in_footer', true)
            ->orderBy('sort_order')->orderBy('title')->get(['id', 'title', 'slug']));
    }
}
