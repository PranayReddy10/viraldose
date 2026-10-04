<?php

namespace Database\Factories;

use App\Models\Reel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reel>
 */
class ReelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => rtrim(fake()->unique()->sentence(6), '.'),
            'caption' => fake()->sentence(12),
            'source_type' => 'youtube',
            'external_url' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'is_active' => true,
            'published_at' => now()->subMinutes(fake()->numberBetween(1, 5000)),
            'views' => fake()->numberBetween(0, 20000),
        ];
    }
}
