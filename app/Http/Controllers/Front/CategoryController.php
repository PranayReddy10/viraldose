<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Services\Seo;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Request $request, Seo $seo, string $slug)
    {
        $category = Category::active()->where('slug', $slug)->with(['parent', 'children' => fn ($q) => $q->active()])->firstOrFail();
        $page = max(1, (int) $request->integer('page', 1));

        $posts = Post::published()->forListing()->inCategoryTree($category)
            ->orderByDesc('published_at')
            ->paginate((int) setting('posts_per_page', 12))
            ->withQueryString();

        if ($page > 1 && $posts->isEmpty()) {
            abort(404);
        }

        $seo->forCategory($category, $page)
            ->pagination($posts->previousPageUrl(), $posts->nextPageUrl());

        return view('front.category', compact('category', 'posts'));
    }
}
