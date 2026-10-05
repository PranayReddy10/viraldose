<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RssFeed extends Model
{
    protected $fillable = [
        'name', 'url', 'category_id', 'user_id', 'language', 'auto_publish', 'import_images', 'fetch_full_content', 'is_active',
        'max_items', 'imported_count', 'last_fetched_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'auto_publish' => 'boolean',
            'import_images' => 'boolean',
            'fetch_full_content' => 'boolean',
            'is_active' => 'boolean',
            'last_fetched_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
