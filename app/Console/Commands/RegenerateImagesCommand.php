<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\ImageService;
use Illuminate\Console\Command;

class RegenerateImagesCommand extends Command
{
    protected $signature = 'images:regenerate {--force : Rebuild even when variants exist}';

    protected $description = 'Generate responsive WebP variants for all locally stored post images';

    public function handle(ImageService $images): int
    {
        $posts = Post::withTrashed()->whereNotNull('image')->where('image', 'not like', 'http%')->get(['id', 'image']);
        $bar = $this->output->createProgressBar($posts->count());
        foreach ($posts as $post) {
            $bar->advance();
            $info = pathinfo($post->image);
            $variant = storage_path('app/public/'.$info['dirname'].'/'.$info['filename'].'-large.webp');
            if (! $this->option('force') && file_exists($variant)) {
                continue;
            }
            $images->generateVariants($post->image);
        }
        $bar->finish();
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
