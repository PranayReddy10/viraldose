<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Str;

/**
 * On-page SEO checklist for one article. Returns a score (0–100) and checks
 * with pass / warn / fail so editors fix issues before publishing.
 */
class SeoAnalyzer
{
    /**
     * @return array{score: int, grade: string, checks: array<int, array{label: string, status: string, hint: string}>}
     */
    public function analyze(Post $post): array
    {
        $checks = [];
        $title = $post->seoTitle();
        $titleLen = mb_strlen($title);
        $desc = $post->seoDescription();
        $descLen = mb_strlen($desc);
        $content = (string) $post->content;
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
        $words = str_word_count($text);
        $keyword = Str::lower(trim(explode(',', (string) $post->meta_keywords)[0] ?? ''));
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        $checks[] = $this->check('Title length', $titleLen >= 30 && $titleLen <= 65 ? 'pass' : ($titleLen > 0 && $titleLen < 30 ? 'warn' : 'fail'),
            "{$titleLen} characters – aim for 30–65 so it is not cut off in Google.");
        $checks[] = $this->check('Meta description', $descLen >= 70 && $descLen <= 160 ? 'pass' : ($descLen > 0 ? 'warn' : 'fail'),
            $descLen ? "{$descLen} characters – aim for 70–160." : 'Missing – add a meta description or excerpt.');
        $checks[] = $this->check('URL slug', mb_strlen($post->slug) <= 75 ? 'pass' : 'warn', mb_strlen($post->slug).' characters – shorter slugs are easier to share and rank.');
        $checks[] = $this->check('Content length', $words >= 400 ? 'pass' : ($words >= 150 ? 'warn' : 'fail'),
            "{$words} words – thin pages (under ~300 words) are the main cause of “Crawled – currently not indexed”.");
        $checks[] = $this->check('Featured image', $post->image ? 'pass' : 'fail', $post->image ? 'Present.' : 'Add a featured image (1200×675) for Discover, social cards and image search.');
        $checks[] = $this->check('Image alt text', $post->image_alt ? 'pass' : ($post->image ? 'warn' : 'fail'), $post->image_alt ? 'Present.' : 'Describe the featured image in the Alt text field.');

        preg_match_all('/<h[2-4][^>]*>/i', $content, $h);
        $checks[] = $this->check('Subheadings', count($h[0]) >= 1 ? 'pass' : ($words > 300 ? 'warn' : 'pass'), count($h[0]).' subheadings – break long articles into H2/H3 sections.');

        preg_match_all('/<a\s[^>]*href="([^"]+)"/i', $content, $links);
        $internal = $external = 0;
        foreach ($links[1] ?? [] as $href) {
            $linkHost = parse_url($href, PHP_URL_HOST);
            if (! $linkHost || $linkHost === $host) {
                $internal++;
            } else {
                $external++;
            }
        }
        $checks[] = $this->check('Internal links', $internal >= 1 ? 'pass' : 'warn', "{$internal} internal links – link to 2–3 related ViralDose stories to help crawling.");
        $checks[] = $this->check('Outbound links', $external >= 1 ? 'pass' : 'warn', "{$external} outbound links – citing sources builds trust.");

        preg_match_all('/<img\b[^>]*>/i', $content, $imgs);
        $missingAlt = count(array_filter($imgs[0] ?? [], fn ($tag) => ! preg_match('/alt="[^"]+"/i', $tag)));
        $checks[] = $this->check('Inline image alt text', $missingAlt === 0 ? 'pass' : 'warn', $missingAlt ? "{$missingAlt} inline images have no alt text." : 'All inline images have alt text.');

        if ($keyword !== '') {
            $inTitle = Str::contains(Str::lower($title), $keyword);
            $inDesc = Str::contains(Str::lower($desc), $keyword);
            $inUrl = Str::contains($post->slug, Str::slug($keyword));
            $inIntro = Str::contains(Str::lower(Str::limit($text, 400, '')), $keyword);
            $hits = ($inTitle ? 1 : 0) + ($inDesc ? 1 : 0) + ($inUrl ? 1 : 0) + ($inIntro ? 1 : 0);
            $checks[] = $this->check("Focus keyword “{$keyword}”", $hits >= 3 ? 'pass' : ($hits >= 1 ? 'warn' : 'fail'),
                'Found in: '.implode(', ', array_keys(array_filter(['title' => $inTitle, 'description' => $inDesc, 'URL' => $inUrl, 'first paragraph' => $inIntro]))) ?: 'Not found in title, description, URL or intro.');
        } else {
            $checks[] = $this->check('Focus keyword', 'warn', 'Set a focus keyword (first entry in “Focus keywords”) to get keyword checks.');
        }

        $checks[] = $this->check('Category & tags', $post->category_id && $post->tags->count() >= 1 ? 'pass' : 'warn', $post->tags->count().' tags – 2–5 relevant tags help internal linking.');
        $checks[] = $this->check('Indexable', $post->noindex ? 'fail' : 'pass', $post->noindex ? 'This post is set to noindex and will not appear in Google.' : 'Robots: index, follow.');
        if ($post->canonical_url && rtrim($post->canonical_url, '/') !== rtrim($post->url(), '/')) {
            $checks[] = $this->check('Canonical', 'warn', 'Canonical points elsewhere – Google will credit that URL instead of this page.');
        }

        $points = ['pass' => 1, 'warn' => 0.5, 'fail' => 0];
        $score = (int) round(array_sum(array_map(fn ($c) => $points[$c['status']], $checks)) / max(1, count($checks)) * 100);

        return [
            'score' => $score,
            'grade' => $score >= 80 ? 'good' : ($score >= 55 ? 'ok' : 'poor'),
            'checks' => $checks,
        ];
    }

    private function check(string $label, string $status, string $hint): array
    {
        return compact('label', 'status', 'hint');
    }
}
