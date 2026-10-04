<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Reel;
use App\Services\EmbedRenderer;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReelController extends Controller
{
    public function __construct(private ImageService $images) {}

    public function index(Request $request)
    {
        $reels = Reel::with(['category:id,name', 'post:id,title'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->q.'%'))
            ->ordered()->paginate(24)->withQueryString();

        return view('admin.reels.index', compact('reels'));
    }

    public function create(Request $request)
    {
        $reel = new Reel(['source_type' => 'youtube', 'is_active' => true]);
        if ($request->filled('post')) {
            $post = Post::find($request->post);
            if ($post) {
                $reel->fill(['post_id' => $post->id, 'title' => $post->title, 'caption' => $post->excerpt, 'category_id' => $post->category_id]);
                if ($post->video_url) {
                    $reel->source_type = EmbedRenderer::youtubeId($post->video_url) ? 'youtube' : 'url';
                    $reel->external_url = $post->video_url;
                    $reel->video_path = $reel->source_type === 'url' ? $post->video_url : null;
                }
                if ($post->image && ! $post->video_url) {
                    $reel->thumbnail = $post->image;
                }
            }
        }

        return view('admin.reels.form', $this->formData($reel));
    }

    public function store(Request $request)
    {
        $reel = new Reel(['user_id' => $request->user()->id]);
        $this->fill($reel, $request);
        $reel->save();

        return redirect()->route('admin.reels.index')->with('status', 'Reel added.');
    }

    public function edit(Reel $reel)
    {
        return view('admin.reels.form', $this->formData($reel));
    }

    public function update(Request $request, Reel $reel)
    {
        $this->fill($reel, $request);
        $reel->save();

        return redirect()->route('admin.reels.index')->with('status', 'Reel updated.');
    }

    public function destroy(Reel $reel)
    {
        if ($reel->source_type === 'upload') {
            $this->images->delete($reel->video_path);
        }
        $this->images->delete($reel->thumbnail);
        $reel->delete();

        return back()->with('status', 'Reel deleted.');
    }

    private function formData(Reel $reel): array
    {
        return [
            'reel' => $reel,
            'categories' => Category::ordered()->get(['id', 'name', 'parent_id']),
            'posts' => Post::latest('published_at')->limit(200)->get(['id', 'title']),
        ];
    }

    private function fill(Reel $reel, Request $request): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'source_type' => ['required', Rule::in(array_keys(Reel::SOURCES))],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:102400', 'required_if:source_type,upload'],
            'video_url' => ['nullable', 'url', 'max:500', 'required_if:source_type,url'],
            'external_url' => ['nullable', 'url', 'max:500', 'required_if:source_type,youtube,instagram'],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'post_id' => ['nullable', 'exists:posts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
        ]);

        if ($data['source_type'] === 'upload' && ! $request->hasFile('video') && ! $reel->video_path) {
            abort(422, 'Upload a video file.');
        }
        if ($data['source_type'] === 'youtube' && ! EmbedRenderer::youtubeId($data['external_url'] ?? '')) {
            throw ValidationException::withMessages(['external_url' => 'That is not a YouTube video / Shorts URL.']);
        }
        if ($data['source_type'] === 'instagram' && ! EmbedRenderer::validUrl('instagram', $data['external_url'] ?? '')) {
            throw ValidationException::withMessages(['external_url' => 'That is not an Instagram reel / post URL.']);
        }

        if ($request->hasFile('video')) {
            if ($reel->source_type === 'upload') {
                $this->images->delete($reel->video_path);
            }
            $reel->video_path = $this->images->store($request->file('video'), 'uploads/reels', variants: false);
        } elseif ($data['source_type'] === 'url') {
            $reel->video_path = $data['video_url'];
        }
        if ($data['source_type'] !== 'upload' && $data['source_type'] !== 'url' && $reel->source_type !== $data['source_type']) {
            $reel->video_path = null;
        }
        if ($request->hasFile('thumbnail')) {
            $this->images->delete($reel->thumbnail);
            $reel->thumbnail = $this->images->store($request->file('thumbnail'), 'uploads/reels');
        } elseif (! empty($data['thumbnail_url'])) {
            $reel->thumbnail = $data['thumbnail_url'];
        }

        $reel->fill([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? null,
            'caption' => $data['caption'] ?? null,
            'source_type' => $data['source_type'],
            'external_url' => in_array($data['source_type'], ['youtube', 'instagram'], true) ? $data['external_url'] : null,
            'post_id' => $data['post_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'published_at' => $data['published_at'] ?? $reel->published_at ?? now(),
        ]);
    }
}
