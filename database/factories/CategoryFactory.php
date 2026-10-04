<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word());

        return [
            'name' => $name,
            'description' => fake()->sentence(12),
            'color' => fake()->hexColor(),
            'show_in_menu' => true,
            'show_on_home' => true,
            'is_active' => true,
        ];
    }
}
