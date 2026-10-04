<?php

use App\Models\Post;
use App\Models\PostView;
use Illuminate\Support\Facades\Schedule;

// Housekeeping. Add to crontab:  * * * * * php /path/artisan schedule:run >> /dev/null 2>&1
Schedule::command('cache:prune-stale-tags')->hourly();
Schedule::call(function () {
    // Keep the per-day view table small: 90 days is enough for trending calculations.
    PostView::where('viewed_on', '<', now()->subDays(90)->toDateString())->delete();
})->daily()->name('prune-post-views');
Schedule::call(function () {
    // Scheduled posts become visible automatically (published_at <= now); just bust caches on the hour.
    Post::flushCache();
})->everyFifteenMinutes()->name('flush-home-cache');

Schedule::command('feeds:import')->hourly()->withoutOverlapping();
Schedule::command('posts:ping')->everyFifteenMinutes()->withoutOverlapping();
