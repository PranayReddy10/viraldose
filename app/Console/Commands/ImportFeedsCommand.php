<?php

namespace App\Console\Commands;

use App\Models\RssFeed;
use App\Services\FeedImporter;
use Illuminate\Console\Command;

class ImportFeedsCommand extends Command
{
    protected $signature = 'feeds:import {--id= : Only this feed id} {--retry : Retry posts whose source page gave no article last time}';

    protected $description = 'Fetch all active RSS feeds and import new items as posts';

    public function handle(FeedImporter $importer): int
    {
        $feeds = RssFeed::where('is_active', true)->when($this->option('id'), fn ($q, $id) => $q->where('id', $id))->get();
        foreach ($feeds as $feed) {
            $count = $importer->import($feed);
            $error = $feed->fresh()->last_error;
            $filled = $feed->fetch_full_content ? $importer->refill($feed, limit: 20, retry: (bool) $this->option('retry')) : null;
            $this->line(sprintf('%-30s %s%s', $feed->name, $error ? "ERROR: {$error}" : "{$count} new", $filled ? ", {$filled['done']} filled, {$filled['remaining']} pending" : ''));
        }

        return self::SUCCESS;
    }
}
