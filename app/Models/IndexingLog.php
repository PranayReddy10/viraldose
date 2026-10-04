<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndexingLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['post_id', 'url', 'provider', 'action', 'status', 'response', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public static function record(string $provider, string $action, string $url, bool $ok, ?string $response = null, ?int $postId = null): self
    {
        return static::create([
            'post_id' => $postId,
            'url' => $url,
            'provider' => $provider,
            'action' => $action,
            'status' => $ok ? 'ok' : 'error',
            'response' => $response !== null ? mb_substr($response, 0, 2000) : null,
            'created_at' => now(),
        ]);
    }
}
