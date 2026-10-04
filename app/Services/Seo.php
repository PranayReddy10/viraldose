<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Collects the SEO metadata for the current page. Controllers call the fluent
 * setters; the layout renders tags + JSON-LD from the resulting state.
 */
class Seo
{
    public ?string $title = null;

    public ?string $description = null;

    public ?string $canonical = null;

    public string $robots = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

    public string $type = 'website';

    public ?string $image = null;

    public ?int $imageWidth = null;

    public ?int $imageHeight = null;

    public ?string $publishedTime = null;

    public ?string $modifiedTime = null;

    public ?string $section = null;

    /** @var array<int, string> */
    public array $tags = [];

    /** @var array<int, array<string, mixed>> */
    public array $jsonLd = [];

    /** @var array<int, array{name: string, url: string}> */
    public array $breadcrumbs = [];

    public ?string $prev = null;

    public ?string $next = null;

    public ?string $feed = null;

    public bool $titleIsFull = false;

    public function title(?string $title, bool $full = false): static
    {
        $this->title = $title;
        $this->titleIsFull = $full;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = seo_truncate($description, 160);

        return $this;
    }

    public function canonical(?string $url): static
    {
        $this->canonical = $url;

        return $this;
    }

    public function noindex(bool $follow = true): static
    {
        $this->robots = $follow ? 'noindex, follow' : 'noindex, nofollow';

        return $this;
    }

    public function image(?string $url, ?int $width = null, ?int $height = null): static
    {
        $this->image = $url;
        $this->imageWidth = $width;
        $this->imageHeight = $height;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function feed(?string $url): static
    {
        $this->feed = $url;

        return $this;
    }

    public function pagination(?string $prev, ?string $next): static
    {
        $this->prev = $prev;
        $this->next = $next;

        return $this;
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $items
     */
    public function breadcrumbs(array $items): static
    {
        $this->breadcrumbs = $items;
        $list = [];
        foreach ($items as $i => $item) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }
        if ($list) {
            $this->jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addJsonLd(array $data): static
    {
        $this->jsonLd[] = $data;

        return $this;
    }

    public function fullTitle(): string
    {
        $site = site_name();
        if (blank($this->title)) {
            $tag = setting('site_tagline');

            return $tag ? "{$site} - {$tag}" : $site;
        }
        if ($this->titleIsFull) {
            return $this->title;
        }

        return Str::limit($this->title, 70 - strlen($site) - 3, '').' - '.$site;
    }

    public function resolvedDescription(): string
    {
        return $this->description ?: seo_truncate(setting('site_description'), 160);
    }

    public function resolvedImage(): ?string
    {
        return $this->image ?: media_url(setting('default_og_image')) ?: media_url(setting('logo'));
    }

    public function resolvedCanonical(): string
    {
        return $this->canonical ?: url()->current();
    }

    // Builders ------------------------------------------------------------

    public function forPost(Post $post): static
    {
        $this->title($post->seoTitle())
            ->description($post->seoDescription())
            ->canonical($post->canonical_url ?: $post->url())
            ->type('article')
            ->image($post->imageUrl('large'));
        if ($post->noindex) {
            $this->noindex();
        }
        $this->publishedTime = $post->published_at?->toIso8601String();
        $this->modifiedTime = $post->updated_at?->toIso8601String();
        $this->section = $post->category?->name;
        $this->tags = $post->tags->pluck('name')->all();

        $crumbs = [['name' => 'Home', 'url' => url('/')]];
        if ($post->category) {
            if ($post->category->parent) {
                $crumbs[] = ['name' => $post->category->parent->name, 'url' => $post->category->parent->url()];
            }
            $crumbs[] = ['name' => $post->category->name, 'url' => $post->category->url()];
        }
        $crumbs[] = ['name' => $post->title, 'url' => $post->url()];
        $this->breadcrumbs($crumbs);

        $article = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $post->url()],
            'headline' => Str::limit($post->title, 110, ''),
            'description' => $post->seoDescription(),
            'datePublished' => $this->publishedTime,
            'dateModified' => $this->modifiedTime,
            'author' => [[
                '@type' => 'Person',
                'name' => $post->author?->name ?? site_name(),
                'url' => $post->author ? $post->author->url() : url('/'),
            ]],
            'publisher' => static::publisher(),
            'articleSection' => $post->category?->name,
            'keywords' => implode(', ', $this->tags),
            'wordCount' => str_word_count(strip_tags((string) $post->content)),
            'inLanguage' => setting('language', 'en'),
            'isAccessibleForFree' => true,
        ];
        if ($post->image) {
            $article['image'] = array_values(array_filter([
                $post->imageUrl('large'),
                $post->imageUrl('medium'),
            ]));
        }
        $this->addJsonLd($article);

        if ($video = $post->video()) {
            $this->type('video.other');
            $this->addJsonLd(array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'VideoObject',
                'name' => $post->title,
                'description' => $post->seoDescription(),
                'thumbnailUrl' => array_values(array_filter([$post->imageUrl('large'), $video['poster']])),
                'uploadDate' => $this->publishedTime,
                'embedUrl' => $video['type'] === 'file' ? null : $video['src'],
                'contentUrl' => $video['type'] === 'file' ? $video['src'] : null,
                'publisher' => static::publisher(),
            ]));
        }

        return $this;
    }

    public function forCategory(Category $category, int $page = 1): static
    {
        $title = $category->meta_title ?: $category->name.' News';
        $this->title($page > 1 ? "{$title} - Page {$page}" : $title)
            ->description($category->meta_description ?: $category->description ?: "Latest {$category->name} news, stories and updates on ".site_name().'.')
            ->canonical($page > 1 ? $category->url().'?page='.$page : $category->url())
            ->feed(route('feed.category', $category->slug));

        $crumbs = [['name' => 'Home', 'url' => url('/')]];
        if ($category->parent) {
            $crumbs[] = ['name' => $category->parent->name, 'url' => $category->parent->url()];
        }
        $crumbs[] = ['name' => $category->name, 'url' => $category->url()];
        $this->breadcrumbs($crumbs);

        $this->addJsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $category->url(),
            'isPartOf' => ['@type' => 'WebSite', 'url' => url('/'), 'name' => site_name()],
        ]);

        return $this;
    }

    public function forHome(): static
    {
        $this->title(null)
            ->description(setting('site_description'))
            ->canonical(url('/'))
            ->feed(route('feed'));

        $this->addJsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => site_name(),
            'url' => url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('search').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
        $this->addJsonLd(array_merge(['@context' => 'https://schema.org'], static::publisher(true)));

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public static function publisher(bool $extended = false): array
    {
        $logo = media_url(setting('publisher_logo')) ?: media_url(setting('logo'));
        $data = [
            '@type' => setting('organization_type', 'NewsMediaOrganization'),
            'name' => site_name(),
            'url' => url('/'),
        ];
        if ($logo) {
            $data['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
        }
        if ($extended) {
            $social = array_values(array_filter([
                setting('facebook_url'), setting('twitter_url'), setting('instagram_url'), setting('youtube_url'),
            ]));
            if ($social) {
                $data['sameAs'] = $social;
            }
            if ($founded = setting('organization_founded')) {
                $data['foundingDate'] = $founded;
            }
            if ($email = setting('contact_email')) {
                $data['contactPoint'] = ['@type' => 'ContactPoint', 'email' => $email, 'contactType' => 'editorial'];
            }
        }

        return $data;
    }
}
