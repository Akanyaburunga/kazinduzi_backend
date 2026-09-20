<?php

namespace Tests\Feature\Api;

use App\Models\Riddle;
use App\Models\Round;
use App\Models\RoundItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoundItemStateTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    private function makeRiddles(int $count): void
    {
        Riddle::factory()->count($count)->create(['answer' => 'inkoko']);
    }

    private function startRound(User $user): array
    {
        return $this->actingAs($user)
            ->postJson('/api/games/sokwe/rounds')
            ->assertOk()
            ->json('data');
    }

    private function answerFor(Round $round, int $position): string
    {
        $item = $round->items->firstWhere('position', $position);

        return $item->puzzleModel()->answer;
    }

    public function test_pending_item_returns_no_answer_disclosure(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($user)['round']['id'];
        $round = Round::findOrFail($roundId);

        $response = $this->actingAs($user)
            ->getJson("/api/games/sokwe/rounds/{$roundId}/items/1")
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $response['item']['position']);
        $this->assertSame('riddle', $response['item']['type']);
        $this->assertFalse($response['item']['answered']);
        $this->assertFalse($response['item']['answered_correct']);
        $this->assertNull($response['item']['revealed_answer']);
        $this->assertArrayNotHasKey('answer', $response['item']);
    }

    public function test_solved_item_returns_answered_state_and_revealed_answer(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($user)['round']['id'];
        $round = Round::findOrFail($roundId);

        $this->actingAs($user)
            ->postJson("/api/games/sokwe/rounds/{$roundId}/items/0/answer", ['answer' => $this->answerFor($round, 0)])
            ->assertOk();

        $response = $this->actingAs($user)
            ->getJson("/api/games/sokwe/rounds/{$roundId}/items/0")
            ->assertOk()
            ->json('data');

        $this->assertTrue($response['item']['answered']);
        $this->assertTrue($response['item']['answered_correct']);
        $this->assertSame($this->answerFor($round, 0), $response['item']['revealed_answer']);
        $this->assertSame('solved', RoundItem::find($round->items->firstWhere('position', 0)->id)->status);
    }

    public function test_conceded_item_returns_answered_state_with_reveal(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($user)['round']['id'];
        $round = Round::findOrFail($roundId);

        $this->actingAs($user)
            ->postJson("/api/games/sokwe/rounds/{$roundId}/items/0/answer", ['answer' => 'ndaguhaye'])
            ->assertOk();

        $response = $this->actingAs($user)
            ->getJson("/api/games/sokwe/rounds/{$roundId}/items/0")
            ->assertOk()
            ->json('data');

        $this->assertTrue($response['item']['answered']);
        $this->assertFalse($response['item']['answered_correct']);
        $this->assertSame($this->answerFor($round, 0), $response['item']['revealed_answer']);
    }

    public function test_unanswered_position_is_found_regardless_of_completion_state(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($user)['round']['id'];
        $round = Round::findOrFail($roundId);

        $this->actingAs($user)
            ->getJson("/api/games/sokwe/rounds/{$roundId}/items/9")
            ->assertOk()
            ->assertJsonPath('data.item.position', 9);
    }

    public function test_unknown_position_returns_404(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($user)['round']['id'];

        $this->actingAs($user)
            ->getJson("/api/games/sokwe/rounds/{$roundId}/items/10")
            ->assertStatus(404);
    }

    public function test_foreign_round_is_forbidden_and_wrong_mode_returns_404(): void
    {
        $owner = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($owner)['round']['id'];

        $this->actingAs($this->user())
            ->getJson("/api/games/sokwe/rounds/{$roundId}/items/0")
            ->assertStatus(403);

        $this->actingAs($owner)
            ->getJson("/api/games/hera/rounds/{$roundId}/items/0")
            ->assertStatus(404);
    }
}