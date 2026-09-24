<?php

namespace Tests\Feature\Api;

use App\Models\ReputationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private function grantPoints(User $user, int $points, ?\DateTimeInterface $at = null): void
    {
        $log = ReputationLog::create([
            'user_id' => $user->id,
            'change' => $points,
            'reason' => 'Solved a riddle',
        ]);
        $log->created_at = $at ?? now();
        $log->save();
    }

    public function test_leaderboard_returns_envelope_and_rankings(): void
    {
        $a = User::factory()->create(['name' => 'Alice']);
        $b = User::factory()->create(['name' => 'Bob']);
        $this->grantPoints($a, 10);
        $this->grantPoints($b, 20);

        Sanctum::actingAs($b);

        $response = $this->getJson('/api/leaderboard?filter=today')->assertOk();
        $this->assertTrue($response->json('success'));

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame($b->id, $data[0]['id']);
        $this->assertSame(1, $data[0]['rank']);
        $this->assertSame(20, $data[0]['points']);
        $this->assertSame($a->id, $data[1]['id']);
        $this->assertSame(2, $data[1]['rank']);
        $this->assertSame(10, $data[1]['points']);

        $this->assertSame($b->id, $response->json('me.id'));
        $this->assertSame(1, $response->json('me.rank'));
        $this->assertSame(20, $response->json('me.points'));
        $this->assertSame(2, $response->json('me.total_players'));
        $this->assertSame(100, $response->json('me.percentile'));
        $this->assertSame('today', $response->json('filter'));
    }

    public function test_leaderboard_excludes_zero_reputation_users(): void
    {
        $a = User::factory()->create();
        User::factory()->create(); // zero points
        $this->grantPoints($a, 10);

        Sanctum::actingAs($a);

        $response = $this->getJson('/api/leaderboard?filter=today')->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(1, $response->json('me.total_players'));
    }

    public function test_leaderboard_me_ranked_outside_page(): void
    {
        $leader = User::factory()->create();
        for ($i = 0; $i < 30; $i++) {
            $this->grantPoints(User::factory()->create(), 5);
        }
        $this->grantPoints($leader, 1);

        Sanctum::actingAs($leader);

        $response = $this->getJson('/api/leaderboard?per_page=10')->assertOk();

        $this->assertCount(10, $response->json('data'));
        // 30 others (5 pts each) rank above the leader (1 pt) → rank 31 of 31.
        $this->assertSame(31, $response->json('me.rank'));
        $this->assertSame(31, $response->json('me.total_players'));
        $this->assertSame(10, $response->json('meta.per_page'));
        $this->assertEmpty(collect($response->json('data'))->where('id', $leader->id));
    }

    public function test_leaderboard_applies_period_filter(): void
    {
        $user = User::factory()->create();
        $this->grantPoints($user, 10, now());            // today
        $this->grantPoints($user, 50, now()->subMonths(2)); // older

        Sanctum::actingAs($user);

        $today = $this->getJson('/api/leaderboard?filter=today')->json('me');
        $allTime = $this->getJson('/api/leaderboard?filter=all_time')->json('me');

        $this->assertSame(10, $today['points']);
        $this->assertSame(60, $allTime['points']);
    }

    public function test_leaderboard_requires_authentication(): void
    {
        $this->getJson('/api/leaderboard')->assertStatus(401);
    }
}
