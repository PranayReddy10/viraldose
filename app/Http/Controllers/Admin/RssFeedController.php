<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\Setting;
use App\Models\User;
use App\Services\FeedImporter;
use Illuminate\Http\Request;

class RssFeedController extends Controller
{
    public function __construct(private FeedImporter $importer) {}

    public function index()
    {
        return view('admin.feeds.index', ['feeds' => RssFeed::with(['category:id,name', 'user:id,name'])->withCount('posts')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.feeds.form', $this->formData(new RssFeed(['auto_publish' => false, 'import_images' => true, 'fetch_full_content' => true, 'is_active' => true, 'max_items' => 10, 'language' => setting('language', 'en')])));
    }

    public function store(Request $request)
    {
        $feed = RssFeed::create($this->validated($request));

        return redirect()->route('admin.feeds.index')->with('status', 'Feed added. Click “Fetch now” to import the first items.');
    }

    public function edit(RssFeed $feed)
    {
        return view('admin.feeds.form', $this->formData($feed));
    }

    public function update(Request $request, RssFeed $feed)
    {
        $feed->update($this->validated($request));

        return redirect()->route('admin.feeds.index')->with('status', 'Feed updated.');
    }

    public function destroy(RssFeed $feed)
    {
        $feed->posts()->update(['rss_feed_id' => null]);
        $feed->delete();

        return back()->with('status', 'Feed deleted (imported posts were kept).');
    }

    public function fetch(RssFeed $feed)
    {
        $count = $this->importer->import($feed->fresh(), seconds: 40);
        if ($feed->fresh()->last_error) {
            return back()->withErrors(['feed' => 'Fetch failed: '.$feed->fresh()->last_error]);
        }
        $pending = $this->importer->refill($feed, limit: 0)['remaining'];
        $note = $pending ? " {$pending} item(s) still hold only the teaser – click “Fill content” or wait for the hourly run." : '';

        return back()->with('status', "Imported {$count} new item(s) from {$feed->name}.{$note}");
    }

    public function refill(Request $request, RssFeed $feed)
    {
        $result = $this->importer->refill($feed, limit: 15, retry: $request->boolean('retry'), seconds: 45);
        $message = "Filled full content for {$result['done']} post(s) from {$feed->name}.";
        if ($result['failed']) {
            $message .= " {$result['failed']} source page(s) gave no article (paywall, blocked, or JavaScript-only page).";
        }
        if ($result['remaining']) {
            $message .= " {$result['remaining']} still waiting – click again or let the hourly run finish them.";
        }

        return back()->with('status', $message);
    }

    public function preview(Request $request)
    {
        $request->validate(['url' => ['required', 'url']]);
        try {
            $items = array_slice($this->importer->fetch($request->url), 0, 5);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['items' => array_map(fn ($i) => ['title' => $i['title'], 'link' => $i['link'], 'date' => $i['date']?->toDateTimeString(), 'image' => $i['image'], 'chars' => $this->textLength($i['content'] ?: $i['summary'])], $items)]);
    }

    private function textLength(string $html): int
    {
        return mb_strlen(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''));
    }

    private function formData(RssFeed $feed): array
    {
        return [
            'feed' => $feed,
            'categories' => Category::ordered()->get(['id', 'name', 'parent_id']),
            'authors' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'languages' => Setting::languages(),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:500'],
            'category_id' => ['required', 'exists:categories,id'],
            'user_id' => ['required', 'exists:users,id'],
            'language' => ['required', 'string', 'max:10'],
            'max_items' => ['required', 'integer', 'min:1', 'max:50'],
            'auto_publish' => ['nullable', 'boolean'],
            'import_images' => ['nullable', 'boolean'],
            'fetch_full_content' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        foreach (['auto_publish', 'import_images', 'fetch_full_content', 'is_active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }
}
