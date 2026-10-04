<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->unique()->sentence(3), '.'),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'is_active' => true,
        ];
    }
}
