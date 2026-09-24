<?php

namespace Database\Factories;

use App\Models\Riddle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RiddleAttempt>
 */
class RiddleAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'riddle_id' => Riddle::factory(),
            'submitted_answer' => fake()->word(),
            'is_correct' => false,
            'rewarded' => false,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn () => ['is_correct' => true]);
    }
}
