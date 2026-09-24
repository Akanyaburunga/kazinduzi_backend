<?php

namespace Database\Factories;

use App\Models\Proverb;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProverbAttempt>
 */
class ProverbAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'proverb_id' => Proverb::factory(),
            'submitted_answer' => fake()->words(2, true),
            'is_correct' => false,
            'rewarded' => false,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn () => ['is_correct' => true]);
    }
}
