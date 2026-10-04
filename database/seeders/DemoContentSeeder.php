<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

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
                'image' => fn () => 'https://picsum.photos/seed/'.fake()->uuid().'/1200/675',
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
}
