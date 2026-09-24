<?php

namespace Tests\Feature\Api;

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\ReputationLog;
use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DuelChallengeTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $reputation = 100): User
    {
        return User::factory()->create(['reputation' => $reputation]);
    }

    private function riddle(string $answer = 'impene'): Riddle
    {
        return Riddle::factory()->create([
            'category_id' => RiddleCategory::factory()->create()->id,
            'question' => "Riddle for {$answer}",
            'answer' => $answer,
        ]);
    }

    public function test_unauthenticated_duel_request_is_rejected(): void
    {
        $this->getJson('/api/duels')->assertStatus(401);
    }

    public function test_user_can_create_a_pending_challenge(): void
    {
        $initiator = $this->user();
        $opponent = $this->user();
        $riddle = $this->riddle();

        Sanctum::actingAs($initiator);
        $data = $this->postJson('/api/duels', [
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'wager' => 10,
        ])->assertOk()->json('data');

        $this->assertSame(Challenge::STATUS_PENDING, $data['status']);
        $this->assertSame(10, $data['wager']);
        $this->assertSame('outgoing', $data['direction']);
        $this->assertArrayHasKey('answer', $data['riddle']);
        $this->assertNull($data['riddle']['answer']);
    }

    public function test_create_rejects_self_challenge(): void
    {
        $user = $this->user();
        $riddle = $this->riddle();

        Sanctum::actingAs($user);
        $this->postJson('/api/duels', ['opponent_id' => $user->id, 'riddle_id' => $riddle->id])
            ->assertStatus(422);
    }

    public function test_create_rejects_wager_above_held_reputation(): void
    {
        $initiator = $this->user(5);
        $opponent = $this->user();
        $riddle = $this->riddle();

        Sanctum::actingAs($initiator);
        $this->postJson('/api/duels', ['opponent_id' => $opponent->id, 'riddle_id' => $riddle->id, 'wager' => 10])
            ->assertStatus(422);
    }

    public function test_create_rejects_duplicate_pending_challenge_to_same_opponent(): void
    {
        $initiator = $this->user();
        $opponent = $this->user();
        $riddle = $this->riddle();

        Challenge::create([
            'initiator_id' => $initiator->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'status' => Challenge::STATUS_PENDING,
        ]);

        Sanctum::actingAs($initiator);
        $this->postJson('/api/duels', ['opponent_id' => $opponent->id, 'riddle_id' => $riddle->id])
            ->assertStatus(422);
    }

    public function test_create_rejects_when_opponent_already_solved_the_riddle(): void
    {
        $initiator = $this->user();
        $opponent = $this->user();
        $riddle = $this->riddle();

        $this->makeCorrectAttempt($opponent, $riddle);

        Sanctum::actingAs($initiator);
        $this->postJson('/api/duels', ['opponent_id' => $opponent->id, 'riddle_id' => $riddle->id])
            ->assertStatus(422);
    }

    public function test_only_opponent_can_accept_and_decide_against_the_wager(): void
    {
        $initiator = $this->user();
        $opponent = $this->user();
        $riddle = $this->riddle();

        $challenge = Challenge::create([
            'initiator_id' => $initiator->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'wager' => 10,
            'status' => Challenge::STATUS_PENDING,
        ]);

        // Non-participant is rejected.
        Sanctum::actingAs($this->user());
        $this->postJson("/api/duels/{$challenge->id}/accept")->assertStatus(404);

        // Initiator is rejected.
        Sanctum::actingAs($initiator);
        $this->postJson("/api/duels/{$challenge->id}/accept")->assertStatus(404);

        // Opponent accepts.
        Sanctum::actingAs($opponent);
        $data = $this->postJson("/api/duels/{$challenge->id}/accept")->assertOk()->json('data');
        $this->assertSame(Challenge::STATUS_ACCEPTED, $data['status']);

        // Already accepted -> cannot accept again.
        $this->postJson("/api/duels/{$challenge->id}/accept")->assertStatus(422);
    }

    public function test_opponent_can_decline_with_no_transfer(): void
    {
        $initiator = $this->user();
        $opponent = $this->user();
        $riddle = $this->riddle();

        $challenge = Challenge::create([
            'initiator_id' => $initiator->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'wager' => 10,
            'status' => Challenge::STATUS_PENDING,
        ]);

        Sanctum::actingAs($opponent);
        $data = $this->postJson("/api/duels/{$challenge->id}/decline")->assertOk()->json('data');
        $this->assertSame(Challenge::STATUS_DECLINED, $data['status']);

        $this->assertSame(100, $initiator->fresh()->reputation);
        $this->assertSame(100, $opponent->fresh()->reputation);
    }

    public function test_opponent_cannot_accept_a_wager_they_cannot_cover(): void
    {
        $initiator = $this->user(200);
        $opponent = $this->user(5);
        $riddle = $this->riddle();

        $challenge = Challenge::create([
            'initiator_id' => $initiator->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'wager' => 50,
            'status' => Challenge::STATUS_PENDING,
        ]);

        Sanctum::actingAs($opponent);
        $this->postJson("/api/duels/{$challenge->id}/accept")->assertStatus(422);
    }

    public function test_faster_solver_wins_and_transfers_the_wager(): void
    {
        $initiator = $this->user(100);
        $opponent = $this->user(100);
        $riddle = $this->riddle('impene');

        $challenge = Challenge::create([
            'initiator_id' => $initiator->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'wager' => 10,
            'status' => Challenge::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        // Initiator solves (and is faster).
        Sanctum::actingAs($initiator);
        $this->postJson("/api/duels/{$challenge->id}/solve", ['answer' => 'impene'])->assertOk()->json('data');

        // Opponent solves later.
        Sanctum::actingAs($opponent);
        $this->travel(2)->seconds(); // ensure created_at ordering is unambiguous
        $this->postJson("/api/duels/{$challenge->id}/solve", ['answer' => 'impene'])->assertOk();

        $challenge->refresh();
        $this->assertSame(Challenge::STATUS_COMPLETED, $challenge->status);
        $this->assertSame($initiator->id, $challenge->winner_id);

        $this->assertSame(110, $initiator->fresh()->reputation);
        $this->assertSame(90, $opponent->fresh()->reputation);
    }

    public function test_solve_allows_a_single_attempt_per_player(): void
    {
        $initiator = $this->user();
        $opponent = $this->user();
        $riddle = $this->riddle('impene');

        $challenge = Challenge::create([
            'initiator_id' => $initiator->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'status' => Challenge::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($initiator);
        $this->postJson("/api/duels/{$challenge->id}/solve", ['answer' => 'wrong'])->assertOk();
        $this->postJson("/api/duels/{$challenge->id}/solve", ['answer' => 'impene'])->assertStatus(422);

        $this->assertSame(1, ChallengeAttempt::where('challenge_id', $challenge->id)->where('user_id', $initiator->id)->count());
    }

    public function test_solving_a_closed_or_non_participant_duel_is_rejected(): void
    {
        $initiator = $this->user();
        $opponent = $this->user();
        $other = $this->user();
        $riddle = $this->riddle('impene');

        $challenge = Challenge::create([
            'initiator_id' => $initiator->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'status' => Challenge::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($initiator);
        $this->postJson("/api/duels/{$challenge->id}/solve", ['answer' => 'impene'])->assertOk();

        Sanctum::actingAs($opponent);
        $this->postJson("/api/duels/{$challenge->id}/solve", ['answer' => 'impene'])->assertOk();

        // Both solved so the duel is now completed; further answers are rejected.
        Sanctum::actingAs($opponent);
        $this->postJson("/api/duels/{$challenge->id}/solve", ['answer' => 'impene'])->assertStatus(422);

        // A stranger cannot even view the duel.
        Sanctum::actingAs($other);
        $this->getJson("/api/duels/{$challenge->id}")->assertStatus(404);
    }

    public function test_wager_is_voided_when_no_one_solves_on_expiry(): void
    {
        $initiator = $this->user(100);
        $opponent = $this->user(100);
        $riddle = $this->riddle();

        $challenge = $this->staleChallenge($initiator, $opponent, $riddle, 10);

        $this->artisan('duels:expire-stale')->assertSuccessful();

        $challenge->refresh();
        $this->assertSame(Challenge::STATUS_COMPLETED, $challenge->status);
        $this->assertNull($challenge->winner_id);
        $this->assertSame(100, $initiator->fresh()->reputation);
        $this->assertSame(100, $opponent->fresh()->reputation);
    }

    public function test_settlement_favours_the_one_who_did_solve_on_expiry(): void
    {
        $initiator = $this->user(100);
        $opponent = $this->user(100);
        $riddle = $this->riddle('impene');

        $challenge = $this->staleChallenge($initiator, $opponent, $riddle, 10);

        ChallengeAttempt::create([
            'challenge_id' => $challenge->id,
            'user_id' => $initiator->id,
            'submitted_answer' => 'impene',
            'is_correct' => true,
        ]);

        $this->artisan('duels:expire-stale')->assertSuccessful();

        $challenge->refresh();
        $this->assertSame($initiator->id, $challenge->winner_id);
        $this->assertSame(110, $initiator->fresh()->reputation);
        $this->assertSame(90, $opponent->fresh()->reputation);
    }

    public function test_expired_pending_duels_are_marked_expired(): void
    {
        $initiator = $this->user();
        $opponent = $this->user();
        $riddle = $this->riddle();

        $challenge = $this->staleChallenge($initiator, $opponent, $riddle, 0, Challenge::STATUS_PENDING);

        $this->artisan('duels:expire-stale')->assertSuccessful();

        $this->assertSame(Challenge::STATUS_EXPIRED, $challenge->fresh()->status);
    }

    public function test_winner_gain_is_capped_by_the_daily_reputation_cap_and_balance_is_floored(): void
    {
        // Winner has already earned 48 of the 50 daily cap, so only 2 of the
        // 10-wager duel can be pocketed; the loser forfeits exactly that 2.
        $initiator = $this->user(60);
        $opponent = $this->user(100);
        $riddle = $this->riddle('impene');

        ReputationLog::create([
            'user_id' => $initiator->id,
            'change' => 48,
            'reason' => 'Solved a riddle',
        ]);

        $challenge = Challenge::create([
            'initiator_id' => $initiator->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'wager' => 10,
            'status' => Challenge::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($initiator);
        $this->postJson("/api/duels/{$challenge->id}/solve", ['answer' => 'impene'])->assertOk();

        $this->travel(2)->seconds();
        Sanctum::actingAs($opponent);
        $this->postJson("/api/duels/{$challenge->id}/solve", ['answer' => 'impene'])->assertOk();

        $challenge->refresh();
        $this->assertSame($initiator->id, $challenge->winner_id);
        $this->assertSame(62, $initiator->fresh()->reputation);
        $this->assertSame(98, $opponent->fresh()->reputation);
    }

    public function test_answer_is_not_visible_to_the_unsolved_opponent(): void
    {
        $initiator = $this->user();
        $opponent = $this->user();
        $riddle = $this->riddle('impene');

        $challenge = Challenge::create([
            'initiator_id' => $initiator->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'status' => Challenge::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($opponent);
        $this->postJson("/api/duels/{$challenge->id}/solve", ['answer' => 'impene'])->assertOk();

        // The unsolved initiator must not learn the answer through the status payload.
        Sanctum::actingAs($initiator);
        $data = $this->getJson("/api/duels/{$challenge->id}")->assertOk()->json('data');

        $this->assertNull($data['riddle']['answer']);
        $this->assertNull($data['opponent_attempt']['submitted_answer']);
        $this->assertTrue($data['opponent_attempt']['is_correct']);
    }

    public function test_participants_can_list_their_challenges(): void
    {
        $initiator = $this->user();
        $opponent = $this->user();
        $riddle = $this->riddle();

        $challenge = Challenge::create([
            'initiator_id' => $initiator->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'status' => Challenge::STATUS_PENDING,
        ]);

        Sanctum::actingAs($initiator);
        $data = $this->getJson('/api/duels')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('outgoing', $data[0]['direction']);

        Sanctum::actingAs($opponent);
        $data = $this->getJson('/api/duels')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('incoming', $data[0]['direction']);
        $this->assertNotNull($challenge->id);
    }

    private function makeCorrectAttempt(User $user, Riddle $riddle): void
    {
        \App\Models\RiddleAttempt::factory()->create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'is_correct' => true,
        ]);
    }

    private function staleChallenge(User $initiator, User $opponent, Riddle $riddle, int $wager, string $status = Challenge::STATUS_ACCEPTED): Challenge
    {
        $challenge = Challenge::create([
            'initiator_id' => $initiator->id,
            'opponent_id' => $opponent->id,
            'riddle_id' => $riddle->id,
            'wager' => $wager,
            'status' => $status,
            'accepted_at' => $status === Challenge::STATUS_ACCEPTED ? now()->subDays(3) : null,
        ]);

        $challenge->created_at = now()->subDays(3);
        $challenge->updated_at = now()->subDays(3);
        $challenge->save();

        return $challenge;
    }
}
