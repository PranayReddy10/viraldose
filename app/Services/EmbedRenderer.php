<?php

namespace App\Services;

/**
 * Turns the editor's social embed placeholders
 *   <div class="embed" data-provider="youtube|twitter|instagram" data-url="…"></div>
 * into real, lazy embeds on the public site, and knows which third-party
 * scripts the page then needs.
 */
class EmbedRenderer
{
    public const PROVIDERS = ['youtube', 'twitter', 'instagram', 'facebook'];

    /** @var array<string, string> provider => script URL loaded once per page */
    public const SCRIPTS = [
        'twitter' => 'https://platform.twitter.com/widgets.js',
        'instagram' => 'https://www.instagram.com/embed.js',
    ];

    /** @var array<int, string> */
    private array $scripts = [];

    public function render(?string $html): string
    {
        $html = (string) $html;
        if ($html === '' || ! str_contains($html, 'data-provider=')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<div\b([^>]*\bclass="[^"]*\bembed\b[^"]*"[^>]*)>(?:(?!<\/div>).)*<\/div>/is',
            function (array $m) {
                $attrs = $m[1];
                preg_match('/data-provider="([a-z]+)"/i', $attrs, $p);
                preg_match('/data-url="([^"]+)"/i', $attrs, $u);
                $provider = strtolower($p[1] ?? '');
                $url = html_entity_decode($u[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (! $provider || ! $url || ! static::validUrl($provider, $url)) {
                    return '';
                }

                return $this->embed($provider, $url);
            },
            $html,
        );
    }

    /**
     * Script tags required by the embeds rendered so far on this page.
     */
    public function scripts(): string
    {
        return collect(array_unique($this->scripts))
            ->map(fn ($src) => '<script async defer src="'.e($src).'" charset="utf-8"></script>')
            ->implode("\n");
    }

    public function embed(string $provider, string $url): string
    {
        $safe = e($url);
        switch ($provider) {
            case 'youtube':
                $id = static::youtubeId($url);
                if (! $id) {
                    return '';
                }

                return '<figure class="embed embed-youtube"><iframe src="https://www.youtube-nocookie.com/embed/'.e($id).'" title="YouTube video" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></figure>';
            case 'twitter':
                $this->scripts[] = self::SCRIPTS['twitter'];

                return '<div class="embed embed-twitter"><blockquote class="twitter-tweet" data-dnt="true"><a href="'.$safe.'">View post on X</a></blockquote></div>';
            case 'instagram':
                $this->scripts[] = self::SCRIPTS['instagram'];
                $permalink = rtrim(strtok($url, '?'), '/').'/';

                return '<div class="embed embed-instagram"><blockquote class="instagram-media" data-instgrm-permalink="'.e($permalink).'" data-instgrm-version="14"><a href="'.e($permalink).'" target="_blank" rel="noopener nofollow">View this post on Instagram</a></blockquote></div>';
            case 'facebook':
                return '<figure class="embed embed-facebook"><iframe src="https://www.facebook.com/plugins/post.php?href='.rawurlencode($url).'&show_text=true&width=500" loading="lazy" title="Facebook post" allow="encrypted-media" allowfullscreen scrolling="no" frameborder="0"></iframe></figure>';
        }

        return '';
    }

    public static function validUrl(string $provider, string $url): bool
    {
        if (! str_starts_with(strtolower($url), 'https://')) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www|m|mobile)\./', '', $host);

        return match ($provider) {
            'youtube' => in_array($host, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], true) && static::youtubeId($url) !== null,
            'twitter' => in_array($host, ['twitter.com', 'x.com'], true) && (bool) preg_match('#/status/\d+#', $url),
            'instagram' => $host === 'instagram.com' && (bool) preg_match('#/(p|reel|reels|tv)/[A-Za-z0-9_-]+#', $url),
            'facebook' => in_array($host, ['facebook.com', 'fb.watch'], true),
            default => false,
        };
    }

    public static function youtubeId(string $url): ?string
    {
        if (preg_match('#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Normalises a user supplied video URL for the "Video" post type.
     *
     * @return array{type: string, src: string, poster: ?string}|null
     */
    public static function video(?string $url): ?array
    {
        if (blank($url)) {
            return null;
        }
        if ($id = static::youtubeId($url)) {
            return ['type' => 'youtube', 'src' => 'https://www.youtube-nocookie.com/embed/'.$id, 'poster' => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg", 'id' => $id];
        }
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return ['type' => 'vimeo', 'src' => 'https://player.vimeo.com/video/'.$m[1], 'poster' => null, 'id' => $m[1]];
        }
        if (preg_match('#\.(mp4|webm|ogg)(\?.*)?$#i', $url) && str_starts_with($url, 'https://')) {
            return ['type' => 'file', 'src' => $url, 'poster' => null, 'id' => null];
        }

        return null;
    }
}
