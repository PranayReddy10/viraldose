<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (Tag $tag) {
            if (blank($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public function url(): string
    {
        return route('tag.show', $this->slug);
    }

    /**
     * Resolve a comma separated list of tag names into Tag ids, creating missing tags.
     *
     * @return array<int>
     */
    public static function syncFromString(?string $raw): array
    {
        if (blank($raw)) {
            return [];
        }
        $names = collect(explode(',', $raw))
            ->map(fn ($n) => trim(Str::squish($n)))
            ->filter()
            ->unique(fn ($n) => Str::slug($n))
            ->take(20);

        $ids = [];
        foreach ($names as $name) {
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }
            $tag = static::firstOrCreate(['slug' => $slug], ['name' => Str::limit($name, 100, '')]);
            $ids[] = $tag->id;
        }

        return $ids;
    }
}
