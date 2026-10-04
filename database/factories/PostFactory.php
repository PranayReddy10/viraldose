<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $paragraphs = collect(fake()->paragraphs(6))->map(fn ($p) => "<p>{$p}</p>")->implode("\n");

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => rtrim(fake()->unique()->sentence(8), '.'),
            'excerpt' => fake()->sentence(20),
            'content' => '<p><strong>'.fake()->sentence(10)."</strong></p>\n<h2>".fake()->sentence(4)."</h2>\n".$paragraphs,
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'views' => fake()->numberBetween(0, 5000),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Post::STATUS_DRAFT, 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['status' => Post::STATUS_PUBLISHED, 'published_at' => now()->addDays(2)]);
    }
}
