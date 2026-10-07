<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\SocialShare;
use App\Services\FacebookPublisher;
use App\Services\InstagramPublisher;
use Illuminate\Console\Command;

class ProcessSocialSharesCommand extends Command
{
    protected $signature = 'social:process';

    protected $description = 'Publish finished Instagram containers and auto-share newly published posts';

    public function handle(InstagramPublisher $instagram): int
    {
        if (! $instagram->isReady()) {
            return self::SUCCESS;
        }
        $pending = SocialShare::where('network', 'instagram')->where('status', 'processing')->whereNotNull('creation_id')
            ->where('created_at', '>=', now()->subDay())->get();
        foreach ($pending as $share) {
            try {
                $done = $instagram->publishContainer($share);
                $this->line("#{$share->id} ".($done ? $share->fresh()->status : 'still processing'));
            } catch (\Throwable $e) {
                $share->update(['status' => 'failed', 'response' => $e->getMessage()]);
                $this->error("#{$share->id} failed: ".$e->getMessage());
            }
        }
        // Auto-share posts that went live without passing through the editor (scheduled posts,
        // content agent auto-publish). autoShare() skips anything already shared.
        foreach (['instagram' => $instagram, 'facebook' => app(FacebookPublisher::class)] as $network => $publisher) {
            if (! setting($network.'_auto_share')) {
                continue;
            }
            Post::published()->where('published_at', '>=', now()->subHours(6))
                ->whereDoesntHave('socialShares', fn ($q) => $q->where('network', $network))
                ->orderBy('published_at')->limit(3)->get()
                ->each(fn (Post $post) => $publisher->autoShare($post));
        }

        SocialShare::where('status', 'processing')->where('created_at', '<', now()->subDay())->update(['status' => 'failed', 'response' => 'Timed out waiting for Instagram.']);

        return self::SUCCESS;
    }
}
