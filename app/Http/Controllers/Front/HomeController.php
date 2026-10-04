<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Services\Seo;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function __invoke(Seo $seo)
    {
        $seo->forHome();

        $data = Cache::remember('home.data', 300, function () {
            $slider = Post::published()->forListing()->where('is_slider', true)->orderByDesc('published_at')->limit(5)->get();
            $featured = Post::published()->forListing()->where('is_featured', true)
                ->whereNotIn('id', $slider->pluck('id'))->orderByDesc('published_at')->limit(4)->get();

            $exclude = $slider->pluck('id')->merge($featured->pluck('id'))->all();
            $latest = Post::published()->forListing()->whereNotIn('id', $exclude)->orderByDesc('published_at')->limit(10)->get();

            $categories = Category::active()->where('show_on_home', true)->ordered()->with('children:id,parent_id')->limit(6)->get();
            $sections = $categories->map(function (Category $category) {
                return [
                    'category' => $category,
                    'posts' => Post::published()->forListing()->inCategoryTree($category)->orderByDesc('published_at')->limit(5)->get(),
                ];
            })->filter(fn ($s) => $s['posts']->isNotEmpty())->values();

            $recommended = Post::published()->forListing()->where('is_recommended', true)->orderByDesc('published_at')->limit(6)->get();

            return compact('slider', 'featured', 'latest', 'sections', 'recommended');
        });

        if ($data['slider']->isEmpty() && $data['featured']->isEmpty()) {
            // Fresh install: promote the latest posts into the hero so the home page is never empty.
            $data['slider'] = $data['latest']->take(3);
            $data['latest'] = $data['latest']->slice(3)->values();
        }

        return view('front.home', $data);
    }
}
