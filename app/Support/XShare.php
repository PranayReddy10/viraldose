<?php

namespace App\Support;

use App\Models\Post;
use App\Services\InstagramPublisher;

/**
 * Free one-click posting to X (Twitter): builds a pre-filled "intent" link that opens the X
 * composer with the headline, link and hashtags – the editor just presses Post.
 * (The X API charges per post with a link, so nothing is posted automatically.)
 */
class XShare
{
    public const LIMIT = 280;

    /** X counts every link as 23 characters. */
    public const LINK_LENGTH = 23;

    public static function text(Post $post): string
    {
        $title = trim($post->seoTitle());
        $url = $post->url();
        $budget = self::LIMIT - self::LINK_LENGTH - mb_strlen($title) - 4; // two line breaks before link + tags

        $tags = [];
        foreach (preg_split('/\s+/', app(InstagramPublisher::class)->hashtags($post), -1, PREG_SPLIT_NO_EMPTY) as $tag) {
            if (count($tags) >= 4 || mb_strlen(implode(' ', [...$tags, $tag])) > $budget) {
                break;
            }
            $tags[] = $tag;
        }

        return $title."\n\n".$url.($tags ? "\n\n".implode(' ', $tags) : '');
    }

    public static function url(Post $post): string
    {
        return 'https://x.com/intent/post?text='.rawurlencode(static::text($post));
    }
}
