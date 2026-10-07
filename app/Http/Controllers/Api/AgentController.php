<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\ImageService;
use App\Services\SeoAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * API used by the scheduled content agent. It can read the site's categories and
 * recent headlines (for internal links and to avoid repeat topics) and create
 * posts. Posts are ALWAYS saved as drafts – an editor reviews and publishes them.
 */
class AgentController extends Controller
{
    public const MIN_WORDS = 400;

    public function __construct(private HtmlSanitizer $sanitizer, private ImageService $images, private SeoAnalyzer $seo) {}

    /** Categories + recent published headlines and pending agent drafts. */
    public function context(): JsonResponse
    {
        $categories = Category::active()->ordered()->get(['id', 'name', 'slug', 'parent_id'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'url' => $c->url()]);

        $recent = Post::published()->with('category:id,slug,parent_id')
            ->orderByDesc('published_at')->limit(100)
            ->get(['id', 'title', 'slug', 'category_id', 'published_at'])
            ->map(fn (Post $p) => ['title' => $p->title, 'url' => $p->url(), 'published_at' => $p->published_at?->toIso8601String()]);

        $drafts = Post::where('created_via', 'agent')->where('status', Post::STATUS_DRAFT)
            ->orderByDesc('id')->limit(30)->get(['id', 'title', 'created_at'])
            ->map(fn (Post $p) => ['id' => $p->id, 'title' => $p->title, 'created_at' => $p->created_at?->toIso8601String()]);

        return response()->json([
            'site' => ['name' => site_name(), 'url' => url('/'), 'language' => setting('language', 'en')],
            'rules' => [
                'status' => 'Every post is saved as a draft for editor review.',
                'min_words' => self::MIN_WORDS,
                'title' => '50-70 characters',
                'meta_description' => '150-160 characters',
                'links' => 'Only links to '.parse_url(url('/'), PHP_URL_HOST).' are kept; other links are removed.',
            ],
            'categories' => $categories,
            'recent_posts' => $recent,
            'pending_drafts' => $drafts,
        ]);
    }

    /** Posts created by the agent (latest first). */
    public function index(Request $request): JsonResponse
    {
        $posts = Post::where('created_via', 'agent')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->with('category:id,slug,parent_id')->orderByDesc('id')->limit(50)
            ->get(['id', 'title', 'slug', 'status', 'category_id', 'published_at', 'created_at']);

        return response()->json(['data' => $posts->map(fn (Post $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'status' => $p->status,
            'url' => $p->isPublished() ? $p->url() : null,
            'created_at' => $p->created_at?->toIso8601String(),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:20', 'max:200'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'category' => ['required'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:500000'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:60'],
            'meta_title' => ['nullable', 'string', 'max:80'],
            'meta_description' => ['nullable', 'string', 'max:200'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'image_base64' => ['nullable', 'string', 'max:7000000'],
            'image_alt' => ['nullable', 'string', 'max:200'],
            'image_caption' => ['nullable', 'string', 'max:300'],
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        $category = Category::active()
            ->where(fn ($q) => $q->where('slug', (string) $data['category'])->orWhere('id', (int) $data['category']))
            ->first();
        if (! $category) {
            throw ValidationException::withMessages(['category' => 'Unknown category. Use a slug or id from GET /api/agent/context.']);
        }

        $content = $this->stripExternalLinks($this->sanitizer->clean($data['content']));
        $words = str_word_count(strip_tags($content));
        if ($words < self::MIN_WORDS) {
            throw ValidationException::withMessages(['content' => "Content has {$words} words; at least ".self::MIN_WORDS.' are required.']);
        }

        // Same headline (or slug) in the last 30 days, published or still waiting as a draft → refuse.
        $slug = $data['slug'] ?? Str::slug($data['title']);
        $duplicate = Post::withTrashed()->where('created_at', '>=', now()->subDays(30))
            ->where(fn ($q) => $q->where('title', $data['title'])->orWhere('slug', $slug))
            ->first(['id', 'title', 'status']);
        if ($duplicate) {
            return response()->json(['message' => 'A post with this title or slug already exists.', 'existing' => $duplicate], 409);
        }

        $post = new Post([
            'title' => $data['title'],
            'slug' => $slug,
            'category_id' => $category->id,
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $content,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
            'image_alt' => $data['image_alt'] ?? null,
            'image_caption' => $data['image_caption'] ?? null,
            'language' => $data['language'] ?? setting('language', 'en'),
            'status' => Post::STATUS_DRAFT,
            'allow_comments' => true,
            'created_via' => 'agent',
        ]);
        $post->user_id = $this->author()->id;

        if (! empty($data['image_base64'])) {
            $post->image = $this->storeImage($data['image_base64'], $slug);
        }
        $post->save();
        $post->tags()->sync(Tag::syncFromString(implode(',', $data['tags'] ?? [])));

        $post->load('category', 'tags');
        $seo = $this->seo->analyze($post);

        return response()->json([
            'id' => $post->id,
            'status' => $post->status,
            'title' => $post->title,
            'slug' => $post->slug,
            'words' => $words,
            'edit_url' => route('admin.posts.edit', $post),
            'future_url' => $post->url(),
            'seo' => ['score' => $seo['score'], 'grade' => $seo['grade'], 'issues' => collect($seo['checks'])->where('status', '!=', 'pass')->values()],
        ], 201);
    }

    private function author(): User
    {
        $id = (int) setting('agent_user_id', 0);

        return User::where('is_active', true)->find($id)
            ?? User::where('is_active', true)->where('role', User::ROLE_ADMIN)->orderBy('id')->firstOrFail();
    }

    private function storeImage(string $base64, string $slug): string
    {
        $base64 = preg_replace('/^data:image\/[a-z+]+;base64,/i', '', trim($base64));
        $bytes = base64_decode($base64, true);
        $info = $bytes ? @getimagesizefromstring($bytes) : false;
        $ext = $info ? (['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime']] ?? null) : null;
        if (! $ext || strlen($bytes) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['image_base64' => 'Send a JPG, PNG or WebP image of at most 5 MB, base64-encoded.']);
        }

        return $this->images->storeBytes('uploads/posts/'.date('Y/m').'/'.Str::limit($slug, 60, '').'-'.Str::random(6).'-card.'.$ext, $bytes);
    }

    /** Keeps only links to this site (and relative links); other anchors become plain text. */
    private function stripExternalLinks(string $html): string
    {
        $host = preg_replace('/^www\./', '', (string) parse_url(url('/'), PHP_URL_HOST));

        return preg_replace_callback('/<a\b[^>]*>(.*?)<\/a>/is', function (array $m) use ($host) {
            if (! preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $m[0], $href)) {
                return $m[1];
            }
            $target = parse_url(html_entity_decode($href[2]), PHP_URL_HOST);
            if ($target === null || $target === false) {
                return str_starts_with(trim($href[2]), '/') ? $m[0] : $m[1];
            }

            return preg_replace('/^www\./', '', strtolower($target)) === $host ? $m[0] : $m[1];
        }, $html);
    }
}
