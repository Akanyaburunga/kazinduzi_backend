<?php

namespace Database\Factories;

use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Riddle>
 */
class RiddleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => RiddleCategory::factory(),
            'question' => fake()->sentence(8),
            'answer' => fake()->word(),
            'hint' => null,
            'is_suspended' => false,
            'created_by' => User::factory(),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['is_suspended' => true]);
    }
}
