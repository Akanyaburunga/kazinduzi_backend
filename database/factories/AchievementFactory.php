<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Achievement>
 */
class AchievementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(3);

        return [
            'slug' => $slug,
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => 'solved',
            'metric' => 'solved',
            'threshold' => 1,
            'icon' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
