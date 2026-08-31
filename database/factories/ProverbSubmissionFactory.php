<?php

namespace Database\Factories;

use App\Models\ProverbSubmission;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProverbSubmission>
 */
class ProverbSubmissionFactory extends Factory
{
    protected $model = ProverbSubmission::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => RiddleCategory::factory(),
            'question' => fake()->sentence(6) . '…',
            'answer' => fake()->words(3, true),
            'difficulty' => 'medium',
            'source' => fake()->optional()->name(),
            'status' => ProverbSubmission::STATUS_PENDING,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => ProverbSubmission::STATUS_PENDING]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => ProverbSubmission::STATUS_APPROVED]);
    }

    public function rejected(?string $reason = null): static
    {
        return $this->state(fn () => [
            'status' => ProverbSubmission::STATUS_REJECTED,
            'rejection_reason' => $reason ?? fake()->sentence(),
        ]);
    }
}