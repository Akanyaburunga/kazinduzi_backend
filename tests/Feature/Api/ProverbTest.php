<?php

namespace Tests\Feature\Api;

use App\Models\Proverb;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProverbTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create();
    }

    private function makeProverb(array $overrides = []): Proverb
    {
        $category = RiddleCategory::factory()->create();

        return Proverb::factory()->create(array_merge([
            'category_id' => $category->id,
            'question' => 'Umutwe umwe…',
            'answer' => 'ntiwigira inama',
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/proverbs')->assertStatus(401);
    }

    public function test_list_proverbs_does_not_expose_the_answer(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->makeProverb();

        $response = $this->getJson('/api/proverbs')->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertArrayNotHasKey('answer', $data[0]);
        $this->assertArrayHasKey('question', $data[0]);
    }

    public function test_suspended_proverbs_are_not_listed(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->makeProverb(['is_suspended' => true]);

        $this->getJson('/api/proverbs')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_show_returns_question_but_not_answer(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $proverb = $this->makeProverb();

        $data = $this->getJson("/api/proverbs/{$proverb->id}")
            ->assertOk()
            ->json('data');

        $this->assertSame('Umutwe umwe…', $data['question']);
        $this->assertArrayNotHasKey('answer', $data);
    }

    public function test_next_returns_an_unsolved_proverb(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $proverb = $this->makeProverb();

        $data = $this->getJson('/api/proverbs/next')
            ->assertOk()
            ->json('data');

        $this->assertSame($proverb->id, $data['id']);
    }

    public function test_next_returns_404_when_all_solved(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $proverb = $this->makeProverb();

        $this->postJson("/api/proverbs/{$proverb->id}/answer", [
            'answer' => 'ntiwigira inama',
        ])->assertOk()->assertJson(['correct' => true]);

        $this->getJson('/api/proverbs/next')->assertStatus(404);
    }

    public function test_correct_answer_rewards_reputation_exactly_once(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);
        $user->update(['reputation' => 0]);

        $proverb = $this->makeProverb();

        $this->postJson("/api/proverbs/{$proverb->id}/answer", [
            'answer' => 'ntiwigira inama',
        ])->assertOk()
            ->assertJson(['correct' => true, 'rewarded' => true]);

        $this->assertDatabaseHas('reputation_logs', [
            'user_id' => $user->id,
            'change' => 5,
            'reason' => 'Solved a proverb',
        ]);

        $this->assertDatabaseHas('proverb_attempts', [
            'user_id' => $user->id,
            'proverb_id' => $proverb->id,
            'is_correct' => true,
            'rewarded' => true,
        ]);

        // Second solve of the same proverb must not reward again.
        $this->postJson("/api/proverbs/{$proverb->id}/answer", [
            'answer' => 'ntiwigira inama',
        ])->assertOk()->assertJson(['correct' => true, 'rewarded' => false]);

        $this->assertDatabaseCount('reputation_logs', 1);
    }

    public function test_incorrect_answer_awards_nothing(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);
        $user->update(['reputation' => 0]);

        $proverb = $this->makeProverb();

        $this->postJson("/api/proverbs/{$proverb->id}/answer", [
            'answer' => 'amagara',
        ])->assertOk()->assertJson(['correct' => false, 'rewarded' => false]);

        $this->assertDatabaseMissing('reputation_logs', ['user_id' => $user->id]);
    }

    public function test_solve_accepts_aliases_and_free_word_order(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $proverb = $this->makeProverb([
            'answer' => 'uwifashije',
            'answer_aliases' => 'uko yifashije',
        ]);

        $this->postJson("/api/proverbs/{$proverb->id}/answer", [
            'answer' => 'uko yifashije',
        ])->assertOk()->assertJson(['correct' => true]);
    }

    public function test_concede_reveals_answer_without_reward(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);
        $user->update(['reputation' => 0]);

        $proverb = $this->makeProverb();

        $this->postJson("/api/proverbs/{$proverb->id}/answer", [
            'answer' => 'ndaguhaye',
        ])->assertOk()
            ->assertJson([
                'correct' => false,
                'rewarded' => false,
                'conceded' => true,
                'answer' => 'ntiwigira inama',
            ]);

        $this->assertDatabaseMissing('reputation_logs', ['user_id' => $user->id]);
    }

    public function test_reveal_returns_answer_without_reward(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);
        $user->update(['reputation' => 0]);

        $proverb = $this->makeProverb();

        $this->postJson("/api/proverbs/{$proverb->id}/reveal")
            ->assertOk()
            ->assertJsonFragment(['answer' => 'ntiwigira inama']);

        $this->assertDatabaseMissing('reputation_logs', ['user_id' => $user->id]);
    }
}
