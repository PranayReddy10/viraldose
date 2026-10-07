<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Text for posting a story to the ViralDose WhatsApp Channel. WhatsApp has no API for posting
 * to Channels, so the admin copies this (or opens WhatsApp with it) and sends it in the channel.
 * *bold* is WhatsApp formatting; the link shows a preview with the article's image.
 */
class WhatsAppShare
{
    public static function text(Post $post): string
    {
        $summary = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post->excerpt ?: $post->seoDescription())))), 220);

        return '*'.trim($post->title)."*\n\n".($summary !== '' ? $summary."\n\n" : '').'👉 Read more: '.$post->url();
    }

    public static function url(Post $post): string
    {
        return 'https://wa.me/?text='.rawurlencode(static::text($post));
    }
}
