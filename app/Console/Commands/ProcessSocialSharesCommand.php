<?php

namespace App\Console\Commands;

use App\Models\SocialShare;
use App\Services\InstagramPublisher;
use Illuminate\Console\Command;

class ProcessSocialSharesCommand extends Command
{
    protected $signature = 'social:process';

    protected $description = 'Publish Instagram containers (reels) that have finished processing';

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
        SocialShare::where('status', 'processing')->where('created_at', '<', now()->subDay())->update(['status' => 'failed', 'response' => 'Timed out waiting for Instagram.']);

        return self::SUCCESS;
    }
}
