<?php

namespace Tests\Feature\Api;

use App\Models\Joke;
use App\Models\JokeAttempt;
use App\Models\Proverb;
use App\Models\ProverbAttempt;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\Round;
use App\Models\User;
use App\Support\GuestLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestClassicGamesTest extends TestCase
{
    use RefreshDatabase;

    private function makeRiddles(int $count): void
    {
        Riddle::factory()->count($count)->create(['answer' => 'inkoko']);
    }

    private function makeProverbs(int $count): void
    {
        Proverb::factory()->count($count)->create(['answer' => 'ntiwigira inama']);
    }

    private function makeJokes(int $count): void
    {
        Joke::factory()->count($count)->create([
            'punchline' => 'punchline',
            'distractors' => ['anya', 'imbwa', 'inzoka'],
        ]);
    }

    private function guestUser(string $uid = 'device-classic-123'): User
    {
        return User::create([
            'name' => 'Guest',
            'email' => null,
            'password' => 'not-a-real-password',
            'guest_uid' => $uid,
        ]);
    }

    private function verifiedUser(): User
    {
        return User::factory()->create();
    }

    public function test_guest_loads_and_solves_classic_riddle_without_reward(): void
    {
        $this->makeRiddles(10);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $data = $this->getJson('/api/riddles/next')
            ->assertOk()
            ->assertJsonMissingPath('data.answer')
            ->json('data');

        $response = $this->postJson("/api/riddles/{$data['id']}/answer", ['answer' => 'inkoko'])
            ->assertOk()
            ->json();

        $this->assertTrue($response['correct']);
        $this->assertFalse($response['rewarded']);
        $this->assertSame(0, $response['points']);

        $guest->refresh();
        $this->assertSame(0, $guest->reputation);

        $attempt = RiddleAttempt::where('user_id', $guest->id)->where('riddle_id', $data['id'])->first();
        $this->assertNotNull($attempt);
        $this->assertTrue($attempt->is_correct);
    }

    public function test_guest_loads_and_solves_classic_proverb_without_reward(): void
    {
        $this->makeProverbs(10);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $data = $this->getJson('/api/proverbs/next')
            ->assertOk()
            ->assertJsonMissingPath('data.answer')
            ->json('data');

        $response = $this->postJson("/api/proverbs/{$data['id']}/answer", ['answer' => 'ntiwigira inama'])
            ->assertOk()
            ->json();

        $this->assertTrue($response['correct']);
        $this->assertFalse($response['rewarded']);
        $this->assertSame(0, $response['points']);
        $this->assertSame(0, (int) $guest->refresh()->reputation);

        $attempt = ProverbAttempt::where('user_id', $guest->id)->where('proverb_id', $data['id'])->first();
        $this->assertNotNull($attempt);
        $this->assertTrue($attempt->is_correct);
    }

    public function test_guest_plays_classic_jokes_round_next_and_answer_without_reward(): void
    {
        $this->makeJokes(10);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $this->getJson('/api/jokes/round')->assertOk()->assertJsonMissingPath('data.punchline');
        $this->getJson('/api/jokes/next')->assertOk()->assertJsonMissingPath('data.punchline');

        $joke = Joke::where('is_suspended', false)->firstOrFail();

        $this->postJson("/api/jokes/{$joke->id}/answer", ['option' => 'punchline'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'correct' => true,
                'rewarded' => false,
                'points' => 0,
            ]);

        $wrong = $this->postJson("/api/jokes/{$joke->id}/answer", ['option' => 'anya'])
            ->assertOk()
            ->json();

        $this->assertFalse($wrong['correct']);
        $this->assertFalse($wrong['rewarded']);
        $this->assertSame(0, $wrong['points']);
    }

    public function test_guest_bearer_token_authenticates_classic_play(): void
    {
        $this->makeRiddles(10);
        $guest = $this->guestUser('device-token');
        $token = $guest->createToken('GuestApp')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/riddles/next')
            ->assertOk();
    }

    public function test_classic_play_consumes_cap_and_blocks_load_and_answer(): void
    {
        $this->makeRiddles(10);
        GuestLimits::setLimit('sokwe', 2);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $first = $this->getJson('/api/riddles/next')->assertOk()->json('data');
        $this->postJson("/api/riddles/{$first['id']}/answer", ['answer' => 'inkoko'])->assertOk();

        $second = $this->getJson('/api/riddles/next')->assertOk()->json('data');
        $this->postJson("/api/riddles/{$second['id']}/answer", ['answer' => 'inkoko'])->assertOk();

        $this->getJson('/api/riddles/next')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'requires_registration' => true,
                'guest' => [
                    'mode' => 'sokwe',
                    'limit' => 2,
                    'used' => 2,
                    'remaining' => 0,
                    'requires_registration' => true,
                ],
            ]);

        $third = Riddle::where('is_suspended', false)->whereNotIn('id', [$first['id'], $second['id']])->firstOrFail();

        $this->postJson("/api/riddles/{$third->id}/answer", ['answer' => 'inkoko'])
            ->assertStatus(403)
            ->assertJsonPath('guest.mode', 'sokwe');
    }

    public function test_wrong_classic_answer_still_counts_as_a_play(): void
    {
        $this->makeRiddles(10);
        GuestLimits::setLimit('sokwe', 1);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $data = $this->getJson('/api/riddles/next')->assertOk()->json('data');

        $this->postJson("/api/riddles/{$data['id']}/answer", ['answer' => 'dead body'])
            ->assertOk()
            ->assertJson(['correct' => false]);

        $this->assertSame(1, \App\Models\GuestPlay::where('user_id', $guest->id)->where('mode', 'sokwe')->count());
        $this->assertSame(1, GuestLimits::used('sokwe', $guest));

        $this->getJson('/api/riddles/next')->assertStatus(403);
    }

    public function test_classic_cap_shares_allowance_with_round_mode(): void
    {
        $this->makeRiddles(20);
        GuestLimits::setLimit('sokwe', 2);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $this->postJson('/api/games/sokwe/rounds')->assertOk();
        $this->assertSame(1, GuestLimits::used('sokwe', $guest));

        $data = $this->getJson('/api/riddles/next')->assertOk()->json('data');
        $this->postJson("/api/riddles/{$data['id']}/answer", ['answer' => 'inkoko'])->assertOk();

        $this->assertSame(2, GuestLimits::used('sokwe', $guest));

        $this->getJson('/api/riddles/next')
            ->assertStatus(403)
            ->assertJsonPath('guest.used', 2);
    }

    public function test_zero_limit_blocks_classic_immediately(): void
    {
        $this->makeJokes(10);
        GuestLimits::setLimit('tuja', 0);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $this->getJson('/api/jokes/round')
            ->assertStatus(403)
            ->assertJsonPath('guest.mode', 'tuja');

        $joke = Joke::where('is_suspended', false)->firstOrFail();

        $this->postJson("/api/jokes/{$joke->id}/answer", ['option' => 'punchline'])
            ->assertStatus(403)
            ->assertJsonPath('requires_registration', true);
    }

    public function test_classic_caps_are_per_mode_and_per_guest(): void
    {
        $this->makeRiddles(10);
        $this->makeProverbs(10);
        GuestLimits::setLimit('sokwe', 1);
        GuestLimits::setLimit('hera', 5);

        $firstGuest = $this->guestUser('device-a');
        Sanctum::actingAs($firstGuest);

        $riddle = $this->getJson('/api/riddles/next')->assertOk()->json('data');
        $this->postJson("/api/riddles/{$riddle['id']}/answer", ['answer' => 'inkoko'])->assertOk();

        $this->getJson('/api/riddles/next')->assertStatus(403);
        $this->getJson('/api/proverbs/next')->assertOk();

        $secondGuest = $this->guestUser('device-b');
        Sanctum::actingAs($secondGuest);

        $this->getJson('/api/riddles/next')->assertOk();
    }

    public function test_verified_accounts_still_earn_rewards_on_classic_routes(): void
    {
        $this->makeRiddles(10);
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);
        $user->update(['reputation' => 0]);

        $data = $this->getJson('/api/riddles/next')->assertOk()->json('data');

        $this->postJson("/api/riddles/{$data['id']}/answer", ['answer' => 'inkoko'])
            ->assertOk()
            ->assertJson(['correct' => true, 'rewarded' => true]);
    }

    public function test_guest_is_blocked_from_account_only_legacy_routes(): void
    {
        $this->makeRiddles(10);
        $this->makeProverbs(10);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $riddle = Riddle::where('is_suspended', false)->firstOrFail();
        $proverb = Proverb::where('is_suspended', false)->firstOrFail();

        $this->getJson('/api/riddles')->assertStatus(403);
        $this->getJson("/api/riddles/{$riddle->id}")->assertStatus(403);
        $this->postJson("/api/riddles/{$riddle->id}/reveal")->assertStatus(403);
        $this->postJson("/api/proverbs/{$proverb->id}/reveal")->assertStatus(403);
    }
}