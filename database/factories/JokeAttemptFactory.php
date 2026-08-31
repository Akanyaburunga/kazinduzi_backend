<?php

namespace Database\Factories;

use App\Models\Joke;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JokeAttempt>
 */
class JokeAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'joke_id' => Joke::factory(),
            'submitted_answer' => fake()->sentence(2),
            'is_correct' => false,
            'rewarded' => false,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn () => ['is_correct' => true]);
    }
}