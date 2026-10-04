<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IndexingLog;
use App\Models\Post;
use App\Services\Google\Analytics;
use App\Services\Google\GoogleClient;
use App\Services\Google\SearchConsole;
use App\Services\IndexNow;
use App\Services\SearchEnginePinger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Admin → Google: connection status, Search Console performance, sitemaps,
 * GA4 analytics and the indexing log.
 */
class GoogleController extends Controller
{
    public function __construct(
        private GoogleClient $client,
        private SearchConsole $searchConsole,
        private Analytics $analytics,
        private SearchEnginePinger $pinger,
    ) {}

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'overview');
        $configured = $this->client->isConfigured();
        $errors = [];
        $sc = $ga = null;
        $sitemaps = [];
        $days = in_array((int) $request->query('days'), [7, 28, 90], true) ? (int) $request->query('days') : 28;

        if ($configured) {
            try {
                $sc = $this->searchConsole->overview($days);
            } catch (\Throwable $e) {
                $errors['search_console'] = $e->getMessage();
            }
            if ($this->analytics->isReady()) {
                try {
                    $ga = $this->analytics->overview($days);
                } catch (\Throwable $e) {
                    $errors['analytics'] = $e->getMessage();
                }
            }
            if ($tab === 'sitemaps') {
                try {
                    $sitemaps = $this->searchConsole->sitemaps();
                } catch (\Throwable $e) {
                    $errors['sitemaps'] = $e->getMessage();
                }
            }
        }

        $logs = IndexingLog::with('post:id,title')->latest('created_at')->paginate(40, ['*'], 'logs')->withQueryString();
        $indexCounts = [
            'pass' => Post::where('index_status', 'PASS')->count(),
            'neutral' => Post::where('index_status', 'NEUTRAL')->count(),
            'fail' => Post::where('index_status', 'FAIL')->count(),
            'unchecked' => Post::published()->whereNull('index_status')->count(),
        ];
        $unindexed = Post::published()->forListing()->whereIn('index_status', ['FAIL', 'NEUTRAL'])->orderByDesc('published_at')->limit(20)->get();

        return view('admin.google.index', [
            'tab' => $tab,
            'days' => $days,
            'configured' => $configured,
            'clientEmail' => $this->client->clientEmail(),
            'siteUrl' => $this->searchConsole->siteUrl(),
            'gaReady' => $this->analytics->isReady(),
            'indexNowKey' => IndexNow::key(),
            'sc' => $sc,
            'ga' => $ga,
            'sitemaps' => $sitemaps,
            'apiErrors' => $errors,
            'logs' => $logs,
            'indexCounts' => $indexCounts,
            'unindexed' => $unindexed,
        ]);
    }

    public function test()
    {
        if (! $this->client->isConfigured()) {
            return back()->withErrors(['google' => 'Upload a service account JSON key in Settings → Google first.']);
        }
        $report = [];
        try {
            $sites = collect($this->searchConsole->sites())->pluck('siteUrl')->all();
            $report[] = 'Search Console OK – properties visible: '.(implode(', ', $sites) ?: 'none (add the service account email as a user/owner of the property)');
        } catch (\Throwable $e) {
            $report[] = 'Search Console FAILED – '.$e->getMessage();
        }
        if ($this->analytics->isReady()) {
            try {
                $this->analytics->report(now()->subDays(7)->toDateString(), 'today', ['activeUsers']);
                $report[] = 'Analytics (GA4) OK';
            } catch (\Throwable $e) {
                $report[] = 'Analytics FAILED – '.$e->getMessage();
            }
        } else {
            $report[] = 'Analytics skipped – no GA4 property ID set.';
        }
        Cache::forget('sc.overview.28');
        Cache::forget('ga.overview.28');

        return back()->with('status', implode(' | ', $report));
    }

    public function submitSitemaps()
    {
        $submitted = [];
        foreach ([route('sitemap.index'), route('sitemap.news')] as $sitemap) {
            try {
                $this->searchConsole->submitSitemap($sitemap);
                IndexingLog::record('search_console', 'sitemap', $sitemap, true, 'Submitted');
                $submitted[] = $sitemap;
            } catch (\Throwable $e) {
                IndexingLog::record('search_console', 'sitemap', $sitemap, false, $e->getMessage());

                return back()->withErrors(['google' => 'Sitemap submission failed: '.$e->getMessage()]);
            }
        }

        return redirect()->route('admin.google.index', ['tab' => 'sitemaps'])->with('status', 'Submitted: '.implode(', ', $submitted));
    }

    public function inspect(Post $post)
    {
        try {
            $result = $this->searchConsole->inspect($post->url());
        } catch (\Throwable $e) {
            IndexingLog::record('search_console', 'inspect', $post->url(), false, $e->getMessage(), $post->id);

            return back()->withErrors(['google' => 'URL inspection failed: '.$e->getMessage()]);
        }
        Post::withoutTimestamps(fn () => $post->forceFill([
            'index_status' => $result['verdict'],
            'index_coverage' => $result['coverage'],
            'index_checked_at' => now(),
            'last_crawled_at' => $result['last_crawl'] ? Carbon::parse($result['last_crawl']) : null,
        ])->saveQuietly());
        IndexingLog::record('search_console', 'inspect', $post->url(), true, json_encode($result), $post->id);

        return back()->with('status', 'Google says: '.($result['coverage'] ?: $result['verdict']).($result['last_crawl'] ? ' (last crawled '.Carbon::parse($result['last_crawl'])->diffForHumans().')' : ''))
            ->with('inspection', $result);
    }

    public function requestIndexing(Post $post)
    {
        $results = $this->pinger->notify($post, 'URL_UPDATED', force: true);
        if (! $results) {
            return back()->withErrors(['google' => 'Nothing to do: enable Google Indexing (upload a key) or IndexNow in Settings.']);
        }
        $ok = array_filter($results, fn ($r) => $r === 'ok');
        $failed = array_diff_key($results, $ok);
        $message = 'Submitted to: '.(implode(', ', array_keys($ok)) ?: 'none');
        if ($failed) {
            $message .= '. Failed: '.implode('; ', array_map(fn ($k, $v) => "{$k} ({$v})", array_keys($failed), $failed));
        }

        return back()->with($failed ? 'warning' : 'status', $message);
    }

    public function bulkIndex(Request $request)
    {
        $data = $request->validate(['scope' => ['required', 'in:unindexed,recent,unsubmitted']]);
        $query = Post::published()->with('category:id,slug');
        match ($data['scope']) {
            'unindexed' => $query->whereIn('index_status', ['FAIL', 'NEUTRAL']),
            'recent' => $query->where('published_at', '>=', now()->subDays(2)),
            'unsubmitted' => $query->whereNull('indexing_requested_at'),
        };
        $posts = $query->orderByDesc('published_at')->limit(200)->get(); // Indexing API quota: 200/day
        $count = 0;
        foreach ($posts as $post) {
            $r = $this->pinger->notify($post, 'URL_UPDATED', force: true);
            if (in_array('ok', $r, true)) {
                $count++;
            }
        }

        return back()->with('status', "Submitted {$count} of {$posts->count()} URLs.");
    }

    public function refresh()
    {
        foreach ([7, 28, 90] as $d) {
            Cache::forget("sc.overview.{$d}");
            Cache::forget("ga.overview.{$d}");
        }

        return back()->with('status', 'Google data refreshed.');
    }
}
