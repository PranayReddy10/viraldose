<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class SearchConsole
{
    public function __construct(private GoogleClient $client) {}

    public function siteUrl(): string
    {
        $site = (string) setting('google_sc_site_url');
        if ($site === '') {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST);
            $site = 'sc-domain:'.preg_replace('/^www\./', '', (string) $host);
        }

        return $site;
    }

    public function isReady(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sites(): array
    {
        $r = $this->client->http(GoogleClient::SCOPE_WEBMASTERS)->get('https://www.googleapis.com/webmasters/v3/sites');
        $this->guard($r);

        return $r->json('siteEntry', []);
    }

    /**
     * Search analytics rows for a date range.
     *
     * @param  array<int, string>  $dimensions  e.g. ['date'] | ['query'] | ['page']
     * @return array<int, array<string, mixed>>
     */
    public function query(string $start, string $end, array $dimensions = ['date'], int $limit = 25, ?string $pageFilter = null): array
    {
        $body = [
            'startDate' => $start,
            'endDate' => $end,
            'dimensions' => $dimensions,
            'rowLimit' => $limit,
            'dataState' => 'all',
        ];
        if ($pageFilter) {
            $body['dimensionFilterGroups'] = [['filters' => [['dimension' => 'page', 'operator' => 'equals', 'expression' => $pageFilter]]]];
        }
        $r = $this->client->http(GoogleClient::SCOPE_WEBMASTERS)
            ->post('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($this->siteUrl()).'/searchAnalytics/query', $body);
        $this->guard($r);

        return collect($r->json('rows', []))->map(fn ($row) => [
            'key' => $row['keys'][0] ?? '',
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => round(($row['ctr'] ?? 0) * 100, 1),
            'position' => round($row['position'] ?? 0, 1),
        ])->all();
    }

    /**
     * Cached dashboard bundle: daily series, totals, top queries and pages.
     *
     * @return array<string, mixed>
     */
    public function overview(int $days = 28): array
    {
        return Cache::remember("sc.overview.{$days}", 6 * 3600, function () use ($days) {
            // Search Console data lags ~2 days.
            $end = now()->subDays(2)->toDateString();
            $start = now()->subDays($days + 1)->toDateString();
            $daily = $this->query($start, $end, ['date'], 100);
            usort($daily, fn ($a, $b) => strcmp($a['key'], $b['key']));
            $totals = [
                'clicks' => array_sum(array_column($daily, 'clicks')),
                'impressions' => array_sum(array_column($daily, 'impressions')),
            ];
            $totals['ctr'] = $totals['impressions'] ? round($totals['clicks'] / $totals['impressions'] * 100, 1) : 0;
            $positions = array_filter(array_column($daily, 'position'));
            $totals['position'] = $positions ? round(array_sum($positions) / count($positions), 1) : 0;

            return [
                'start' => $start,
                'end' => $end,
                'daily' => $daily,
                'totals' => $totals,
                'queries' => $this->query($start, $end, ['query'], 15),
                'pages' => $this->query($start, $end, ['page'], 15),
                'fetched_at' => now()->toDateTimeString(),
            ];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sitemaps(): array
    {
        $r = $this->client->http(GoogleClient::SCOPE_WEBMASTERS)
            ->get('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($this->siteUrl()).'/sitemaps');
        $this->guard($r);

        return $r->json('sitemap', []);
    }

    public function submitSitemap(string $sitemapUrl): void
    {
        // The API wants an empty body. Laravel's default JSON body would be "[]", which Google
        // rejects with "Invalid JSON payload received … Root element must be a message".
        $r = $this->client->http(GoogleClient::SCOPE_WEBMASTERS)
            ->withBody('', 'application/json')
            ->put('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($this->siteUrl()).'/sitemaps/'.rawurlencode($sitemapUrl));
        $this->guard($r);
    }

    /**
     * URL Inspection API: live index status of one URL.
     *
     * @return array<string, mixed>
     */
    public function inspect(string $url): array
    {
        $r = $this->client->http(GoogleClient::SCOPE_WEBMASTERS)
            ->post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
                'inspectionUrl' => $url,
                'siteUrl' => $this->siteUrl(),
                'languageCode' => 'en-US',
            ]);
        $this->guard($r);
        $index = $r->json('inspectionResult.indexStatusResult', []);

        return [
            'verdict' => $index['verdict'] ?? 'UNKNOWN',               // PASS | NEUTRAL | FAIL
            'coverage' => $index['coverageState'] ?? null,            // e.g. "Submitted and indexed"
            'indexing_state' => $index['indexingState'] ?? null,
            'robots' => $index['robotsTxtState'] ?? null,
            'fetch' => $index['pageFetchState'] ?? null,
            'last_crawl' => $index['lastCrawlTime'] ?? null,
            'google_canonical' => $index['googleCanonical'] ?? null,
            'user_canonical' => $index['userCanonical'] ?? null,
            'crawled_as' => $index['crawledAs'] ?? null,
            'mobile' => $r->json('inspectionResult.mobileUsabilityResult.verdict'),
            'rich_results' => $r->json('inspectionResult.richResultsResult.verdict'),
            'link' => $r->json('inspectionResult.inspectionResultLink'),
        ];
    }

    private function guard($response): void
    {
        if ($response->successful()) {
            return;
        }
        $message = $response->json('error.message') ?? $response->body();
        throw new RuntimeException('Search Console API: '.$message, $response->status());
    }
}
