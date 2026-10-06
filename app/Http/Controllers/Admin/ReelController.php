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
                    $reel->source_type = 'image';
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
        return view('admin.reels.form', $this->formData($reel) + ['shares' => $reel->socialShares()->latest()->limit(5)->get()]);
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
        // The YouTube and Instagram boxes have their own field names (one shared name let the
        // hidden, empty Instagram box overwrite the YouTube link). Use the box for the chosen source.
        $box = ['youtube' => 'youtube_url', 'instagram' => 'instagram_url'][$request->input('source_type')] ?? null;
        if ($box && $request->filled($box)) {
            $link = trim((string) $request->input($box));
            if (! preg_match('#^https?://#i', $link)) {
                $link = 'https://'.ltrim($link, '/'); // pasted without the scheme, e.g. "youtube.com/shorts/…"
            }
            $request->merge(['external_url' => $link]);
        }
        $errorField = $box ?? 'external_url';

        try {
            $data = $this->validateReel($request);
        } catch (ValidationException $e) {
            // Show link errors under the box the editor actually typed in.
            $errors = $e->errors();
            if ($errorField !== 'external_url' && isset($errors['external_url'])) {
                $errors[$errorField] = str_replace(['external url', 'external_url'], $errorField === 'youtube_url' ? 'YouTube URL' : 'Instagram URL', $errors['external_url']);
                unset($errors['external_url']);
            }
            throw ValidationException::withMessages($errors);
        }

        $this->applyReel($reel, $request, $data, $errorField);
    }

    private function validateReel(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'source_type' => ['required', Rule::in(array_keys(Reel::SOURCES))],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:102400', 'required_if:source_type,upload'],
            'video_url' => ['nullable', 'url', 'max:500', 'required_if:source_type,url'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:8192'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'external_url' => ['nullable', 'url', 'max:500', 'required_if:source_type,youtube,instagram'],
            'youtube_url' => ['nullable', 'string', 'max:500'],
            'instagram_url' => ['nullable', 'string', 'max:500'],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'post_id' => ['nullable', 'exists:posts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    private function applyReel(Reel $reel, Request $request, array $data, string $errorField): void
    {
        if ($data['source_type'] === 'upload' && ! $request->hasFile('video') && ! $reel->video_path) {
            throw ValidationException::withMessages(['video' => 'Upload a video file.']);
        }
        if ($data['source_type'] === 'image' && ! $request->hasFile('image') && empty($data['image_url']) && ! $reel->thumbnail) {
            throw ValidationException::withMessages(['image' => 'Upload a photo or give its URL.']);
        }
        if ($data['source_type'] === 'youtube' && ! EmbedRenderer::youtubeId($data['external_url'] ?? '')) {
            throw ValidationException::withMessages([$errorField => 'That is not a YouTube video / Shorts link. Paste a link like https://youtube.com/shorts/abc123XYZ_0']);
        }
        if ($data['source_type'] === 'instagram' && ! EmbedRenderer::validUrl('instagram', $data['external_url'] ?? '')) {
            throw ValidationException::withMessages([$errorField => 'That is not an Instagram reel / post link. Paste a link like https://www.instagram.com/reel/ABC123/']);
        }

        if ($request->hasFile('video')) {
            $oldVideo = $reel->source_type === 'upload' ? $reel->video_path : null;
            $reel->video_path = $this->images->store($request->file('video'), 'uploads/reels', variants: false);
            $this->images->delete($oldVideo);
        } elseif ($data['source_type'] === 'url') {
            $reel->video_path = $data['video_url'];
        }
        if ($data['source_type'] !== 'upload' && $data['source_type'] !== 'url' && $reel->source_type !== $data['source_type']) {
            $reel->video_path = null;
        }
        if ($data['source_type'] === 'image' && $request->hasFile('image')) {
            $oldThumb = $reel->thumbnail;
            $reel->thumbnail = $this->images->store($request->file('image'), 'uploads/reels');
            $this->images->delete($oldThumb);
        } elseif ($data['source_type'] === 'image' && ! empty($data['image_url'])) {
            $reel->thumbnail = $data['image_url'];
        } elseif ($request->hasFile('thumbnail')) {
            $oldThumb = $reel->thumbnail;
            $reel->thumbnail = $this->images->store($request->file('thumbnail'), 'uploads/reels');
            $this->images->delete($oldThumb);
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
