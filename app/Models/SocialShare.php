<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialShare extends Model
{
    protected $fillable = ['post_id', 'reel_id', 'network', 'media_type', 'status', 'creation_id', 'external_id', 'permalink', 'image', 'caption', 'response'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class);
    }
}
