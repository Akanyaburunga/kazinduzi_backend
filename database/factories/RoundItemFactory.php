<?php

namespace Database\Factories;

use App\Models\Riddle;
use App\Models\Round;
use App\Models\RoundItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoundItem>
 */
class RoundItemFactory extends Factory
{
    protected $model = RoundItem::class;

    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'puzzle_type' => RoundItem::PUZZLE_RIDDLE,
            'puzzle_id' => Riddle::factory(),
            'position' => 0,
            'status' => RoundItem::STATUS_PENDING,
            'is_correct' => false,
            'attempts' => 0,
        ];
    }

    public function position(int $position): static
    {
        return $this->state(fn () => ['position' => $position]);
    }

    public function solved(): static
    {
        return $this->state(fn () => [
            'status' => RoundItem::STATUS_SOLVED,
            'is_correct' => true,
            'answered_at' => now(),
        ]);
    }

    public function conceded(): static
    {
        return $this->state(fn () => [
            'status' => RoundItem::STATUS_CONCEDED,
            'is_correct' => false,
            'answered_at' => now(),
        ]);
    }
}
