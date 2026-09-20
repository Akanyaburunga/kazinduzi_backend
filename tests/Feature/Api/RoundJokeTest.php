<?php

namespace Tests\Feature\Api;

use App\Models\Joke;
use App\Models\Round;
use App\Models\RoundItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoundJokeTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    private function makeJokes(int $count): void
    {
        Joke::factory()->count($count)->create([
            'punchline' => 'punchline-'.fake()->unique()->word(),
            'distractors' => ['anya', 'imbwa', 'inzoka'],
        ]);
    }

    private function answerFor(Round $round, int $position): string
    {
        $item = $round->items->firstWhere('position', $position);

        return $item->puzzleModel()->punchline;
    }

    private function playAll(User $user, Round $round, int $correct): void
    {
        $itemCount = $round->items->count();

        for ($i = 0; $i < $itemCount; $i++) {
            $this->actingAs($user)
                ->postJson("/api/games/tuja/rounds/{$round->id}/items/{$i}/answer", [
                    'option' => $i < $correct ? $this->answerFor(Round::findOrFail($round->id), $i) : 'anya',
                ])
                ->assertOk();
        }
    }

    public function test_tuja_pool_is_shuffled_unsolved_capped_at_round_size(): void
    {
        $user = $this->user();
        $this->makeJokes(16);

        $data = $this->actingAs($user)
            ->postJson('/api/games/tuja/rounds')
            ->assertOk()
            ->json('data');

        $this->assertSame(10, $data['round']['item_count']);
        $this->assertSame('joke', $data['item']['type']);

        $round = Round::findOrFail($data['round']['id']);
        $this->assertSame(10, $round->items()->count());
        $this->assertSame(1, Round::count());
        $this->assertSame(0, RoundItem::where('puzzle_type', '!=', 'joke')->count());
    }

    public function test_tuja_round_two_draws_remaining_six_no_duplicates(): void
    {
        $user = $this->user();
        $this->makeJokes(16);

        $first = $this->actingAs($user)
            ->postJson('/api/games/tuja/rounds')
            ->assertOk()
            ->json('data');

        $firstIds = Round::findOrFail($first['round']['id'])->items()->pluck('puzzle_id')->sort()->values();

        $this->playAll($user, Round::findOrFail($first['round']['id']), 10);

        $second = $this->actingAs($user)
            ->postJson('/api/games/tuja/rounds')
            ->assertOk()
            ->json('data');

        $this->assertSame(6, $second['round']['item_count']);

        $secondIds = Round::findOrFail($second['round']['id'])->items()->pluck('puzzle_id')->sort()->values();

        $this->assertSame(6, $secondIds->unique()->count());
        $this->assertSame(0, $secondIds->intersect($firstIds)->count());
    }

    public function test_tuja_never_levels_up_even_with_perfect_score(): void
    {
        $user = $this->user();
        $this->makeJokes(16);

        $data = $this->actingAs($user)
            ->postJson('/api/games/tuja/rounds')
            ->assertOk()
            ->json('data');

        $round = Round::findOrFail($data['round']['id']);

        $this->playAll($user, $round, 10);

        $complete = $this->actingAs($user)
            ->postJson("/api/games/tuja/rounds/{$round->id}/complete")
            ->assertOk()
            ->json('data');

        $this->assertSame(10, $complete['round']['score']);
        $this->assertTrue($complete['round']['completed']);
        $this->assertFalse($complete['round']['has_more_levels']);
        $this->assertNull($complete['round']['next_level']);
        $this->assertFalse($complete['round']['level_available']);
    }

    public function test_tuja_round_payload_never_offers_next_level_mid_round(): void
    {
        $user = $this->user();
        $this->makeJokes(10);

        $data = $this->actingAs($user)
            ->postJson('/api/games/tuja/rounds')
            ->assertOk()
            ->json('data');

        $this->assertFalse($data['round']['has_more_levels']);
        $this->assertNull($data['round']['next_level']);
        $this->assertFalse($data['round']['level_available']);
    }
}