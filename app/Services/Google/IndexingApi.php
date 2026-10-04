<?php

namespace App\Services\Google;

use RuntimeException;

class IndexingApi
{
    public function __construct(private GoogleClient $client) {}

    public function isReady(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(string $url, string $type = 'URL_UPDATED'): array
    {
        $r = $this->client->http(GoogleClient::SCOPE_INDEXING)
            ->post('https://indexing.googleapis.com/v3/urlNotifications:publish', ['url' => $url, 'type' => $type]);
        if (! $r->successful()) {
            throw new RuntimeException('Indexing API: '.($r->json('error.message') ?? $r->body()), $r->status());
        }

        return $r->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $url): array
    {
        $r = $this->client->http(GoogleClient::SCOPE_INDEXING)
            ->get('https://indexing.googleapis.com/v3/urlNotifications/metadata', ['url' => $url]);
        if (! $r->successful()) {
            throw new RuntimeException('Indexing API: '.($r->json('error.message') ?? $r->body()), $r->status());
        }

        return $r->json() ?? [];
    }
}
