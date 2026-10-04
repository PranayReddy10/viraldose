<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'body' => fake()->sentence(15),
            'status' => Comment::STATUS_APPROVED,
        ];
    }
}
