<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pulls the full article body out of a news web page (readability-style),
 * because most RSS feeds only carry a teaser: one linked image and a line of text.
 *
 * No third-party package: DOMDocument + a paragraph-density score, with the
 * usual boilerplate (nav, share bars, "also read" link lists, ads) stripped.
 */
class ArticleExtractor
{
    private const NOISE_TAGS = ['script', 'style', 'noscript', 'template', 'nav', 'header', 'footer', 'aside', 'form', 'button', 'input', 'select', 'textarea', 'svg', 'canvas', 'dialog', 'menu'];

    private const NOISE_PATTERN = '/(^|[\s_-])(share|sharing|social|related|recommend\w*|comments?|sidebar|footer|header|navbar|breadcrumbs?|promo|advert\w*|ads?|adsbygoogle|ad-slot|sponsor\w*|newsletter|subscribe|subscription|tags?|taglist|author-?box|byline|post-?meta|entry-?meta|popup|modal|cookie|widget|outbrain|taboola|also-?read|read-?more|read-?next|trending|most-?read|more-?stories|you-?may-?like|next-?story|prev-?story|pagination|jump-?links|toc|table-?of-?contents|caption-?credit|photo-?credit|wp-?block-?embed-?twitter|sr-only|hidden|visually-?hidden|print-?only)([\s_-]|$)/i';

    private const POSITIVE_PATTERN = '/(article|story|post|entry|news|blog)[\s_-]?(body|content|text|detail|main|wrap|inner)|^(content|main|body|text)$|articlebody|storybody|rich-?text|wysiwyg/i';

    private const BLOCK_TAGS = ['p', 'div', 'section', 'article', 'main', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'blockquote', 'pre', 'figure', 'figcaption', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'iframe', 'dl', 'dt', 'dd', 'address'];

    private const UNWRAP_TAGS = ['div', 'section', 'article', 'main', 'span', 'font', 'center', 'body', 'html', 'picture', 'source', 'amp-img', 'ins'];

    private const TRACKING_IMAGE = '/feedburner|feeds\.wordpress\.com|stats\.wordpress|pixel\.|\/pixel|\/tracking|1x1|spacer|blank\.gif|doubleclick|scorecardresearch|quantserve|\.gif\?/i';

    /**
     * Fetch a page and extract the article.
     *
     * @return array{html: string, text_length: int, image: ?string, title: ?string}|null
     */
    public function fromUrl(string $url, int $timeout = 15): ?array
    {
        $url = trim($url);
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return null;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        $response = Http::timeout($timeout)->connectTimeout(8)
            ->withOptions(['allow_redirects' => ['max' => 5]])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-IN,en;q=0.9',
                'Referer' => 'https://'.$host.'/',
            ])->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException('Source page returned HTTP '.$response->status());
        }
        $contentType = strtolower((string) $response->header('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'html') && ! str_contains($contentType, 'xml')) {
            throw new \RuntimeException('Source URL is not an HTML page');
        }
        $body = $response->body();
        if (preg_match('/charset=["\']?([\w-]+)/i', $contentType, $m) && ! in_array(strtolower($m[1]), ['utf-8', 'utf8'], true)) {
            $converted = @mb_convert_encoding($body, 'UTF-8', $m[1]);
            $body = $converted !== false ? $converted : $body;
        }

        return $this->extract($body, $url);
    }

    /**
     * @return array{html: string, text_length: int, image: ?string, title: ?string}|null
     */
    public function extract(string $html, string $baseUrl): ?array
    {
        if (trim($html) === '') {
            return null;
        }
        if (! mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
        }
        // The document is re-declared as UTF-8 below, so a conflicting <meta charset> must go.
        $html = preg_replace('/<meta[^>]+charset[^>]*>/i', '', $html) ?? $html;

        $doc = $this->load($html);
        if (! $doc) {
            return null;
        }
        $xpath = new \DOMXPath($doc);

        $meta = $this->meta($xpath);
        $base = $this->baseHref($xpath) ?: $baseUrl;

        $container = $this->findContainer($doc, $xpath);
        $out = null;
        if ($container) {
            $out = $this->renderContainer($doc, $container, $base);
        }
        if (! $out || $out['text_length'] < 200) {
            $fallback = $this->fromJsonLd($xpath);
            if ($fallback && (! $out || mb_strlen(strip_tags($fallback)) > $out['text_length'])) {
                $out = ['html' => $fallback, 'text_length' => mb_strlen(trim(strip_tags($fallback)))];
            }
        }
        if (! $out || $out['text_length'] < 80) {
            return null;
        }

        return $out + ['image' => $meta['image'] ? $this->absolute($meta['image'], $base) : null, 'title' => $meta['title']];
    }

    /**
     * Normalise article HTML that came from a feed or a page: absolute URLs, no
     * image-links, no tracking pixels, no duplicate lead image, text wrapped in <p>.
     */
    public function tidy(string $html, string $baseUrl, ?string $featuredImage = null): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $doc = $this->load('<div id="__root">'.$html.'</div>', true);
        $root = $doc?->getElementById('__root');
        if (! $root) {
            return $html;
        }
        $this->cleanTree($root, $baseUrl, $featuredImage, false);

        return $this->innerHtml($root);
    }

    public function textLength(?string $html): int
    {
        return mb_strlen(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''));
    }

    // ---------------------------------------------------------------------

    private function load(string $html, bool $fragment = false): ?\DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $flags = LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR | ($fragment ? LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD : 0);
        $ok = $doc->loadHTML('<?xml encoding="UTF-8">'.$html, $flags);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $ok ? $doc : null;
    }

    /** @return array{image: ?string, title: ?string} */
    private function meta(\DOMXPath $xpath): array
    {
        $get = function (string $query) use ($xpath): ?string {
            $node = $xpath->query($query)->item(0);
            $value = $node instanceof \DOMElement ? trim($node->getAttribute('content')) : null;

            return $value !== '' ? $value : null;
        };

        return [
            'image' => $get('//meta[@property="og:image"]') ?? $get('//meta[@name="twitter:image"]') ?? $get('//meta[@property="og:image:url"]'),
            'title' => $get('//meta[@property="og:title"]') ?? trim((string) $xpath->query('//title')->item(0)?->textContent) ?: null,
        ];
    }

    private function baseHref(\DOMXPath $xpath): ?string
    {
        $base = $xpath->query('//base[@href]')->item(0);

        return $base instanceof \DOMElement ? trim($base->getAttribute('href')) ?: null : null;
    }

    private function findContainer(\DOMDocument $doc, \DOMXPath $xpath): ?\DOMElement
    {
        // 1. Explicit schema.org article body.
        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@itemprop), " "), " articleBody ")]') as $node) {
            if ($node instanceof \DOMElement && $this->paragraphText($node) >= 200) {
                return $node;
            }
        }

        // 2. Paragraph-density scoring (readability style).
        $scores = new \SplObjectStorage;
        foreach ($xpath->query('//p|//li|//blockquote|//h2|//h3|//pre|//td') as $p) {
            if (! $p instanceof \DOMElement) {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $p->textContent) ?? '');
            $len = mb_strlen($text);
            if ($len < 25) {
                continue;
            }
            if ($this->linkDensity($p) > 0.5) {
                continue;
            }
            if (in_array($p->tagName, ['li', 'td', 'h2', 'h3'], true)) {
                $len = (int) ($len / 2);
            }
            $parent = $p->parentNode;
            $grand = $parent?->parentNode;
            if ($parent instanceof \DOMElement) {
                $scores[$parent] = ($scores->contains($parent) ? $scores[$parent] : 0) + $len;
            }
            if ($grand instanceof \DOMElement && $grand !== $doc->documentElement) {
                $scores[$grand] = ($scores->contains($grand) ? $scores[$grand] : 0) + $len / 2;
            }
        }

        $best = null;
        $bestScore = 0;
        foreach ($scores as $el) {
            $score = $scores[$el];
            if (in_array(strtolower($el->tagName), ['body', 'html'], true)) {
                $score *= 0.6;
            }
            $hint = $el->getAttribute('class').' '.$el->getAttribute('id');
            if (preg_match(self::POSITIVE_PATTERN, $hint)) {
                $score *= 1.3;
            }
            if (preg_match(self::NOISE_PATTERN, $hint)) {
                $score *= 0.5;
            }
            $score *= 1 - min(0.8, $this->linkDensity($el));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $el;
            }
        }
        if ($best && $this->paragraphText($best) >= 150) {
            return $best;
        }

        // 3. <article> or main as a last resort.
        foreach (['//article', '//main', '//*[@role="main"]'] as $q) {
            $node = $xpath->query($q)->item(0);
            if ($node instanceof \DOMElement && $this->paragraphText($node) >= 150) {
                return $node;
            }
        }

        return $best;
    }

    private function fromJsonLd(\DOMXPath $xpath): ?string
    {
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode(trim($script->textContent), true);
            if (! is_array($data)) {
                continue;
            }
            $stack = [$data];
            while ($stack) {
                $node = array_pop($stack);
                if (! is_array($node)) {
                    continue;
                }
                if (! empty($node['articleBody']) && is_string($node['articleBody'])) {
                    $paragraphs = preg_split('/\n{2,}|\r\n\r\n|(?<=[.!?])\s{2,}/u', trim($node['articleBody'])) ?: [];
                    $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));
                    if (! $paragraphs) {
                        continue;
                    }

                    return implode('', array_map(fn ($p) => '<p>'.e($p).'</p>', $paragraphs));
                }
                foreach ($node as $value) {
                    if (is_array($value)) {
                        $stack[] = $value;
                    }
                }
            }
        }

        return null;
    }

    /** @return array{html: string, text_length: int} */
    private function renderContainer(\DOMDocument $doc, \DOMElement $container, string $base): array
    {
        $this->cleanTree($container, $base, null, true);
        $html = $this->innerHtml($container);

        return ['html' => $html, 'text_length' => $this->textLength($html)];
    }

    /**
     * Remove boilerplate, fix URLs, unwrap image links, wrap loose text in <p>.
     */
    private function cleanTree(\DOMElement $root, string $base, ?string $featured, bool $stripNoise): void
    {
        $xpath = new \DOMXPath($root->ownerDocument);

        if ($stripNoise) {
            $total = max(1, $this->paragraphText($root));
            foreach ($xpath->query('.//*', $root) as $el) {
                if (! $el instanceof \DOMElement || $el === $root || ! $this->isAlive($el, $root)) {
                    continue;
                }
                $tag = strtolower($el->tagName);
                $hint = $el->getAttribute('class').' '.$el->getAttribute('id');
                $noisy = in_array($tag, self::NOISE_TAGS, true)
                    || ($tag !== 'img' && $tag !== 'iframe' && preg_match(self::NOISE_PATTERN, $hint))
                    || $el->getAttribute('hidden') !== '' || preg_match('/display\s*:\s*none/i', $el->getAttribute('style'))
                    || $el->getAttribute('aria-hidden') === 'true';
                if ($noisy && in_array($tag, ['p', 'div', 'section', 'ul', 'ol', 'figure'], true) && $this->paragraphText($el) > $total * 0.5) {
                    $noisy = false; // never throw away the article itself over a class name
                }
                if ($noisy) {
                    $el->parentNode->removeChild($el);
                }
            }
        } else {
            foreach ($xpath->query('.//script|.//style|.//noscript|.//template', $root) as $el) {
                $el->parentNode?->removeChild($el);
            }
        }

        // Images: promote lazy attributes, absolute URLs, drop pixels and duplicates of the featured image.
        $seenText = false;
        $leadRemoved = false;
        foreach ($xpath->query('.//img', $root) as $img) {
            if (! $img instanceof \DOMElement || ! $this->isAlive($img, $root)) {
                continue;
            }
            $src = '';
            foreach (['data-src', 'data-lazy-src', 'data-original', 'data-url', 'data-img-src', 'data-srcset', 'srcset', 'src'] as $attr) {
                $value = trim($img->getAttribute($attr));
                if ($value === '' || Str::startsWith($value, 'data:')) {
                    continue;
                }
                if (in_array($attr, ['srcset', 'data-srcset'], true)) {
                    $value = $this->bestFromSrcset($value);
                }
                if ($value !== '') {
                    $src = $value;
                    break;
                }
            }
            $src = $src !== '' ? $this->absolute($src, $base) : '';
            $width = (int) $img->getAttribute('width');
            $height = (int) $img->getAttribute('height');
            $tracking = $src === '' || preg_match(self::TRACKING_IMAGE, $src) || ($width > 0 && $width <= 2) || ($height > 0 && $height <= 2);
            if ($tracking || ! Str::startsWith($src, ['http://', 'https://'])) {
                $this->removeWithEmptyWrappers($img, $root);

                continue;
            }
            foreach (['srcset', 'data-src', 'data-lazy-src', 'data-original', 'data-srcset', 'data-url', 'data-img-src', 'sizes', 'class', 'style', 'id', 'decoding', 'fetchpriority'] as $attr) {
                $img->removeAttribute($attr);
            }
            $img->setAttribute('src', $src);
            if ($width > 0 && $width < 120) {
                // icons, emoji, author avatars
                $this->removeWithEmptyWrappers($img, $root);

                continue;
            }
        }

        // Unwrap <a><img></a> so pictures are not links to the source site.
        foreach ($xpath->query('.//a[.//img]', $root) as $a) {
            if (! $a instanceof \DOMElement || ! $this->isAlive($a, $root)) {
                continue;
            }
            $text = trim($a->textContent);
            if ($text !== '' && mb_strlen($text) > 3) {
                continue; // a real text link that happens to contain an icon
            }
            while ($a->firstChild) {
                $a->parentNode->insertBefore($a->firstChild, $a);
            }
            $a->parentNode->removeChild($a);
        }

        // Duplicate lead image: the first image before any text, or any image matching the featured URL.
        if ($featured) {
            foreach ($xpath->query('.//img', $root) as $img) {
                if (! $img instanceof \DOMElement || ! $this->isAlive($img, $root)) {
                    continue;
                }
                $same = $this->sameImage($img->getAttribute('src'), $featured);
                $isLead = ! $leadRemoved && ! $this->hasTextBefore($img, $root);
                if ($same || $isLead) {
                    $this->removeWithEmptyWrappers($img, $root);
                    $leadRemoved = $leadRemoved || $isLead;
                }
            }
        }

        // Links: absolute hrefs; drop link-only "Also read" paragraphs and list items.
        foreach ($xpath->query('.//a[@href]', $root) as $a) {
            if ($a instanceof \DOMElement && $this->isAlive($a, $root)) {
                $a->setAttribute('href', $this->absolute($a->getAttribute('href'), $base));
            }
        }
        if ($stripNoise) {
            foreach ($xpath->query('.//p|.//li|.//h2|.//h3|.//h4|.//div[not(.//p)]', $root) as $el) {
                if (! $el instanceof \DOMElement || ! $this->isAlive($el, $root) || $el === $root) {
                    continue;
                }
                $text = trim($el->textContent);
                $len = mb_strlen($text);
                $hasMedia = $xpath->query('.//img|.//iframe', $el)->length > 0;
                if (! $hasMedia && $len > 0 && $len < 220 && $this->linkDensity($el) > 0.6) {
                    $el->parentNode?->removeChild($el);
                } elseif (! $hasMedia && preg_match('/^\s*(also read|read (more|also)|related( stories| news)?|watch:|click here|follow us|advertisement|trending now|more from|photo credit|image credit|source:)\b/iu', $text)) {
                    $el->parentNode?->removeChild($el);
                }
            }
        }

        // Unwrap layout wrappers (div/section/span…) so the stored HTML is editor-friendly.
        foreach ($xpath->query('.//*', $root) as $el) {
            if (! $el instanceof \DOMElement || ! $this->isAlive($el, $root)) {
                continue;
            }
            $tag = strtolower($el->tagName);
            if ($tag === 'div' && $el->getAttribute('data-provider') !== '') {
                continue; // our own embed placeholders
            }
            if (in_array($tag, self::UNWRAP_TAGS, true) || preg_match('/^(amp-|o-|c-)/', $tag) || str_contains($tag, ':')) {
                if ($tag === 'source') {
                    $el->parentNode->removeChild($el);

                    continue;
                }
                // A div holding bare text becomes a paragraph instead of leaking inline text.
                if ($this->isBlockish($el) && ! $this->hasBlockChild($el) && trim($el->textContent) !== '') {
                    $p = $root->ownerDocument->createElement('p');
                    while ($el->firstChild) {
                        $p->appendChild($el->firstChild);
                    }
                    $el->parentNode->replaceChild($p, $el);

                    continue;
                }
                while ($el->firstChild) {
                    $el->parentNode->insertBefore($el->firstChild, $el);
                }
                $el->parentNode->removeChild($el);
            }
            foreach (['class', 'id', 'style', 'onclick', 'onload', 'data-reactid'] as $attr) {
                if ($el->parentNode) {
                    $el->removeAttribute($attr);
                }
            }
        }

        $this->wrapLooseInline($root);
        $this->dropEmpty($root);
    }

    private function wrapLooseInline(\DOMElement $root): void
    {
        $doc = $root->ownerDocument;
        $children = [];
        foreach ($root->childNodes as $child) {
            $children[] = $child;
        }
        $run = [];
        $flush = function () use (&$run, $doc, $root) {
            if (! $run) {
                return;
            }
            $text = '';
            foreach ($run as $n) {
                $text .= $n->textContent;
            }
            $hasImg = (bool) array_filter($run, fn ($n) => $n instanceof \DOMElement && in_array(strtolower($n->tagName), ['img', 'iframe'], true));
            if (trim($text) === '' && ! $hasImg) {
                foreach ($run as $n) {
                    $root->removeChild($n);
                }
                $run = [];

                return;
            }
            $p = $doc->createElement('p');
            $root->insertBefore($p, $run[0]);
            foreach ($run as $n) {
                $p->appendChild($n);
            }
            $run = [];
        };
        foreach ($children as $child) {
            $isBlock = $child instanceof \DOMElement && in_array(strtolower($child->tagName), self::BLOCK_TAGS, true);
            if ($isBlock) {
                $flush();
            } else {
                $run[] = $child;
            }
        }
        $flush();
    }

    private function dropEmpty(\DOMElement $root): void
    {
        $xpath = new \DOMXPath($root->ownerDocument);
        for ($pass = 0; $pass < 3; $pass++) {
            $removed = 0;
            foreach ($xpath->query('.//p|.//figure|.//figcaption|.//li|.//ul|.//ol|.//blockquote|.//h2|.//h3|.//h4|.//table|.//tr|.//td|.//strong|.//em|.//a', $root) as $el) {
                if (! $el instanceof \DOMElement || ! $el->parentNode) {
                    continue;
                }
                if (trim(preg_replace('/\x{00A0}|\s/u', '', $el->textContent) ?? '') === '' && $xpath->query('.//img|.//iframe|.//br[following-sibling::*]', $el)->length === 0) {
                    $el->parentNode->removeChild($el);
                    $removed++;
                }
            }
            if (! $removed) {
                break;
            }
        }
        // Collapse runs of <br> at the start/end of paragraphs.
        foreach ($xpath->query('.//p', $root) as $p) {
            while ($p->firstChild && ($this->isBr($p->firstChild) || $this->isBlankText($p->firstChild))) {
                $p->removeChild($p->firstChild);
            }
            while ($p->lastChild && ($this->isBr($p->lastChild) || $this->isBlankText($p->lastChild))) {
                $p->removeChild($p->lastChild);
            }
            if (! $p->firstChild) {
                $p->parentNode?->removeChild($p);
            }
        }
    }

    private function isBr(\DOMNode $n): bool
    {
        return $n instanceof \DOMElement && strtolower($n->tagName) === 'br';
    }

    private function isBlankText(\DOMNode $n): bool
    {
        return $n instanceof \DOMText && trim(preg_replace('/\x{00A0}/u', '', $n->textContent) ?? '') === '';
    }

    private function isBlockish(\DOMElement $el): bool
    {
        return in_array(strtolower($el->tagName), ['div', 'section', 'article', 'main', 'center'], true);
    }

    private function hasBlockChild(\DOMElement $el): bool
    {
        foreach ($el->childNodes as $child) {
            if ($child instanceof \DOMElement && in_array(strtolower($child->tagName), array_merge(self::BLOCK_TAGS, ['div', 'section']), true)) {
                return true;
            }
        }

        return false;
    }

    private function hasTextBefore(\DOMElement $img, \DOMElement $root): bool
    {
        $xpath = new \DOMXPath($root->ownerDocument);
        foreach ($xpath->query('preceding::text()', $img) as $text) {
            if (! $this->contains($root, $text)) {
                continue;
            }
            if (mb_strlen(trim($text->textContent)) > 40) {
                return true;
            }
        }

        return false;
    }

    private function contains(\DOMNode $root, \DOMNode $node): bool
    {
        for ($n = $node; $n; $n = $n->parentNode) {
            if ($n === $root) {
                return true;
            }
        }

        return false;
    }

    private function isAlive(\DOMNode $node, \DOMElement $root): bool
    {
        return $this->contains($root, $node);
    }

    private function removeWithEmptyWrappers(\DOMElement $el, \DOMElement $root): void
    {
        $parent = $el->parentNode;
        $parent?->removeChild($el);
        while ($parent instanceof \DOMElement && $parent !== $root && in_array(strtolower($parent->tagName), ['a', 'p', 'figure', 'picture', 'div', 'span', 'figcaption'], true)
            && ! $parent->getElementsByTagName('img')->length && ! $parent->getElementsByTagName('iframe')->length
            && (trim($parent->textContent) === '' || (strtolower($parent->tagName) === 'figure' && mb_strlen(trim($parent->textContent)) < 200))) {
            $next = $parent->parentNode;
            $next?->removeChild($parent);
            $parent = $next;
        }
    }

    private function paragraphText(\DOMElement $el): int
    {
        $len = 0;
        foreach ($el->getElementsByTagName('p') as $p) {
            $len += mb_strlen(trim($p->textContent));
        }
        if ($len === 0) {
            $len = (int) (mb_strlen(trim($el->textContent)) / 2);
        }

        return $len;
    }

    private function linkDensity(\DOMElement $el): float
    {
        $text = mb_strlen(trim($el->textContent));
        if ($text === 0) {
            return 0.0;
        }
        $link = 0;
        foreach ($el->getElementsByTagName('a') as $a) {
            $link += mb_strlen(trim($a->textContent));
        }

        return min(1.0, $link / $text);
    }

    private function bestFromSrcset(string $srcset): string
    {
        $best = '';
        $bestW = -1;
        foreach (explode(',', $srcset) as $candidate) {
            $parts = preg_split('/\s+/', trim($candidate)) ?: [];
            $url = $parts[0] ?? '';
            $w = isset($parts[1]) ? (int) $parts[1] : 0;
            if ($url !== '' && $w >= $bestW) {
                $best = $url;
                $bestW = $w;
            }
        }

        return $best;
    }

    private function sameImage(string $a, string $b): bool
    {
        $norm = function (string $u): array {
            $u = strtolower(trim($u));
            $path = (string) parse_url($u, PHP_URL_PATH);
            $file = pathinfo($path, PATHINFO_FILENAME);
            $file = preg_replace('/[-_]\d{2,4}x\d{2,4}$/', '', $file) ?? $file; // strip -300x200 size suffixes

            return [preg_replace('/[?#].*$/', '', $u), $file];
        };
        [$ua, $fa] = $norm($a);
        [$ub, $fb] = $norm($b);

        return $ua !== '' && ($ua === $ub || ($fa !== '' && mb_strlen($fa) > 6 && $fa === $fb));
    }

    public function absolute(string $url, string $base): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('~^(https?:)?//~i', $url) || Str::startsWith($url, ['mailto:', 'tel:', '#', 'data:'])) {
            return Str::startsWith($url, '//') ? 'https:'.$url : $url;
        }
        $parts = parse_url($base);
        if (! $parts || empty($parts['host'])) {
            return $url;
        }
        $scheme = $parts['scheme'] ?? 'https';
        $origin = $scheme.'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }
        $dir = preg_replace('~/[^/]*$~', '/', $parts['path'] ?? '/') ?: '/';
        $path = $dir.$url;
        // resolve ./ and ../
        $segments = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '..') {
                array_pop($segments);
            } elseif ($seg !== '.') {
                $segments[] = $seg;
            }
        }

        return $origin.implode('/', $segments);
    }

    private function innerHtml(\DOMElement $el): string
    {
        $out = '';
        foreach ($el->childNodes as $child) {
            $out .= $el->ownerDocument->saveHTML($child);
        }

        return trim($out);
    }
}
