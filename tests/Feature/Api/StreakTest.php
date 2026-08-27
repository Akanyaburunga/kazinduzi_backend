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

class StreakTest extends TestCase
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

    public function test_solving_a_riddle_sets_current_streak_to_one(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        Sanctum::actingAs($user);
        $this->postJson("/api/riddles/{$riddle->id}/answer", ['answer' => 'inkerebuzo'])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'current_streak' => 1,
            'longest_streak' => 1,
        ]);
    }

    public function test_incorrect_answer_does_not_start_a_streak(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        Sanctum::actingAs($user);
        $this->postJson("/api/riddles/{$riddle->id}/answer", ['answer' => 'amagara'])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'current_streak' => 0,
            'longest_streak' => 0,
        ]);
    }

    public function test_consecutive_days_build_the_current_streak(): void
    {
        $user = $this->verifiedUser();

        $today = Carbon::now()->startOfDay();
        for ($i = 2; $i >= 0; $i--) {
            $this->correctAttempt($user, $this->makeRiddle(), $today->copy()->subDays($i)->toDateString());
        }

        Streaks::recompute($user);

        $this->assertSame(3, $user->current_streak);
        $this->assertSame(3, $user->longest_streak);
    }

    public function test_gap_resets_current_streak_but_keeps_longest(): void
    {
        $user = $this->verifiedUser();

        $today = Carbon::now()->startOfDay();
        $this->correctAttempt($user, $this->makeRiddle(), $today->copy()->subDays(4)->toDateString());
        $this->correctAttempt($user, $this->makeRiddle(), $today->copy()->subDays(3)->toDateString());
        $this->correctAttempt($user, $this->makeRiddle(), $today->toDateString());

        Streaks::recompute($user);

        // 2-day run plus a fresh 1-day run: longest = 2, current = 1.
        $this->assertSame(1, $user->current_streak);
        $this->assertSame(2, $user->longest_streak);
    }

    public function test_streak_survives_when_today_is_not_yet_solved(): void
    {
        $user = $this->verifiedUser();

        $today = Carbon::now()->startOfDay();
        // Solved yesterday but not yet today -> current streak persists.
        $this->correctAttempt($user, $this->makeRiddle(), $today->copy()->subDay()->toDateString());

        Streaks::recompute($user);

        $this->assertSame(1, $user->current_streak);
        $this->assertSame(1, $user->longest_streak);
    }

    public function test_daily_payload_includes_streak_and_daily_riddle(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();
        Sanctum::actingAs($user);

        $this->postJson("/api/riddles/{$riddle->id}/answer", ['answer' => 'inkerebuzo'])->assertOk();

        $data = $this->getJson('/api/riddles/daily')->assertOk()->json('data');

        $this->assertArrayHasKey('daily', $data);
        $this->assertArrayHasKey('streak', $data);
        $this->assertSame(1, $data['streak']['current']);
        $this->assertSame(1, $data['streak']['longest']);
        $this->assertArrayNotHasKey('answer', $data['daily']);
    }

    public function test_me_includes_streak(): void
    {
        $user = $this->verifiedUser();
        $this->correctAttempt($user, $this->makeRiddle(), Carbon::now()->startOfDay()->toDateString());
        Streaks::recompute($user);

        Sanctum::actingAs($user);

        $data = $this->getJson('/api/me')->assertOk()->json('data');

        $this->assertSame(1, $data['streak']['current']);
        $this->assertSame(1, $data['streak']['longest']);
    }
}
