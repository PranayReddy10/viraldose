<?php

namespace App\Console\Commands;

use App\Models\RssFeed;
use App\Services\FeedImporter;
use Illuminate\Console\Command;

class ImportFeedsCommand extends Command
{
    protected $signature = 'feeds:import {--id= : Only this feed id}';

    protected $description = 'Fetch all active RSS feeds and import new items as posts';

    public function handle(FeedImporter $importer): int
    {
        $feeds = RssFeed::where('is_active', true)->when($this->option('id'), fn ($q, $id) => $q->where('id', $id))->get();
        foreach ($feeds as $feed) {
            $count = $importer->import($feed);
            $error = $feed->fresh()->last_error;
            $this->line(sprintf('%-30s %s', $feed->name, $error ? "ERROR: {$error}" : "{$count} new"));
        }

        return self::SUCCESS;
    }
}
