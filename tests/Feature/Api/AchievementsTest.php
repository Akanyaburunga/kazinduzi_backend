<?php

namespace Tests\Feature\Api;

use App\Models\Achievement;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;
use App\Models\RiddleHintUse;
use App\Models\User;
use App\Support\Achievements;
use App\Support\Streaks;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AchievementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Achievements::syncCatalogue();
    }

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

    public function test_achievements_endpoint_lists_catalogue_with_progress(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        Sanctum::actingAs($user);
        $this->postJson("/api/riddles/{$riddle->id}/answer", ['answer' => 'inkerebuzo'])->assertOk();

        $data = $this->getJson('/api/me/achievements')->assertOk()->json('data');

        $this->assertSame(10, $data['total']);
        // first_riddle + no_hint + category_master (the single riddle completes its category).
        $this->assertSame(3, $data['earned_count']);

        $bySlug = collect($data['achievements'])->keyBy('slug');
        $this->assertTrue($bySlug['first_riddle']['earned']);
        $this->assertTrue($bySlug['no_hint']['earned']);
        $this->assertTrue($bySlug['category_master']['earned']);
        $this->assertSame(1, $bySlug['riddles_10']['progress']);
        $this->assertSame(10, $bySlug['riddles_10']['goal']);
        $this->assertFalse($bySlug['streak_30']['earned']);
    }

    public function test_solve_emits_new_achievements_and_unlocks_them(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        Sanctum::actingAs($user);
        $response = $this->postJson("/api/riddles/{$riddle->id}/answer", ['answer' => 'inkerebuzo'])->assertOk();

        $slugs = collect($response->json('new_achievements'))->pluck('slug');
        $this->assertContains('first_riddle', $slugs);
        $this->assertContains('no_hint', $slugs);

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => Achievement::where('slug', 'first_riddle')->value('id'),
        ]);
    }

    public function test_unlock_is_idempotent(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();
        $riddle2 = $this->makeRiddle();

        Sanctum::actingAs($user);
        $this->postJson("/api/riddles/{$riddle->id}/answer", ['answer' => 'inkerebuzo'])->assertOk();
        $this->postJson("/api/riddles/{$riddle2->id}/answer", ['answer' => 'inkerebuzo'])->assertOk();

        $count = \DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->where('achievement_id', Achievement::where('slug', 'first_riddle')->value('id'))
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_using_a_hint_blocks_the_no_hint_badge(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        Sanctum::actingAs($user);
        $response = $this->getJson("/api/riddles/{$riddle->id}/hint")->assertOk();
        $this->postJson("/api/riddles/{$riddle->id}/answer", ['answer' => 'inkerebuzo'])->assertOk();

        $this->assertDatabaseHas('riddle_hint_uses', [
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
        ]);

        $noHintId = Achievement::where('slug', 'no_hint')->value('id');
        $firstId = Achievement::where('slug', 'first_riddle')->value('id');

        // Solved correctly -> first_riddle unlocked, but no_hint was blocked by the hint.
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $firstId,
        ]);
        $this->assertDatabaseMissing('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $noHintId,
        ]);
    }

    public function test_streak_3_badge_unlocks(): void
    {
        $user = $this->verifiedUser();
        $today = Carbon::now()->startOfDay();

        for ($i = 2; $i >= 0; $i--) {
            $this->correctAttempt($user, $this->makeRiddle(), $today->copy()->subDays($i)->toDateString());
        }
        Streaks::recompute($user);

        $unlocked = Achievements::evaluate($user);
        $this->assertTrue($unlocked->contains('slug', 'streak_3'));
    }

    public function test_solving_not_using_a_hint_emits_no_hint_in_answer(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        RiddleHintUse::create(['user_id' => $user->id, 'riddle_id' => $riddle->id]);

        Sanctum::actingAs($user);
        $response = $this->postJson("/api/riddles/{$riddle->id}/answer", ['answer' => 'inkerebuzo'])->assertOk();

        $slugs = collect($response->json('new_achievements'))->pluck('slug');
        $this->assertNotContains('no_hint', $slugs);
    }

    public function test_daily_champion_requires_seven_daily_solves(): void
    {
        $user = $this->verifiedUser();
        $today = Carbon::now()->startOfDay();

        for ($i = 0; $i < 12; $i++) {
            $this->makeRiddle();
        }

        $runningSolved = collect();

        // Resolve and solve each day's daily riddle, oldest first, using the
        // solved set that existed at that date (mirrors the service logic).
        for ($day = 6; $day >= 0; $day--) {
            $date = $today->copy()->subDays($day)->toDateString();
            $dailyId = Achievements::dailyRiddleIdFor($user->id, $date, $runningSolved);

            $attempt = RiddleAttempt::factory()->correct()->create([
                'user_id' => $user->id,
                'riddle_id' => $dailyId,
            ]);
            $attempt->created_at = Carbon::parse($date)->startOfDay();
            $attempt->save();

            $runningSolved->push($dailyId);
        }

        $unlocked = Achievements::evaluate($user);
        $this->assertTrue($unlocked->contains('slug', 'daily_champion'));
    }
}
