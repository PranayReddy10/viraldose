<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostView extends Model
{
    public $timestamps = false;

    protected $fillable = ['post_id', 'viewed_on', 'count'];

    protected function casts(): array
    {
        return ['viewed_on' => 'date', 'count' => 'integer'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
