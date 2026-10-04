<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Reel;
use App\Services\Seo;
use Illuminate\Http\Request;

class ReelController extends Controller
{
    public function index(Request $request, Seo $seo)
    {
        abort_unless(setting('reels_enabled', 1), 404);
        $reels = Reel::live()->ordered()->with(['category:id,name,slug,color', 'post:id,slug,category_id', 'post.category:id,slug'])
            ->paginate((int) setting('reels_per_page', 10));

        $seo->title('Reels – short news videos')
            ->description('Watch the latest short news videos, viral clips and reels from '.site_name().'.')
            ->canonical(route('reels.index'))
            ->pagination($reels->previousPageUrl(), $reels->nextPageUrl())
            ->breadcrumbs([['name' => 'Home', 'url' => url('/')], ['name' => 'Reels', 'url' => route('reels.index')]]);
        if ($reels->currentPage() > 1) {
            $seo->canonical(route('reels.index', ['page' => $reels->currentPage()]));
        }

        if ($request->boolean('fragment')) {
            return view('front.reels._items', ['reels' => $reels, 'offset' => ($reels->currentPage() - 1) * $reels->perPage()]);
        }

        return view('front.reels.index', ['reels' => $reels, 'current' => null]);
    }

    public function show(Seo $seo, string $slug)
    {
        abort_unless(setting('reels_enabled', 1), 404);
        $current = Reel::live()->where('slug', $slug)->with(['category:id,name,slug,color', 'post:id,slug,category_id', 'post.category:id,slug'])->firstOrFail();
        Reel::withoutTimestamps(fn () => $current->increment('views'));

        // The requested reel first, then the rest of the feed.
        $others = Reel::live()->ordered()->where('id', '!=', $current->id)
            ->with(['category:id,name,slug,color', 'post:id,slug,category_id', 'post.category:id,slug'])
            ->limit((int) setting('reels_per_page', 10) - 1)->get();
        $reels = collect([$current])->concat($others);

        $seo->title($current->title)
            ->description($current->caption ?: $current->title.' – watch on '.site_name().' Reels.')
            ->canonical($current->url())
            ->type('video.other')
            ->image($current->thumbnailUrl())
            ->breadcrumbs([['name' => 'Home', 'url' => url('/')], ['name' => 'Reels', 'url' => route('reels.index')], ['name' => $current->title, 'url' => $current->url()]])
            ->addJsonLd(array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'VideoObject',
                'name' => $current->title,
                'description' => $current->caption ?: $current->title,
                'thumbnailUrl' => $current->thumbnailUrl() ? [$current->thumbnailUrl()] : null,
                'uploadDate' => $current->published_at?->toIso8601String(),
                'contentUrl' => $current->videoUrl(),
                'embedUrl' => $current->embedUrl(),
                'publisher' => Seo::publisher(),
            ]));

        return view('front.reels.index', ['reels' => $reels, 'current' => $current]);
    }
}
