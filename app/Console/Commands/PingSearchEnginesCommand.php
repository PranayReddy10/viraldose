<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\SearchEnginePinger;
use Illuminate\Console\Command;

/**
 * Scheduled posts go live without a request hitting the admin, so the
 * scheduler submits any newly visible article that has not been pinged yet.
 */
class PingSearchEnginesCommand extends Command
{
    protected $signature = 'posts:ping {--all : Re-submit every published post (respects the 200/day Google quota)}';

    protected $description = 'Submit newly published posts to Google Indexing API and IndexNow';

    public function handle(SearchEnginePinger $pinger): int
    {
        $query = Post::published()->with('category:id,slug')->where('noindex', false);
        if (! $this->option('all')) {
            $query->whereNull('indexing_requested_at')->where('published_at', '>=', now()->subDay());
        }
        $posts = $query->orderByDesc('published_at')->limit(200)->get();
        foreach ($posts as $post) {
            $r = $pinger->notify($post);
            $this->line($post->slug.' => '.json_encode($r));
        }
        $this->info("Processed {$posts->count()} posts.");

        return self::SUCCESS;
    }
}
