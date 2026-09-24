<?php

namespace Database\Factories;

use App\Models\JokeSubmission;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JokeSubmission>
 */
class JokeSubmissionFactory extends Factory
{
    protected $model = JokeSubmission::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => RiddleCategory::factory(),
            'setup' => fake()->sentence(6) . '…',
            'punchline' => fake()->words(4, true),
            'source' => fake()->optional()->name(),
            'status' => JokeSubmission::STATUS_PENDING,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => JokeSubmission::STATUS_PENDING]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => JokeSubmission::STATUS_APPROVED]);
    }

    public function rejected(?string $reason = null): static
    {
        return $this->state(fn () => [
            'status' => JokeSubmission::STATUS_REJECTED,
            'rejection_reason' => $reason ?? fake()->sentence(),
        ]);
    }
}