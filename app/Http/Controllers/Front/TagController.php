<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tag;
use App\Services\Seo;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function show(Request $request, Seo $seo, string $slug)
    {
        $tag = Tag::where('slug', $slug)->firstOrFail();
        $page = max(1, (int) $request->integer('page', 1));

        $posts = Post::published()->forListing()
            ->whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
            ->orderByDesc('published_at')
            ->paginate((int) setting('posts_per_page', 12))
            ->withQueryString();

        if ($posts->isEmpty()) {
            abort(404);
        }

        $title = $tag->name.($page > 1 ? " - Page {$page}" : '');
        $seo->title($title)
            ->description("Read the latest news and stories tagged \"{$tag->name}\" on ".site_name().'.')
            ->canonical($page > 1 ? $tag->url().'?page='.$page : $tag->url())
            ->pagination($posts->previousPageUrl(), $posts->nextPageUrl())
            ->breadcrumbs([
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Tags', 'url' => url('/tag')],
                ['name' => $tag->name, 'url' => $tag->url()],
            ]);

        // Thin tag archives add little value; keep them crawlable but out of the index.
        if ($posts->total() < 3) {
            $seo->noindex();
        }

        return view('front.tag', compact('tag', 'posts'));
    }
}
