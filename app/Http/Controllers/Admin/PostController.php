<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostRequest;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\ImageService;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(private ImageService $images, private HtmlSanitizer $sanitizer) {}

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
            ->when($request->filled('trashed'), fn ($q) => $q->onlyTrashed())
            ->latest('updated_at')
            ->paginate(20)->withQueryString();

        $categories = Category::ordered()->get(['id', 'name', 'parent_id']);

        return view('admin.posts.index', compact('posts', 'categories'));
    }

    public function create()
    {
        $post = new Post(['status' => Post::STATUS_DRAFT, 'allow_comments' => true]);

        return view('admin.posts.form', $this->formData($post));
    }

    public function store(PostRequest $request)
    {
        $post = new Post;
        $this->fill($post, $request);
        $post->save();
        $post->tags()->sync(Tag::syncFromString($request->tags));

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Post created.');
    }

    public function edit(Request $request, Post $post)
    {
        $this->authorizePost($request, $post);

        return view('admin.posts.form', $this->formData($post));
    }

    public function update(PostRequest $request, Post $post)
    {
        $this->authorizePost($request, $post);
        $this->fill($post, $request);
        $post->save();
        $post->tags()->sync(Tag::syncFromString($request->tags));

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Post updated.');
    }

    public function destroy(Request $request, Post $post)
    {
        $this->authorizePost($request, $post);
        $post->delete();

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
        $post = Post::onlyTrashed()->findOrFail($id);
        $this->images->delete($post->image);
        $post->forceDelete();

        return back()->with('status', 'Post permanently deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', 'in:publish,draft,trash,feature,unfeature'],
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
        ]);
        $user = $request->user();
        $posts = Post::whereIn('id', $data['ids'])
            ->when(! $user->canManageAllPosts(), fn ($q) => $q->where('user_id', $user->id))->get();

        foreach ($posts as $post) {
            match ($data['action']) {
                'publish' => $post->forceFill(['status' => Post::STATUS_PUBLISHED, 'published_at' => $post->published_at ?? now()])->save(),
                'draft' => $post->forceFill(['status' => Post::STATUS_DRAFT])->save(),
                'trash' => $post->delete(),
                'feature' => $post->forceFill(['is_featured' => true])->save(),
                'unfeature' => $post->forceFill(['is_featured' => false])->save(),
            };
        }

        return back()->with('status', count($posts).' post(s) updated.');
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
            'tagString' => $post->exists ? $post->tags->pluck('name')->implode(', ') : '',
        ];
    }

    private function fill(Post $post, PostRequest $request): void
    {
        $user = $request->user();
        $data = $request->safe()->except(['image', 'image_url', 'remove_image', 'tags', 'user_id']);
        $data['content'] = $this->sanitizer->clean($data['content'] ?? '');

        if (! $post->exists) {
            $post->user_id = $user->id;
        }
        if ($user->canManageAllPosts() && $request->filled('user_id')) {
            $post->user_id = (int) $request->user_id;
        }
        if (! $user->canManageAllPosts()) {
            // Authors cannot pin content to the home page.
            unset($data['is_featured'], $data['is_slider'], $data['is_breaking'], $data['is_recommended']);
        }

        if ($request->boolean('remove_image') && $post->image) {
            $this->images->delete($post->image);
            $post->image = null;
        }
        if ($request->hasFile('image')) {
            if ($post->image) {
                $this->images->delete($post->image);
            }
            $post->image = $this->images->store($request->file('image'), 'uploads/posts');
        } elseif ($request->filled('image_url')) {
            $post->image = $request->image_url;
        }

        $post->fill($data);
    }
}
