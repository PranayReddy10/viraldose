<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SitemapController extends Controller
{
    public const PER_SITEMAP = 1000;

    public function index()
    {
        $postCount = Post::published()->count();
        $chunks = max(1, (int) ceil($postCount / self::PER_SITEMAP));
        $latest = Post::published()->max('updated_at');

        $maps = [
            ['loc' => route('sitemap.categories'), 'lastmod' => $latest],
            ['loc' => route('sitemap.pages'), 'lastmod' => Page::active()->max('updated_at')],
            ['loc' => route('sitemap.tags'), 'lastmod' => $latest],
            ['loc' => route('sitemap.authors'), 'lastmod' => $latest],
        ];
        for ($i = 1; $i <= $chunks; $i++) {
            $maps[] = ['loc' => route('sitemap.posts', $i), 'lastmod' => $latest];
        }

        return $this->xml('front.sitemap.index', compact('maps'));
    }

    public function posts(int $page)
    {
        abort_if($page < 1, 404);
        $posts = Post::published()->where('noindex', false)
            ->select(['id', 'slug', 'category_id', 'image', 'title', 'updated_at', 'published_at'])
            ->with('category:id,slug')
            ->orderBy('id')
            ->forPage($page, self::PER_SITEMAP)
            ->get();
        abort_if($posts->isEmpty() && $page > 1, 404);

        return $this->xml('front.sitemap.posts', compact('posts'));
    }

    public function categories()
    {
        $categories = Category::active()->get(['id', 'slug', 'updated_at']);
        $lastPost = Post::published()->select('category_id', DB::raw('MAX(published_at) as last'))->groupBy('category_id')->pluck('last', 'category_id');

        return $this->xml('front.sitemap.categories', compact('categories', 'lastPost'));
    }

    public function tags()
    {
        $tags = Tag::query()
            ->whereHas('posts', fn ($q) => $q->published(), '>=', 3)
            ->get(['id', 'slug', 'updated_at']);

        return $this->xml('front.sitemap.tags', compact('tags'));
    }

    public function pages()
    {
        $pages = Page::active()->where('noindex', false)->get(['id', 'slug', 'updated_at']);

        return $this->xml('front.sitemap.pages', compact('pages'));
    }

    public function authors()
    {
        $authors = User::where('is_active', true)->whereHas('posts', fn ($q) => $q->published())->get(['id', 'slug', 'updated_at']);

        return $this->xml('front.sitemap.authors', compact('authors'));
    }

    /**
     * Google News sitemap: only articles from the last 48 hours, max 1000.
     */
    public function news()
    {
        $posts = Post::published()->where('noindex', false)
            ->where('published_at', '>=', now()->subHours(48))
            ->select(['id', 'slug', 'category_id', 'title', 'published_at', 'meta_keywords'])
            ->with(['category:id,slug', 'tags:id,name'])
            ->orderByDesc('published_at')->limit(1000)->get();

        return $this->xml('front.sitemap.news', compact('posts'));
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /search',
            'Disallow: /*?preview',
            '',
            'Sitemap: '.route('sitemap.index'),
            'Sitemap: '.route('sitemap.news'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function indexNowKey(string $key)
    {
        abort_unless(hash_equals((string) setting('indexnow_key'), $key), 404);

        return response($key, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function adsTxt()
    {
        $content = (string) setting('ads_txt', '');
        abort_if(trim($content) === '', 404);

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function xml(string $view, array $data)
    {
        return response()->view($view, $data)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=900');
    }
}
