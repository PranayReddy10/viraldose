<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sample content for local development / previews. Do not run in production.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([AdminUserSeeder::class, CategorySeeder::class, PageSeeder::class, SettingSeeder::class]);

        $authors = User::factory()->count(3)->create();
        $categories = Category::whereNull('parent_id')->get();
        $tags = Tag::factory()->count(15)->create();

        foreach ($categories as $category) {
            Post::factory()->count(6)->create([
                'user_id' => fn () => $authors->random()->id,
                'category_id' => $category->id,
                'image' => fn () => $this->placeholderImage($category->name, $category->color),
            ])->each(fn (Post $post) => $post->tags()->sync($tags->random(3)->pluck('id')));
        }

        Post::published()->inRandomOrder()->limit(5)->update(['is_slider' => true]);
        Post::published()->where('is_slider', false)->inRandomOrder()->limit(4)->update(['is_featured' => true]);
        Post::published()->inRandomOrder()->limit(4)->update(['is_breaking' => true]);
        Post::published()->inRandomOrder()->limit(6)->update(['is_recommended' => true]);

        Post::published()->inRandomOrder()->limit(10)->get()->each(
            fn (Post $post) => Comment::factory()->count(2)->create(['post_id' => $post->id])
        );
        Post::flushCache();
    }

    /**
     * Generates a local JPEG (gradient + label) so demo content works offline,
     * then builds the responsive WebP variants like a real upload would.
     */
    private function placeholderImage(string $label, string $hex): string
    {
        $dir = 'uploads/demo';
        $disk = Storage::disk('public');
        $disk->makeDirectory($dir);
        $name = Str::slug($label).'-'.Str::lower(Str::random(6)).'.jpg';
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $w = 1200;
        $h = 675;
        $img = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $col = imagecolorallocate($img, (int) ($r * (1 - $t * 0.6)), (int) ($g * (1 - $t * 0.6)), (int) ($b * (1 - $t * 0.6)));
            imageline($img, 0, $y, $w, $y, $col);
        }
        $light = imagecolorallocatealpha($img, 255, 255, 255, 100);
        for ($i = 0; $i < 6; $i++) {
            imagefilledellipse($img, rand(0, $w), rand(0, $h), rand(150, 500), rand(150, 500), $light);
        }
        $white = imagecolorallocate($img, 255, 255, 255);
        $text = strtoupper($label);
        $scale = 6;
        $tw = imagefontwidth(5) * strlen($text) * $scale;
        $th = imagefontheight(5) * $scale;
        $tmp = imagecreatetruecolor(imagefontwidth(5) * strlen($text), imagefontheight(5));
        $bg = imagecolorallocate($tmp, 1, 2, 3);
        imagecolortransparent($tmp, $bg);
        imagefill($tmp, 0, 0, $bg);
        imagestring($tmp, 5, 0, 0, $text, imagecolorallocate($tmp, 255, 255, 255));
        imagecopyresized($img, $tmp, (int) (($w - $tw) / 2), (int) (($h - $th) / 2), 0, 0, $tw, $th, imagesx($tmp), imagesy($tmp));
        imagedestroy($tmp);
        ob_start();
        imagejpeg($img, null, 85);
        $disk->put("{$dir}/{$name}", (string) ob_get_clean());
        imagedestroy($img);
        app(ImageService::class)->generateVariants("{$dir}/{$name}");

        return "{$dir}/{$name}";
    }
}
