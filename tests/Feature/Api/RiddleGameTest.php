<?php

namespace Tests\Feature\Api;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiddleGameTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create();
    }

    private function makeRiddle(array $overrides = []): Riddle
    {
        $category = RiddleCategory::factory()->create();

        return Riddle::factory()->create(array_merge([
            'category_id' => $category->id,
            'question' => 'Ikintu kingana n’urugo kikongana n’inzu?',
            'answer' => 'Inkerebuzo',
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/riddles')->assertStatus(401);
    }

    public function test_list_riddles_does_not_expose_the_answer(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->makeRiddle();

        $response = $this->getJson('/api/riddles')->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertArrayNotHasKey('answer', $data[0]);
        $this->assertArrayHasKey('question', $data[0]);
    }

    public function test_game_payload_surfaces_difficulty_and_hints_but_not_answer_or_source(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->makeRiddle([
            'difficulty' => 'medium',
            'hint' => 'first clue',
            'hint2' => 'second clue',
            'source' => 'secret attribution',
        ]);

        $data = $this->getJson('/api/riddles')->json('data');
        $this->assertNotEmpty($data);

        $this->assertSame('medium', $data[0]['difficulty']);
        $this->assertSame('first clue', $data[0]['hint']);
        $this->assertSame('second clue', $data[0]['hint2']);
        $this->assertArrayNotHasKey('answer', $data[0]);
        $this->assertArrayNotHasKey('source', $data[0]);
    }

    public function test_suspended_riddles_are_not_listed(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->makeRiddle(['is_suspended' => true]);

        $this->getJson('/api/riddles')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_correct_answer_rewards_reputation_exactly_once(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $riddle = $this->makeRiddle();
        $user->update(['reputation' => 0]);

        $response = $this->postJson("/api/riddles/{$riddle->id}/answer", [
            'answer' => 'inkerebuzo',
        ])->assertOk();

        $response->assertJson([
            'correct' => true,
            'rewarded' => true,
        ]);

        $this->assertDatabaseHas('reputation_logs', [
            'user_id' => $user->id,
            'change' => 5,
            'reason' => 'Solved a riddle',
        ]);

        $this->assertDatabaseHas('riddle_attempts', [
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'is_correct' => true,
            'rewarded' => true,
        ]);

        // Second solve of the same riddle must not reward again.
        $this->postJson("/api/riddles/{$riddle->id}/answer", [
            'answer' => 'inkerebuzo',
        ])->assertOk()
            ->assertJson([
                'correct' => true,
                'rewarded' => false,
            ]);

        $this->assertDatabaseCount('reputation_logs', 1);
    }

    public function test_incorrect_answer_awards_nothing(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $riddle = $this->makeRiddle();
        $user->update(['reputation' => 0]);

        $this->postJson("/api/riddles/{$riddle->id}/answer", [
            'answer' => 'amagara',
        ])->assertOk()
            ->assertJson([
                'correct' => false,
                'rewarded' => false,
            ]);

        $this->assertDatabaseMissing('reputation_logs', ['user_id' => $user->id]);
        $this->assertDatabaseHas('riddle_attempts', [
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'is_correct' => false,
        ]);
    }

    public function test_answer_comparison_ignores_case_and_diacritics(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $riddle = $this->makeRiddle(['answer' => 'Inkerebuzo']);

        $this->postJson("/api/riddles/{$riddle->id}/answer", [
            'answer' => '  INKEREBUZO  ',
        ])->assertOk()->assertJson(['correct' => true]);
    }
}
