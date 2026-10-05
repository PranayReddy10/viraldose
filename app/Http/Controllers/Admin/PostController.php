<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostRequest;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostFile;
use App\Models\PostImage;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Services\Google\GoogleClient;
use App\Services\HtmlSanitizer;
use App\Services\ImageService;
use App\Services\IndexNow;
use App\Services\InstagramPublisher;
use App\Services\SearchEnginePinger;
use App\Services\SeoAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function __construct(
        private ImageService $images,
        private HtmlSanitizer $sanitizer,
        private SearchEnginePinger $pinger,
        private SeoAnalyzer $seo,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $posts = Post::query()->with(['category:id,name,slug', 'author:id,name'])
            ->when(! $user->canManageAllPosts(), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('q'), fn ($q) => $q->search($request->q))
            ->when($request->filled('status'), function ($q) use ($request) {
                if ($request->status === 'scheduled') {
                    return $q->where('status', Post::STATUS_PUBLISHED)->where('published_at', '>', now());
                }

                return $q->where('status', $request->status);
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->category))
            ->when($request->filled('index'), fn ($q) => $request->index === 'unchecked' ? $q->whereNull('index_status') : $q->where('index_status', strtoupper($request->index)))
            ->when($request->filled('trashed'), fn ($q) => $q->onlyTrashed())
            ->latest('updated_at')
            ->paginate(20)->withQueryString();

        $categories = Category::ordered()->get(['id', 'name', 'parent_id']);

        return view('admin.posts.index', compact('posts', 'categories'));
    }

    public function create()
    {
        $post = new Post(['status' => Post::STATUS_DRAFT, 'allow_comments' => true, 'language' => setting('language', 'en')]);

        return view('admin.posts.form', $this->formData($post));
    }

    public function store(PostRequest $request)
    {
        $post = new Post;
        $this->fill($post, $request);
        $post->save();
        $post->tags()->sync(Tag::syncFromString($request->tags));
        $this->storeAttachments($post, $request);
        $this->afterSave($post, false);

        return redirect()->route('admin.posts.edit', $post)->with('status', $post->isPublished() ? 'Post published.' : 'Post saved.');
    }

    public function edit(Request $request, Post $post)
    {
        $this->authorizePost($request, $post);

        return view('admin.posts.form', $this->formData($post->load(['images', 'files', 'tags'])));
    }

    public function update(PostRequest $request, Post $post)
    {
        $this->authorizePost($request, $post);
        $wasPublished = $post->isPublished();
        $this->fill($post, $request);
        $post->save();
        $post->tags()->sync(Tag::syncFromString($request->tags));
        $this->storeAttachments($post, $request);
        $this->afterSave($post, $wasPublished);

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Post updated.');
    }

    public function destroy(Request $request, Post $post)
    {
        $this->authorizePost($request, $post);
        $wasPublished = $post->isPublished();
        $post->delete();
        if ($wasPublished) {
            try {
                $this->pinger->notify($post, 'URL_DELETED');
            } catch (\Throwable) {
            }
        }

        return redirect()->route('admin.posts.index')->with('status', 'Post moved to trash.');
    }

    public function restore(Request $request, int $id)
    {
        $post = Post::onlyTrashed()->findOrFail($id);
        $this->authorizePost($request, $post);
        $post->restore();

        return back()->with('status', 'Post restored.');
    }

    public function forceDelete(Request $request, int $id)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $post = Post::onlyTrashed()->with(['images', 'files'])->findOrFail($id);
        $this->images->delete($post->image);
        foreach ($post->images as $img) {
            $this->images->delete($img->path);
        }
        foreach ($post->files as $file) {
            $this->images->delete($file->path);
        }
        $post->forceDelete();

        return back()->with('status', 'Post permanently deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', 'in:publish,draft,trash,feature,unfeature,index'],
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
        ]);
        $user = $request->user();
        $posts = Post::whereIn('id', $data['ids'])
            ->when(! $user->canManageAllPosts(), fn ($q) => $q->where('user_id', $user->id))->get();

        foreach ($posts as $post) {
            match ($data['action']) {
                'publish' => tap($post->forceFill(['status' => Post::STATUS_PUBLISHED, 'published_at' => $post->published_at ?? now()]))->save() && $this->pinger->notify($post),
                'draft' => $post->forceFill(['status' => Post::STATUS_DRAFT])->save(),
                'trash' => $post->delete(),
                'feature' => $post->forceFill(['is_featured' => true])->save(),
                'unfeature' => $post->forceFill(['is_featured' => false])->save(),
                'index' => $post->isPublished() ? $this->pinger->notify($post, 'URL_UPDATED', force: true) : null,
            };
        }

        return back()->with('status', count($posts).' post(s) updated.');
    }

    public function destroyImage(Request $request, PostImage $image)
    {
        $this->authorizePost($request, $image->post);
        $this->images->delete($image->path);
        $image->delete();

        return back()->with('status', 'Image removed.');
    }

    public function destroyFile(Request $request, PostFile $file)
    {
        $this->authorizePost($request, $file->post);
        $this->images->delete($file->path);
        $file->delete();

        return back()->with('status', 'File removed.');
    }

    private function authorizePost(Request $request, Post $post): void
    {
        $user = $request->user();
        abort_unless($user->canManageAllPosts() || $post->user_id === $user->id, 403);
    }

    private function formData(Post $post): array
    {
        return [
            'post' => $post,
            'categories' => Category::ordered()->with('children')->topLevel()->get(),
            'authors' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'languages' => Setting::languages(),
            'tagString' => $post->exists ? $post->tags->pluck('name')->implode(', ') : '',
            'seo' => $post->exists ? $this->seo->analyze($post) : null,
            'googleReady' => app(GoogleClient::class)->isConfigured(),
            'indexNowReady' => IndexNow::enabled(),
            'indexingLogs' => $post->exists ? $post->indexingLogs()->limit(8)->get() : collect(),
            'instagram' => app(InstagramPublisher::class),
            'shares' => $post->exists ? $post->socialShares()->limit(5)->get() : collect(),
        ];
    }

    private function fill(Post $post, PostRequest $request): void
    {
        $user = $request->user();
        $data = $request->safe()->except(['image', 'image_url', 'remove_image', 'tags', 'user_id', 'gallery', 'files', 'save_as', 'scheduled']);
        $data['content'] = $this->sanitizer->clean($data['content'] ?? '');

        // "Save as draft" / "Publish" buttons override the status select.
        if ($request->input('save_as') === 'draft') {
            $data['status'] = Post::STATUS_DRAFT;
        } elseif ($request->input('save_as') === 'publish') {
            $data['status'] = Post::STATUS_PUBLISHED;
        }
        if (! $request->boolean('scheduled') && $data['status'] === Post::STATUS_PUBLISHED) {
            $current = $post->exists ? $post->published_at : null;
            $requested = ! empty($data['published_at']) ? Carbon::parse($data['published_at']) : $current;
            // Not scheduled: publish now unless an existing past date should be kept.
            $data['published_at'] = ($requested && $requested->lte(now())) ? $requested : now();
        }

        if (! $post->exists) {
            $post->user_id = $user->id;
        }
        if ($user->canManageAllPosts() && $request->filled('user_id')) {
            $post->user_id = (int) $request->user_id;
        }
        if (! $user->canManageAllPosts()) {
            unset($data['is_featured'], $data['is_slider'], $data['is_breaking'], $data['is_recommended']);
        }

        $removed = null;
        if ($request->boolean('remove_image') && $post->image) {
            $removed = $post->image;
            $this->images->delete($post->image);
            $post->image = null;
        }
        if ($request->hasFile('image')) {
            $old = $post->image;
            $post->image = $this->images->store($request->file('image'), 'uploads/posts'); // throws on failure, old image untouched
            if ($old && $old !== $post->image) {
                $this->images->delete($old);
            }
        } elseif ($request->filled('image_url') && $request->image_url !== $post->image && $request->image_url !== $removed) {
            $post->image = $request->image_url;
        }

        $post->fill($data);
    }

    private function storeAttachments(Post $post, PostRequest $request): void
    {
        foreach ($request->file('gallery', []) as $i => $file) {
            if ($file && $file->isValid()) {
                $post->images()->create([
                    'path' => $this->images->store($file, 'uploads/gallery'),
                    'sort_order' => $post->images()->count() + $i,
                ]);
            }
        }
        foreach ($request->file('files', []) as $file) {
            if ($file && $file->isValid()) {
                $post->files()->create([
                    'path' => $this->images->store($file, 'uploads/files', variants: false),
                    'name' => Str::limit($file->getClientOriginalName(), 200, ''),
                    'size' => $file->getSize(),
                ]);
            }
        }
        if (is_array($request->input('image_captions'))) {
            foreach ($request->input('image_captions') as $id => $caption) {
                $post->images()->where('id', $id)->update(['caption' => Str::limit((string) $caption, 300, '')]);
            }
        }
    }

    private function afterSave(Post $post, bool $wasPublished): void
    {
        try {
            $this->pinger->notifyIfJustPublished($post->fresh(['category']), $wasPublished);
        } catch (\Throwable) {
            // Never block saving on a search-engine API hiccup; the log has the details.
        }
        if (! $wasPublished && $post->isPublished() && setting('instagram_auto_share')) {
            try {
                $instagram = app(InstagramPublisher::class);
                if ($instagram->isReady() && ! $post->socialShares()->where('network', 'instagram')->where('status', 'published')->exists()) {
                    $instagram->shareImage($post->fresh(['category']));
                }
            } catch (\Throwable) {
            }
        }
    }
}
