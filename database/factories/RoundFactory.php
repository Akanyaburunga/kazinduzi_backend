<?php

namespace Database\Factories;

use App\Models\Round;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Round>
 */
class RoundFactory extends Factory
{
    protected $model = Round::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mode' => Round::MODE_SOKWE,
            'level' => 1,
            'item_count' => 10,
            'score' => 0,
            'current_streak' => 0,
            'best_streak' => 0,
            'status' => Round::STATUS_ACTIVE,
            'started_at' => now(),
        ];
    }

    public function mode(string $mode): static
    {
        return $this->state(fn () => ['mode' => $mode]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => Round::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function withScore(int $score, ?int $itemCount = 10): static
    {
        return $this->state(fn () => [
            'score' => $score,
            'item_count' => $itemCount,
            'status' => Round::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
