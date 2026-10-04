<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * IndexNow: instant URL submission to Bing, Yandex, Seznam, Naver (shared protocol).
 */
class IndexNow
{
    public static function key(): string
    {
        $key = (string) setting('indexnow_key');
        if ($key === '') {
            $key = bin2hex(random_bytes(16));
            Setting::set('indexnow_key', $key);
        }

        return $key;
    }

    public static function enabled(): bool
    {
        return (bool) setting('indexnow_enabled', 1);
    }

    /**
     * @param  array<int, string>  $urls
     */
    public function submit(array $urls): int
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if (! $urls) {
            return 0;
        }
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $r = Http::timeout(20)->asJson()->post('https://api.indexnow.org/indexnow', [
            'host' => $host,
            'key' => static::key(),
            'keyLocation' => url('/'.static::key().'.txt'),
            'urlList' => array_slice($urls, 0, 10000),
        ]);
        if (! in_array($r->status(), [200, 202], true)) {
            throw new RuntimeException('IndexNow responded '.$r->status().': '.Str::limit($r->body(), 200), $r->status());
        }

        return $r->status();
    }
}
