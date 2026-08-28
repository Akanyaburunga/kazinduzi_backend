<?php

namespace Tests\Feature\Api;

use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;
use App\Models\User;
use App\Support\Streaks;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyExperienceTest extends TestCase
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

    private function correctAttempt(User $user, Riddle $riddle, string $date): RiddleAttempt
    {
        $attempt = RiddleAttempt::factory()->correct()->create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
        ]);
        $attempt->created_at = Carbon::parse($date)->startOfDay();
        $attempt->save();

        return $attempt;
    }

    public function test_daily_payload_has_social_proof_and_no_answer(): void
    {
        $user = $this->verifiedUser();
        $other = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        // Both users solve the same riddle today -> solved_by_count reflects it.
        foreach ([$user, $other] as $u) {
            Sanctum::actingAs($u);
            $this->postJson("/api/riddles/{$riddle->id}/answer", ['answer' => 'inkerebuzo'])->assertOk();
        }

        Sanctum::actingAs($user);
        $data = $this->getJson('/api/riddles/daily')->assertOk()->json('data');

        $this->assertArrayHasKey('solved_by_count', $data);
        $this->assertArrayHasKey('best_streak', $data);
        $this->assertIsInt($data['solved_by_count']);
        $this->assertIsInt($data['best_streak']);
        $this->assertArrayNotHasKey('answer', $data['daily']);
    }

    public function test_daily_history_replays_a_past_daily_without_answer(): void
    {
        $user = $this->verifiedUser();
        $this->makeRiddle();

        Sanctum::actingAs($user);

        $data = $this->getJson('/api/riddles/daily/history?date=2020-01-01')->assertOk()->json('data');

        $this->assertSame('2020-01-01', $data['date']);
        $this->assertArrayHasKey('solved', $data);
        $this->assertArrayHasKey('daily', $data);
        $this->assertArrayNotHasKey('answer', $data['daily']);
    }

    public function test_daily_history_defaults_to_today_and_marks_solved(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();
        $this->correctAttempt($user, $riddle, Carbon::now()->startOfDay()->toDateString());
        $user->refresh();

        Sanctum::actingAs($user);

        $data = $this->getJson('/api/riddles/daily/history')->assertOk()->json('data');
        $this->assertSame(now()->toDateString(), $data['date']);
        $this->assertArrayHasKey('solved', $data);
    }

    public function test_freeze_protects_and_extends_the_current_streak(): void
    {
        $user = $this->verifiedUser();
        $today = Carbon::now()->startOfDay();

        // Solved yesterday and the day before -> current = 2 (persists until today ends).
        $this->correctAttempt($user, $this->makeRiddle(), $today->copy()->subDay()->toDateString());
        $this->correctAttempt($user, $this->makeRiddle(), $today->copy()->subDays(2)->toDateString());
        Streaks::recompute($user);
        $this->assertSame(2, $user->current_streak);

        Sanctum::actingAs($user);
        $data = $this->postJson('/api/riddles/streak/freeze')->assertOk()->json('data');

        // The freeze protects today: the run continues through today (3) instead of resetting.
        $this->assertSame(2, $data['freezes_remaining']);
        $this->assertTrue($data['freeze_active']);
        $this->assertSame(3, $data['streak']['current']);
        $this->assertSame(2, $data['streak']['longest']);
        $this->assertSame(3, $user->refresh()->current_streak);
    }

    public function test_freeze_cannot_be_used_twice_in_one_day(): void
    {
        $user = $this->verifiedUser();

        Sanctum::actingAs($user);
        $this->postJson('/api/riddles/streak/freeze')->assertOk();
        $this->postJson('/api/riddles/streak/freeze')->assertStatus(422);
    }

    public function test_freeze_rejected_when_no_freezes_remain(): void
    {
        $user = $this->verifiedUser();
        $user->forceFill(['streak_freezes' => 0])->save();

        Sanctum::actingAs($user);
        $this->postJson('/api/riddles/streak/freeze')->assertStatus(422);
    }

    public function test_daily_status_flags_when_at_risk(): void
    {
        $user = $this->verifiedUser();

        // Solved yesterday but not today -> current > 0 => at risk, daily available.
        $this->correctAttempt($user, $this->makeRiddle(), Carbon::now()->startOfDay()->subDay()->toDateString());
        Streaks::recompute($user);

        Sanctum::actingAs($user);
        $data = $this->getJson('/api/riddles/daily/status')->assertOk()->json('data');

        $this->assertTrue($data['daily_available']);
        $this->assertTrue($data['streak_at_risk']);
        $this->assertSame(0, $data['pending_challenges']);
        $this->assertSame(1, $data['streak']['current']);
    }

    public function test_daily_status_clears_when_solved_today(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();
        $this->correctAttempt($user, $riddle, Carbon::now()->startOfDay()->toDateString());
        Streaks::recompute($user);

        Sanctum::actingAs($user);
        $data = $this->getJson('/api/riddles/daily/status')->assertOk()->json('data');

        $this->assertFalse($data['daily_available']);
        $this->assertFalse($data['streak_at_risk']);
    }

    public function test_missing_yesterday_resets_current_streak_without_freeze(): void
    {
        $user = $this->verifiedUser();
        $today = Carbon::now()->startOfDay();

        // Only solved two days ago -> current resets to 0, longest stays 1.
        $this->correctAttempt($user, $this->makeRiddle(), $today->copy()->subDays(2)->toDateString());
        Streaks::recompute($user);

        $this->assertSame(0, $user->current_streak);
        $this->assertSame(1, $user->longest_streak);
    }
}
