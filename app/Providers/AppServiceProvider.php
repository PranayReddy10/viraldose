<?php

namespace App\Providers;

use App\Services\Seo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Seo::class, fn () => new Seo);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);
        Paginator::defaultView('pagination.default');
        Paginator::defaultSimpleView('pagination.simple');

        if ($this->app->isProduction() && config('app.url')) {
            // Shared hosting / proxies: build every URL from APP_URL, never from the request.
            URL::forceRootUrl(config('app.url'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        $this->configureSpacesDisk();
    }

    /**
     * DigitalOcean Spaces credentials entered in Admin → Settings → Storage override .env.
     */
    private function configureSpacesDisk(): void
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests() && in_array($_SERVER['argv'][1] ?? '', ['migrate', 'migrate:fresh', 'key:generate', 'package:discover'], true)) {
            return;
        }
        try {
            $bucket = setting('spaces_bucket');
        } catch (\Throwable) {
            return;
        }
        if (! $bucket) {
            return;
        }
        $secret = (string) setting('spaces_secret');
        try {
            $secret = $secret !== '' ? Crypt::decryptString($secret) : '';
        } catch (\Throwable) {
            // Stored unencrypted (e.g. seeded) – use as is.
        }
        $endpoint = rtrim((string) setting('spaces_endpoint'), '/');
        $cdn = rtrim((string) setting('spaces_cdn_url'), '/');
        config([
            'filesystems.disks.spaces.key' => setting('spaces_key'),
            'filesystems.disks.spaces.secret' => $secret,
            'filesystems.disks.spaces.region' => setting('spaces_region', 'blr1'),
            'filesystems.disks.spaces.bucket' => $bucket,
            'filesystems.disks.spaces.endpoint' => $endpoint,
            'filesystems.disks.spaces.url' => $cdn ?: ($endpoint ? preg_replace('#^https?://#', 'https://'.$bucket.'.', $endpoint) : null),
        ]);
    }
}
