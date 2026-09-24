<?php

namespace Tests\Feature\Admin;

use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['reputation' => 50]);
    }

    private function category(): RiddleCategory
    {
        return RiddleCategory::factory()->create();
    }

    public function test_dashboard_returns_operations_stats(): void
    {
        $this->actingAs($this->admin());
        $cat = $this->category();
        $riddle = Riddle::factory()->create(['category_id' => $cat->id, 'difficulty' => 'easy']);
        Riddle::factory()->create(['category_id' => $cat->id, 'difficulty' => 'hard', 'is_suspended' => true]);

        $this->makeAttempt(User::factory()->create(), $riddle, true, now());
        $this->makeAttempt(User::factory()->create(), $riddle, true, now());
        $this->makeAttempt(User::factory()->create(), $riddle, false, now()->subDays(5));

        $data = $this->getJson('/admin/api/dashboard')->assertOk()->json('data');

        $this->assertSame(2, $data['total_riddles']);
        $this->assertSame(1, $data['suspended_riddles']);
        $this->assertSame(1, $data['total_categories']);
        $this->assertSame(3, $data['total_attempts']);
        $this->assertSame(2, $data['total_solves']);
        $this->assertSame(2, $data['today_solves']);
        $this->assertSame(2, $data['today_attempts']);
        $this->assertSame(3, $data['active_players']);
        $this->assertSame(2, $data['today_solvers']);
        $this->assertSame($riddle->id, $data['top_riddles'][0]['id']);
        $this->assertSame(2, count($data['difficulty_breakdown']));
    }

    public function test_dashboard_requires_admin(): void
    {
        $this->actingAs(User::factory()->create(['reputation' => 0]));

        $this->getJson('/admin/api/dashboard')->assertForbidden();
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->getJson('/admin/api/dashboard')->assertUnauthorized();
    }

    private function makeAttempt(User $user, Riddle $riddle, bool $correct, $createdAt): RiddleAttempt
    {
        $attempt = RiddleAttempt::create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'submitted_answer' => $correct ? 'correct' : 'wrong',
            'is_correct' => $correct,
            'rewarded' => $correct,
        ]);

        $attempt->created_at = $createdAt;
        $attempt->save();

        return $attempt;
    }
}
