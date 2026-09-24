<?php

namespace Tests\Feature\Admin;

use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['reputation' => 50]);
    }

    private function nonAdmin(): User
    {
        return User::factory()->create(['reputation' => 0]);
    }

    private function setUpData(): void
    {
        $solver = User::factory()->create();
        $other = User::factory()->create();

        $easy = Riddle::factory()->create([
            'category_id' => RiddleCategory::factory()->create(['name' => 'Ibikorwa'])->id,
            'difficulty' => 'easy',
            'riddle_type' => 'riddle',
            'answer' => 'impene',
        ]);
        $hard = Riddle::factory()->create([
            'category_id' => RiddleCategory::factory()->create(['name' => 'Inkuru'])->id,
            'difficulty' => 'hard',
            'riddle_type' => 'math',
            'answer' => 'imbwa',
        ]);

        $this->makeAttempt($solver, $easy, true);
        $this->makeAttempt($solver, $hard, false);
        $this->makeAttempt($other, $easy, true);
    }

    private function makeAttempt(User $user, Riddle $riddle, bool $correct): void
    {
        RiddleAttempt::factory()->create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'is_correct' => $correct,
        ]);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->nonAdmin());
        $this->getJson('/admin/api/analytics/performance')->assertForbidden();
    }

    public function test_performance_breaks_down_by_category_type_and_difficulty(): void
    {
        $this->setUpData();
        $this->actingAs($this->admin());

        $data = $this->getJson('/admin/api/analytics/performance')->assertOk()->json('data');

        $this->assertNotEmpty($data['by_category']);
        $this->assertNotEmpty($data['by_type']);
        $this->assertNotEmpty($data['by_difficulty']);

        $type = collect($data['by_type'])->firstWhere('type', 'riddle');
        $this->assertSame(2, $type['attempts']);
        $this->assertSame(2, $type['solves']);
    }

    public function test_players_reports_daily_active_players_series(): void
    {
        $this->setUpData();
        $this->actingAs($this->admin());

        $data = $this->getJson('/admin/api/analytics/players?days=7')->assertOk()->json('data');

        $this->assertSame(7, $data['days']);
        $this->assertCount(7, $data['daily_active_players']);

        // Two distinct users solved/attempted today.
        $today = now()->toDateString();
        $this->assertSame(2, $data['daily_active_players'][$today]);
    }

    public function test_daily_conversion_reports_rate(): void
    {
        $this->setUpData();
        $this->actingAs($this->admin());

        $data = $this->getJson('/admin/api/analytics/daily-conversion?days=7')->assertOk()->json('data');

        $this->assertSame(7, $data['days']);
        $this->assertCount(7, $data['daily_conversion']);

        $today = collect($data['daily_conversion'])->firstWhere('day', now()->toDateString());
        $this->assertSame(2, $today['active_users']);
        $this->assertSame(2, $today['solvers']);
        $this->assertSame(100, $today['conversion_rate']);
    }
}
