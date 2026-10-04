<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostFile;
use App\Services\Seo;
use App\Support\PostUrl;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * Canonical URL: /{category}/{slug}
     */
    public function show(Request $request, Seo $seo, string $category, string $slug)
    {
        $post = $this->findPublished($slug, $request);
        if (! $post) {
            abort(404);
        }
        // Enforce the canonical URL form (category mismatch or flat format configured).
        if (PostUrl::path($post) !== '/'.$category.'/'.$slug) {
            return redirect($post->url(), 301);
        }

        return $this->render($request, $seo, $post);
    }

    /**
     * Flat URL: /{slug}. Resolves pages first, then posts. If the configured
     * format is "category", a 301 is issued to the canonical URL — this keeps
     * old Varient-style links alive without a redirects table entry.
     */
    public function flat(Request $request, Seo $seo, string $slug)
    {
        $page = Page::active()->where('slug', $slug)->first();
        if ($page) {
            return app(PageController::class)->render($seo, $page);
        }

        $post = $this->findPublished($slug, $request);
        if (! $post) {
            abort(404);
        }
        if (PostUrl::path($post) !== '/'.$slug) {
            return redirect($post->url(), 301);
        }

        return $this->render($request, $seo, $post);
    }

    public function download(PostFile $file)
    {
        abort_unless($file->post && $file->post->isPublished(), 404);
        PostFile::withoutTimestamps(fn () => $file->increment('downloads'));

        return redirect()->away($file->url());
    }

    private function findPublished(string $slug, Request $request): ?Post
    {
        $query = Post::query()->with(['category.parent', 'author', 'tags', 'images', 'files']);
        $user = $request->user();
        if ($user && $user->is_active && $request->has('preview')) {
            // Logged-in staff may preview drafts/scheduled posts.
            return $query->where('slug', $slug)->first();
        }

        return $query->published()->where('slug', $slug)->first();
    }

    private function render(Request $request, Seo $seo, Post $post)
    {
        $seo->forPost($post);
        if (! $post->isPublished()) {
            $seo->noindex(false);
        }

        $related = Post::published()->forListing()
            ->where('id', '!=', $post->id)
            ->where(function ($q) use ($post) {
                $q->where('category_id', $post->category_id);
                if ($post->tags->isNotEmpty()) {
                    $q->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $post->tags->pluck('id')));
                }
            })
            ->orderByDesc('published_at')->limit(6)->get();

        $prev = $next = null;
        if ($post->published_at) {
            $prev = Post::published()->forListing()->where('id', '!=', $post->id)->where('published_at', '<', $post->published_at)->orderByDesc('published_at')->first();
            $next = Post::published()->forListing()->where('id', '!=', $post->id)->where('published_at', '>', $post->published_at)->orderBy('published_at')->first();
        }

        $comments = setting('comments_enabled') && $post->allow_comments ? $post->approvedComments()->get() : collect();

        if ($post->isPublished()) {
            $viewed = (array) session('viewed_posts', []);
            if (! in_array($post->id, $viewed, true)) {
                $post->recordView();
                $viewed[] = $post->id;
                session(['viewed_posts' => array_slice($viewed, -50)]);
            }
        }

        return response()
            ->view('front.post', compact('post', 'related', 'prev', 'next', 'comments'))
            ->setLastModified($post->updated_at);
    }
}
