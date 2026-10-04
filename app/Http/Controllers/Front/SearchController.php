<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function __invoke(Request $request, Seo $seo)
    {
        $q = Str::limit(trim(strip_tags((string) $request->query('q', ''))), 100, '');

        $posts = null;
        if (mb_strlen($q) >= 2) {
            $posts = Post::published()->forListing()->search($q)
                ->orderByDesc('published_at')
                ->paginate((int) setting('posts_per_page', 12))
                ->withQueryString();
        }

        $seo->title($q !== '' ? "Search results for \"{$q}\"" : 'Search')
            ->description('Search '.site_name().' articles.')
            ->canonical(route('search'))
            ->noindex();

        return view('front.search', compact('q', 'posts'));
    }
}
