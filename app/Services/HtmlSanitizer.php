<?php

namespace App\Services;

/**
 * Allow-list based HTML sanitizer for editor content (posts, pages, comments).
 * Uses DOMDocument so no third-party package is required.
 */
class HtmlSanitizer
{
    /** @var array<string, array<int, string>> tag => allowed attributes */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [],
        'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'h5' => [], 'h6' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'mark' => [], 'small' => [], 'sub' => [], 'sup' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'ul' => [], 'ol' => ['start'], 'li' => [],
        'blockquote' => ['cite'], 'q' => [], 'cite' => [],
        'pre' => [], 'code' => [],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'iframe' => ['src', 'width', 'height', 'allow', 'allowfullscreen', 'frameborder', 'loading', 'title'],
        'div' => ['class', 'data-provider', 'data-url'], 'span' => ['class'],
    ];

    private const IFRAME_HOSTS = [
        'www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com',
        'platform.twitter.com', 'www.instagram.com', 'www.facebook.com', 'open.spotify.com', 'www.google.com',
    ];

    private const ALLOWED_CLASSES = ['ql-align-center', 'ql-align-right', 'ql-align-justify', 'ql-indent-1', 'ql-indent-2', 'embed', 'embed-responsive', 'embed-label', 'embed-url', 'table-wrap'];

    public function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if (! $root) {
            return strip_tags($html);
        }
        $this->walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private function walk(\DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child instanceof \DOMComment) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'link', 'meta', 'base'], true)) {
                $node->removeChild($child);

                continue;
            }
            if (! isset(self::ALLOWED[$tag])) {
                // Unwrap: keep the children, drop the tag.
                $this->walk($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }
            $this->cleanAttributes($child, $tag);
            if ($tag === 'iframe' && ! $this->iframeAllowed($child->getAttribute('src'))) {
                $node->removeChild($child);

                continue;
            }
            $this->walk($child);
        }
    }

    private function cleanAttributes(\DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];
        $remove = [];
        foreach ($el->attributes as $attr) {
            $name = strtolower($attr->nodeName);
            if (! in_array($name, $allowed, true)) {
                $remove[] = $name;

                continue;
            }
            $value = trim($attr->nodeValue ?? '');
            if (in_array($name, ['href', 'src', 'cite'], true) && ! $this->safeUrl($value)) {
                $remove[] = $name;
            }
            if ($name === 'class') {
                $classes = array_filter(explode(' ', $value), fn ($c) => in_array($c, self::ALLOWED_CLASSES, true));
                if ($classes) {
                    $el->setAttribute('class', implode(' ', $classes));
                } else {
                    $remove[] = 'class';
                }
            }
        }
        foreach (array_unique($remove) as $name) {
            $el->removeAttribute($name);
        }
        if ($tag === 'a' && $el->hasAttribute('href')) {
            $href = $el->getAttribute('href');
            $host = parse_url($href, PHP_URL_HOST);
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            if ($host && $appHost && ! str_ends_with($host, $appHost)) {
                $el->setAttribute('rel', 'noopener nofollow');
                $el->setAttribute('target', '_blank');
            } elseif ($el->getAttribute('target') === '_blank') {
                $el->setAttribute('rel', 'noopener');
            }
        }
        if ($tag === 'img') {
            if (! $el->hasAttribute('loading')) {
                $el->setAttribute('loading', 'lazy');
            }
            if (! $el->hasAttribute('alt')) {
                $el->setAttribute('alt', '');
            }
        }
        if ($tag === 'iframe') {
            $el->setAttribute('loading', 'lazy');
        }
        if ($tag === 'div' && ($el->hasAttribute('data-provider') || $el->hasAttribute('data-url'))) {
            $provider = strtolower($el->getAttribute('data-provider'));
            $url = $el->getAttribute('data-url');
            if (! in_array($provider, EmbedRenderer::PROVIDERS, true) || ! EmbedRenderer::validUrl($provider, $url)) {
                $el->removeAttribute('data-provider');
                $el->removeAttribute('data-url');
            } else {
                $el->setAttribute('class', 'embed');
            }
        }
    }

    private function safeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }

    private function iframeAllowed(string $src): bool
    {
        $host = strtolower((string) parse_url($src, PHP_URL_HOST));

        return $host !== '' && in_array($host, self::IFRAME_HOSTS, true) && str_starts_with(strtolower($src), 'https://');
    }
}
