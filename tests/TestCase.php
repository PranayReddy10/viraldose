<?php

namespace Tests;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function publishedPost(array $attributes = []): Post
    {
        $category = Category::factory()->create(['name' => 'Sports', 'slug' => 'sports']);

        return Post::factory()->create(array_merge([
            'category_id' => $category->id,
            'title' => 'India wins the final',
            'slug' => 'india-wins-the-final',
            'published_at' => now()->subHour(),
            'views' => 0,
        ], $attributes));
    }
}
