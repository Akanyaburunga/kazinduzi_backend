<?php

namespace Tests\Feature\Api;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\RiddleShare;
use App\Models\User;
use App\Support\Achievements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SummaryTest extends TestCase
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

    private function makeRiddle(string $answer = 'impene'): Riddle
    {
        return Riddle::factory()->create([
            'category_id' => RiddleCategory::factory()->create()->id,
            'question' => "Riddle for {$answer}",
            'answer' => $answer,
            'source' => 'https://example.com/riddle',
        ]);
    }

    public function test_summary_combines_points_level_streak_badges_favorites_and_activity(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        Sanctum::actingAs($user);
        $this->postJson("/api/riddles/{$riddle->id}/answer", ['answer' => 'impene'])->assertOk();
        $this->postJson("/api/me/favorites/{$riddle->id}")->assertOk();
        RiddleShare::create(['user_id' => $user->id, 'riddle_id' => $riddle->id, 'code' => 'abc123']);

        $data = $this->getJson('/api/me/summary')->assertOk()->json('data');

        $this->assertSame(5, $data['points']['reputation']);
        $this->assertSame(1, $data['points']['level']['level']);
        $this->assertSame(1, $data['streak']['current']);
        $this->assertSame(1, $data['favorites_count']);
        $this->assertSame(1, $data['activity']['total_attempts']);
        $this->assertSame(1, $data['activity']['riddles_solved']);
        $this->assertSame(100, $data['activity']['accuracy']);
        $this->assertSame(1, $data['activity']['shares_count']);
        // first_riddle + no_hint + category_master unlocked from the single no-hint solve.
        $this->assertSame(3, $data['badges']['earned_count']);
        $this->assertSame(10, $data['badges']['total']);
        $this->assertContains('first_riddle', $data['badges']['earned_slugs']);
    }

    public function test_summary_reflects_fresh_user(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $data = $this->getJson('/api/me/summary')->assertOk()->json('data');

        $this->assertSame(0, $data['points']['reputation']);
        $this->assertSame(0, $data['streak']['current']);
        $this->assertSame(0, $data['favorites_count']);
        $this->assertSame(0, $data['activity']['total_attempts']);
        $this->assertSame(0, $data['badges']['earned_count']);
    }

    public function test_summary_requires_authentication(): void
    {
        $this->getJson('/api/me/summary')->assertStatus(401);
    }
}
