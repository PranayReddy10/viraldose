<?php

namespace App\Support;

use App\Models\Post;

/**
 * Centralised post URL generation so the public URL format can be switched
 * (flat "/slug" vs "/category/slug") from Settings without touching views.
 */
class PostUrl
{
    public const FORMAT_CATEGORY = 'category';

    public const FORMAT_FLAT = 'flat';

    public static function format(): string
    {
        return setting('post_url_format', self::FORMAT_CATEGORY) === self::FORMAT_FLAT ? self::FORMAT_FLAT : self::FORMAT_CATEGORY;
    }

    public static function path(Post $post): string
    {
        if (static::format() === self::FORMAT_FLAT) {
            return '/'.$post->slug;
        }

        $categorySlug = $post->relationLoaded('category') && $post->category
            ? $post->category->slug
            : optional($post->category)->slug;

        return '/'.($categorySlug ?: 'news').'/'.$post->slug;
    }
}
