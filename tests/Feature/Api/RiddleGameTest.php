<?php

namespace Tests\Feature\Api;

use App\Models\Riddle;
use App\Models\RiddleAttempt;
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

    public function test_list_marks_solved_riddles(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $solved = $this->makeRiddle();
        $unsolved = $this->makeRiddle();
        RiddleAttempt::factory()->correct()->create(['user_id' => $user->id, 'riddle_id' => $solved->id]);

        $data = $this->getJson('/api/riddles')->json('data');

        foreach ($data as $row) {
            if ($row['id'] === $solved->id) {
                $this->assertTrue($row['solved']);
            } else {
                $this->assertFalse($row['solved']);
            }
        }
    }

    public function test_next_returns_an_unsolved_riddle(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $solved = $this->makeRiddle();
        $unsolved = $this->makeRiddle();
        RiddleAttempt::factory()->correct()->create(['user_id' => $user->id, 'riddle_id' => $solved->id]);

        $response = $this->getJson('/api/riddles/next')->assertOk();
        $this->assertSame($unsolved->id, $response->json('data.id'));
        $this->assertFalse($response->json('data.solved'));
    }

    public function test_next_filters_by_difficulty(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->makeRiddle(['difficulty' => 'easy']);
        $hard = $this->makeRiddle(['difficulty' => 'hard']);

        $response = $this->getJson('/api/riddles/next?difficulty=hard')->assertOk();
        $this->assertSame($hard->id, $response->json('data.id'));
        $this->assertSame('hard', $response->json('data.difficulty'));
    }

    public function test_next_returns_404_when_all_riddles_solved(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $riddle = $this->makeRiddle();
        RiddleAttempt::factory()->correct()->create(['user_id' => $user->id, 'riddle_id' => $riddle->id]);

        $this->getJson('/api/riddles/next')->assertStatus(404);
    }

    public function test_hint_returns_progressive_hints_without_answer(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $riddle = $this->makeRiddle(['hint' => 'first', 'hint2' => 'second']);

        $response = $this->getJson("/api/riddles/{$riddle->id}/hint")->assertOk();
        $this->assertSame('first', $response->json('data.hint'));
        $this->assertSame('second', $response->json('data.hint2'));
        $this->assertArrayNotHasKey('answer', $response->json('data'));
    }

    public function test_reveal_returns_answer_without_reward(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);
        $user->update(['reputation' => 0]);

        $riddle = $this->makeRiddle(['answer' => 'inkerebuzo']);

        $response = $this->postJson("/api/riddles/{$riddle->id}/reveal")->assertOk();
        $this->assertSame('inkerebuzo', $response->json('data.answer'));

        $this->assertDatabaseCount('reputation_logs', 0);
        $this->assertDatabaseCount('riddle_attempts', 0);
    }

    public function test_history_returns_paginated_attempts_without_answer(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $riddle = $this->makeRiddle(['difficulty' => 'easy']);
        RiddleAttempt::factory()->correct()->create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'submitted_answer' => 'inkerebuzo',
        ]);

        $response = $this->getJson('/api/riddles/history')->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($riddle->id, $data[0]['riddle']['id']);
        $this->assertSame('inkerebuzo', $data[0]['submitted_answer']);
        $this->assertTrue($data[0]['is_correct']);
        $this->assertArrayNotHasKey('answer', $data[0]['riddle']);
    }

    public function test_history_stats_returns_aggregates(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $catA = RiddleCategory::factory()->create(['name' => 'Imigani']);
        $riddleA = Riddle::factory()->create(['category_id' => $catA->id]);
        $riddleB = Riddle::factory()->create(['category_id' => $catA->id]);
        $riddleC = Riddle::factory()->create(['category_id' => $catA->id]);

        RiddleAttempt::factory()->correct()->create(['user_id' => $user->id, 'riddle_id' => $riddleA->id]);
        RiddleAttempt::factory()->correct()->create(['user_id' => $user->id, 'riddle_id' => $riddleB->id]);
        RiddleAttempt::factory()->create(['user_id' => $user->id, 'riddle_id' => $riddleC->id]);

        $data = $this->getJson('/api/riddles/history/stats')->assertOk()->json('data');

        $this->assertSame(3, $data['total_attempts']);
        $this->assertSame(2, $data['riddles_solved']);
        $this->assertSame(3, $data['unique_riddles']);
        $this->assertEquals(66.7, $data['accuracy']);
        $this->assertCount(1, $data['by_category']);
        $this->assertSame('Imigani', $data['by_category'][0]['name']);
        $this->assertSame(3, $data['by_category'][0]['attempts']);
        $this->assertSame(2, $data['by_category'][0]['solved']);
    }
}
