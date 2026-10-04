<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * GA4 Data API (runReport) – read-only reporting for the dashboard.
 */
class Analytics
{
    public function __construct(private GoogleClient $client) {}

    public function propertyId(): string
    {
        return preg_replace('/\D/', '', (string) setting('ga4_property_id')) ?? '';
    }

    public function isReady(): bool
    {
        return $this->client->isConfigured() && $this->propertyId() !== '';
    }

    /**
     * @param  array<int, string>  $metrics
     * @param  array<int, string>  $dimensions
     * @return array<int, array<string, mixed>>
     */
    public function report(string $start, string $end, array $metrics, array $dimensions = [], int $limit = 20, ?string $orderBy = null): array
    {
        $body = [
            'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
            'metrics' => array_map(fn ($m) => ['name' => $m], $metrics),
            'dimensions' => array_map(fn ($d) => ['name' => $d], $dimensions),
            'limit' => $limit,
        ];
        if ($orderBy) {
            $body['orderBys'] = [['metric' => ['metricName' => $orderBy], 'desc' => true]];
        } elseif (in_array('date', $dimensions, true)) {
            $body['orderBys'] = [['dimension' => ['dimensionName' => 'date']]];
        }
        $r = $this->client->http(GoogleClient::SCOPE_ANALYTICS)
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$this->propertyId()}:runReport", $body);
        if (! $r->successful()) {
            throw new RuntimeException('Analytics API: '.($r->json('error.message') ?? $r->body()), $r->status());
        }
        $rows = [];
        foreach ($r->json('rows', []) as $row) {
            $item = [];
            foreach ($dimensions as $i => $d) {
                $item[$d] = $row['dimensionValues'][$i]['value'] ?? null;
            }
            foreach ($metrics as $i => $m) {
                $item[$m] = (float) ($row['metricValues'][$i]['value'] ?? 0);
            }
            $rows[] = $item;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(int $days = 28): array
    {
        return Cache::remember("ga.overview.{$days}", 3 * 3600, function () use ($days) {
            $start = now()->subDays($days)->toDateString();
            $end = 'today';
            $daily = $this->report($start, $end, ['activeUsers', 'sessions', 'screenPageViews'], ['date'], 100);
            $totals = [
                'users' => (int) array_sum(array_column($daily, 'activeUsers')),
                'sessions' => (int) array_sum(array_column($daily, 'sessions')),
                'pageviews' => (int) array_sum(array_column($daily, 'screenPageViews')),
            ];

            return [
                'daily' => $daily,
                'totals' => $totals,
                'pages' => $this->report($start, $end, ['screenPageViews', 'activeUsers'], ['pagePath', 'pageTitle'], 15, 'screenPageViews'),
                'sources' => $this->report($start, $end, ['sessions'], ['sessionSource'], 10, 'sessions'),
                'countries' => $this->report($start, $end, ['activeUsers'], ['country'], 8, 'activeUsers'),
                'devices' => $this->report($start, $end, ['activeUsers'], ['deviceCategory'], 3, 'activeUsers'),
                'fetched_at' => now()->toDateTimeString(),
            ];
        });
    }
}
