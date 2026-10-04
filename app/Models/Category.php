<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'meta_title', 'meta_description',
        'color', 'sort_order', 'show_in_menu', 'show_on_home', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'show_in_menu' => 'boolean',
            'show_on_home' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            $category->slug = static::uniqueSlug($category->slug ?: $category->name, $category->id);
        });
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget('nav.categories');
        Cache::forget('home.categories');
        Cache::forget('categories.parent_map');
        Post::flushCache();
    }

    public static function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'category';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function url(): string
    {
        return route('category.show', $this->slug);
    }

    /**
     * IDs of this category plus all descendant categories.
     *
     * @return array<int>
     */
    public function treeIds(): array
    {
        $map = static::parentMap();
        $ids = [$this->id];
        $queue = [$this->id];
        while ($queue) {
            $current = array_shift($queue);
            foreach ($map as $id => $parentId) {
                if ($parentId === $current && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                    $queue[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * Flat id => parent_id map of every category, cached so category trees never
     * trigger lazy loading or N+1 queries.
     *
     * @return array<int, int|null>
     */
    public static function parentMap(): array
    {
        return Cache::remember('categories.parent_map', 3600, function () {
            return static::query()->pluck('parent_id', 'id')
                ->map(fn ($parent) => $parent === null ? null : (int) $parent)
                ->all();
        });
    }

    public static function navigation()
    {
        return Cache::remember('nav.categories', 3600, function () {
            return static::active()->topLevel()->where('show_in_menu', true)->ordered()
                ->with(['children' => fn ($q) => $q->active()->where('show_in_menu', true)])
                ->get();
        });
    }
}
