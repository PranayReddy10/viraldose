<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;

class FeedController extends Controller
{
    public function index()
    {
        $posts = Post::published()->forListing()->orderByDesc('published_at')->limit(30)->get();

        return $this->respond($posts, site_name(), setting('site_description'), url('/'), route('feed'));
    }

    public function category(string $slug)
    {
        $category = Category::active()->where('slug', $slug)->firstOrFail();
        $posts = Post::published()->forListing()->inCategoryTree($category)->orderByDesc('published_at')->limit(30)->get();

        return $this->respond($posts, $category->name.' - '.site_name(), $category->description ?: setting('site_description'), $category->url(), route('feed.category', $slug));
    }

    private function respond($posts, string $title, ?string $description, string $link, string $self)
    {
        $lastBuild = optional($posts->first())->published_at ?? now();

        return response()
            ->view('front.feed', compact('posts', 'title', 'description', 'link', 'self', 'lastBuild'))
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=600');
    }
}
