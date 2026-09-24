<?php

namespace Tests\Feature\Api;

use App\Models\Joke;
use App\Models\Proverb;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\Round;
use App\Models\RoundItem;
use App\Models\User;
use App\Support\GuestLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestModeTest extends TestCase
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

    private function guestUser(string $uid = 'device-abc-123'): User
    {
        return User::create([
            'name' => 'Guest',
            'email' => null,
            'password' => 'not-a-real-password',
            'guest_uid' => $uid,
        ]);
    }

    public function test_guest_session_creates_guest_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/guest', ['guest_uid' => 'device-xyz'])->assertOk();

        $data = $response->json('data');

        $this->assertNotNull($data['token']);
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertSame('device-xyz', $data['user']['guest_uid']);
        $this->assertNull($data['user']['email']);
        $this->assertArrayHasKey('guest', $data);
        $this->assertCount(3, $data['guest']);
    }

    public function test_guest_session_is_idempotent_for_same_uid(): void
    {
        $first = $this->postJson('/api/auth/guest', ['guest_uid' => 'device-same'])->json('data');
        $second = $this->postJson('/api/auth/guest', ['guest_uid' => 'device-same'])->json('data');

        $this->assertSame($first['user']['id'], $second['user']['id']);
        $this->assertSame(1, User::where('guest_uid', 'device-same')->count());
    }

    public function test_guest_session_requires_guest_uid(): void
    {
        $this->postJson('/api/auth/guest', [])->assertStatus(422);
    }

    public function test_guest_can_start_and_play_a_round_without_verified_email(): void
    {
        $this->makeRiddles(10);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $data = $this->postJson('/api/games/sokwe/rounds')
            ->assertOk()
            ->json('data');

        $this->assertSame(10, $data['round']['item_count']);
        $this->assertArrayHasKey('guest', $data);
        $this->assertSame(1, $data['guest']['used']);
        $this->assertLessThanOrEqual($data['guest']['limit'], $data['guest']['used']);

        // Answer one item correctly.
        $roundId = $data['round']['id'];
        $round = Round::findOrFail($roundId);
        $answer = $round->items->firstWhere('position', 0)->puzzleModel()->answer;

        $this->postJson("/api/games/sokwe/rounds/{$roundId}/items/0/answer", ['answer' => $answer])
            ->assertOk()
            ->assertJson(['correct' => true]);
    }

    public function test_guest_can_play_all_three_modes(): void
    {
        $this->makeRiddles(10);
        $this->makeProverbs(10);
        $this->makeJokes(10);

        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        foreach (['sokwe', 'hera', 'tuja'] as $mode) {
            $this->postJson("/api/games/{$mode}/rounds")->assertOk();
        }

        $this->assertSame(1, Round::where('mode', 'sokwe')->count());
        $this->assertSame(1, Round::where('mode', 'hera')->count());
        $this->assertSame(1, Round::where('mode', 'tuja')->count());
    }

    public function test_guest_is_blocked_when_limit_reached(): void
    {
        $this->makeRiddles(30);
        GuestLimits::setLimit('sokwe', 1);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $this->postJson('/api/games/sokwe/rounds')->assertOk();

        $this->postJson('/api/games/sokwe/rounds')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'requires_registration' => true,
            ]);

        $this->assertSame(1, Round::where('mode', 'sokwe')->count());
    }

    public function test_guest_limit_is_per_mode(): void
    {
        $this->makeRiddles(10);
        $this->makeProverbs(10);
        GuestLimits::setLimit('sokwe', 0);
        GuestLimits::setLimit('hera', 5);

        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        // Sokwe requires an account immediately (limit 0).
        $this->postJson('/api/games/sokwe/rounds')
            ->assertStatus(403)
            ->assertJson(['requires_registration' => true]);

        // Hera still allowed.
        $this->postJson('/api/games/hera/rounds')->assertOk();
    }

    public function test_guest_solves_earn_no_reputation_or_achievements(): void
    {
        $this->makeRiddles(10);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $data = $this->postJson('/api/games/sokwe/rounds')->json('data');
        $roundId = $data['round']['id'];

        $firstItem = Round::findOrFail($roundId)->items->firstWhere('position', 0);
        $riddle = Riddle::findOrFail($firstItem->puzzle_id);

        $response = $this->postJson("/api/games/sokwe/rounds/{$roundId}/items/0/answer", ['answer' => $riddle->answer])
            ->assertOk()
            ->json();

        $this->assertTrue($response['correct']);
        $this->assertFalse($response['rewarded']);
        $this->assertSame(0, $response['points']);
        $this->assertSame([], $response['new_achievements']);

        $guest->refresh();
        $this->assertSame(0, $guest->reputation);

        $attempt = RiddleAttempt::where('user_id', $guest->id)->where('riddle_id', $riddle->id)->first();
        $this->assertNotNull($attempt);
        $this->assertTrue($attempt->is_correct);
        $this->assertFalse($attempt->rewarded);
    }

    public function test_guest_is_blocked_from_account_only_routes(): void
    {
        $this->makeRiddles(10);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $this->getJson('/api/riddles')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'requires_registration' => true,
            ]);
    }

    public function test_guest_status_endpoint_reports_limits(): void
    {
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $this->getJson('/api/auth/guest')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'guest' => true,
                ],
            ]);

        $summary = collect($this->getJson('/api/auth/guest')->json('data.summary'))->keyBy('mode');
        $this->assertTrue($summary->has('sokwe'));
        $this->assertTrue($summary->has('hera'));
        $this->assertTrue($summary->has('tuja'));
        $this->assertArrayHasKey('remaining', $summary['sokwe']);
    }

    public function test_register_with_guest_uid_converts_guest_data(): void
    {
        $this->makeRiddles(10);
        $guest = $this->guestUser();

        // Guest plays a sokwe round.
        Sanctum::actingAs($guest);
        $data = $this->postJson('/api/games/sokwe/rounds')->json('data');
        $roundId = $data['round']['id'];
        $round = Round::findOrFail($roundId);

        $riddle = Riddle::findOrFail($round->items->firstWhere('position', 0)->puzzle_id);
        $this->postJson("/api/games/sokwe/rounds/{$roundId}/items/0/answer", ['answer' => $riddle->answer])->assertOk();

        $this->assertSame(1, Round::count());
        $this->assertSame(1, RiddleAttempt::count());

        // Register the guest with the same uid.
        $registered = $this->postJson('/api/auth/register', [
            'name' => 'Converted',
            'email' => 'converted@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'guest_uid' => 'device-abc-123',
        ])->assertStatus(201)
            ->assertJsonPath('data.converted_guest', true)
            ->json();

        $this->assertTrue($registered['success']);

        // Guest user is gone; rounds and attempts now belong to the account.
        $this->assertDatabaseMissing('users', ['guest_uid' => 'device-abc-123']);
        $account = User::where('email', 'converted@example.com')->firstOrFail();
        $this->assertSame(1, Round::where('user_id', $account->id)->count());
        $this->assertSame(1, RiddleAttempt::where('user_id', $account->id)->count());
        $this->assertSame(0, Round::where('user_id', $guest->id)->count());
    }

    public function test_register_with_unknown_guest_uid_reports_no_conversion(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Fresh',
            'email' => 'fresh@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'guest_uid' => 'does-not-exist',
        ])->assertStatus(201)
            ->assertJsonPath('data.converted_guest', false);
    }

    public function test_guest_history_rounds_are_visible_to_guest(): void
    {
        $this->makeRiddles(10);
        $guest = $this->guestUser();
        Sanctum::actingAs($guest);

        $data = $this->postJson('/api/games/sokwe/rounds')->assertOk()->json('data');
        $roundId = $data['round']['id'];

        for ($position = 0; $position < 10; $position++) {
            $this->postJson("/api/games/sokwe/rounds/{$roundId}/items/{$position}/answer", ['answer' => 'inkoko'])
                ->assertOk();
        }

        $history = $this->getJson('/api/games/history')->assertOk()->json('data');
        $this->assertSame(1, $history['games']);
        $this->assertSame(1, $history['rows'][0]['games']);
    }
}