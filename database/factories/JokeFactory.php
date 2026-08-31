<?php

namespace Database\Factories;

use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Joke>
 */
class JokeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => RiddleCategory::factory(),
            'setup' => fake()->sentence(6) . ':',
            'punchline' => fake()->sentence(4),
            'distractors' => [fake()->sentence(3), fake()->sentence(3), fake()->sentence(3)],
            'source' => null,
            'is_suspended' => false,
            'created_by' => User::factory(),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['is_suspended' => true]);
    }
}