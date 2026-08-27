<?php

namespace Tests\Feature\Api;

use App\Models\ReputationLog;
use App\Models\User;
use App\Support\Levels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_points_returns_total_and_history_in_order(): void
    {
        $user = User::factory()->create(['reputation' => 15]);

        $older = ReputationLog::create(['user_id' => $user->id, 'change' => 5, 'reason' => 'Solved a riddle']);
        $newer = ReputationLog::create(['user_id' => $user->id, 'change' => 10, 'reason' => 'Contributed a word']);
        $older->created_at = now()->subMinutes(2);
        $older->save();
        $newer->created_at = now()->subMinute();
        $newer->save();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/points')->assertOk()->assertJson(['success' => true]);
        $data = $response->json('data');

        $this->assertSame(15, $data['total']);

        $history = collect($data['history']['data']);
        $this->assertCount(2, $history);
        $this->assertSame(10, $history[0]['change']);
        $this->assertSame('Contributed a word', $history[0]['reason']);
        $this->assertSame(5, $history[1]['change']);
    }

    public function test_points_requires_authentication(): void
    {
        $this->getJson('/api/points')->assertStatus(401);
    }

    public function test_me_levels_returns_current_progress_and_table(): void
    {
        $user = User::factory()->create(['reputation' => 120]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me/levels')->assertOk()->assertJson(['success' => true]);
        $data = $response->json('data');

        $current = $data['current'];
        $this->assertSame(Levels::levelForReputation(120), $current['level']);
        $this->assertSame(50, $current['current_min']);
        $this->assertSame(3, $current['next_level']);
        $this->assertSame(150, $current['next_min']);
        $this->assertGreaterThan(0, $current['progress_to_next']);
        $this->assertLessThan(100, $current['progress_to_next']);

        $this->assertCount(count(Levels::THRESHOLDS), $data['levels']);
        $this->assertSame(1, $data['levels'][0]['level']);
    }

    public function test_me_levels_requires_authentication(): void
    {
        $this->getJson('/api/me/levels')->assertStatus(401);
    }
}
