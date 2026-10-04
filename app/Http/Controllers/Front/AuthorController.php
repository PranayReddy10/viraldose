<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Services\Seo;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    public function show(Request $request, Seo $seo, string $slug)
    {
        $author = User::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $page = max(1, (int) $request->integer('page', 1));

        $posts = Post::published()->forListing()->where('user_id', $author->id)
            ->orderByDesc('published_at')
            ->paginate((int) setting('posts_per_page', 12))
            ->withQueryString();

        $seo->title($author->name.($page > 1 ? " - Page {$page}" : ''))
            ->description($author->bio ?: "Articles written by {$author->name} on ".site_name().'.')
            ->canonical($page > 1 ? $author->url().'?page='.$page : $author->url())
            ->pagination($posts->previousPageUrl(), $posts->nextPageUrl())
            ->breadcrumbs([
                ['name' => 'Home', 'url' => url('/')],
                ['name' => $author->name, 'url' => $author->url()],
            ])
            ->addJsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'ProfilePage',
                'mainEntity' => array_filter([
                    '@type' => 'Person',
                    'name' => $author->name,
                    'description' => $author->bio,
                    'url' => $author->url(),
                    'image' => $author->avatarUrl(),
                    'sameAs' => array_values(array_filter([$author->website, $author->twitter])),
                ]),
            ]);

        return view('front.author', compact('author', 'posts'));
    }
}
