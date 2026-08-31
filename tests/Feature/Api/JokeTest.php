<?php

namespace Tests\Feature\Api;

use App\Models\Joke;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JokeTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create();
    }

    private function makeJoke(array $overrides = []): Joke
    {
        $category = RiddleCategory::factory()->create();

        return Joke::factory()->create(array_merge([
            'category_id' => $category->id,
            'setup' => 'Agaca gacakiye agahori gati:',
            'punchline' => 'Mwana wa mama undiye twari bamwe.',
            'distractors' => [
                'Nagira ngo akaguruka ntikoriye akandi.',
                'Urandya neza ndahanda.',
                'Maze simvyanka.',
            ],
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/jokes/round')->assertStatus(401);
    }

    public function test_round_returns_four_shuffled_options_including_correct_once(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $joke = $this->makeJoke();

        $data = $this->getJson('/api/jokes/round')->assertOk()->json('data');

        $this->assertSame($joke->id, $data['joke_id']);
        $this->assertSame('Agaca gacakiye agahori gati:', $data['setup']);
        $this->assertCount(4, $data['options']);
        $this->assertCount(1, array_keys($data['options'], 'Mwana wa mama undiye twari bamwe.'));
        $this->assertCount(4, array_unique($data['options']));
    }

    public function test_round_never_leaks_punchline_as_a_separate_field(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->makeJoke();

        $this->getJson('/api/jokes/round')
            ->assertOk()
            ->assertJsonMissingPath('data.punchline');
    }

    public function test_correct_option_rewards_reputation_exactly_once(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);
        $user->update(['reputation' => 0]);

        $joke = $this->makeJoke();

        $this->postJson("/api/jokes/{$joke->id}/answer", [
            'option' => 'Mwana wa mama undiye twari bamwe.',
        ])->assertOk()
            ->assertJson([
                'success' => true,
                'correct' => true,
                'rewarded' => true,
            ]);

        $this->assertDatabaseHas('reputation_logs', [
            'user_id' => $user->id,
            'change' => 5,
            'reason' => 'Solved a joke',
        ]);

        // Second attempt on the same joke must not reward again.
        $this->postJson("/api/jokes/{$joke->id}/answer", [
            'option' => 'Mwana wa mama undiye twari bamwe.',
        ])->assertOk()->assertJson(['correct' => true, 'rewarded' => false]);

        $this->assertDatabaseCount('reputation_logs', 1);
    }

    public function test_wrong_option_reveals_answer_without_reward(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);
        $user->update(['reputation' => 0]);

        $joke = $this->makeJoke();

        $this->postJson("/api/jokes/{$joke->id}/answer", [
            'option' => 'Maze simvyanka.',
        ])->assertOk()
            ->assertJson([
                'success' => false,
                'correct' => false,
                'answer' => 'Mwana wa mama undiye twari bamwe.',
            ]);

        $this->assertDatabaseMissing('reputation_logs', ['user_id' => $user->id]);
    }

    public function test_reveal_returns_punchline_without_reward(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);
        $user->update(['reputation' => 0]);

        $joke = $this->makeJoke();

        $this->postJson("/api/jokes/{$joke->id}/reveal")
            ->assertOk()
            ->assertJsonFragment(['answer' => 'Mwana wa mama undiye twari bamwe.']);

        $this->assertDatabaseMissing('reputation_logs', ['user_id' => $user->id]);
    }

    public function test_next_returns_unsolved_and_404_when_all_solved(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $joke = $this->makeJoke();

        $data = $this->getJson('/api/jokes/next')->assertOk()->json('data');
        $this->assertSame($joke->id, $data['joke_id']);
        $this->assertCount(4, $data['options']);

        $this->postJson("/api/jokes/{$joke->id}/answer", [
            'option' => 'Mwana wa mama undiye twari bamwe.',
        ])->assertOk()->assertJson(['correct' => true]);

        $this->getJson('/api/jokes/next')->assertStatus(404);
    }
}