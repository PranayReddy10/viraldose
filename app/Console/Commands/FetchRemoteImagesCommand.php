<?php

namespace App\Console\Commands;

use App\Services\RemoteImageFetcher;
use Illuminate\Console\Command;

class FetchRemoteImagesCommand extends Command
{
    protected $signature = 'images:fetch-remote {--limit=10 : Posts per run} {--retry : Retry images that failed before}';

    protected $description = 'Download hot-linked featured images (http URLs) into local storage with WebP variants';

    public function handle(RemoteImageFetcher $fetcher): int
    {
        $result = $fetcher->run((int) $this->option('limit'), (bool) $this->option('retry'));
        foreach ($result['lines'] as $line) {
            $this->line($line);
        }
        $this->info("Downloaded {$result['done']}, failed {$result['failed']}, remaining {$result['remaining']}.");

        return self::SUCCESS;
    }
}
