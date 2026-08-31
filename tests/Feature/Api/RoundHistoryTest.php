<?php

namespace Tests\Feature\Api;

use App\Models\Round;
use App\Models\RoundItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoundHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_history_requires_auth(): void
    {
        $this->getJson('/api/games/history')->assertStatus(401);
    }

    public function test_history_aggregates_completed_rounds_for_the_user(): void
    {
        $user = $this->user();

        Round::factory()->withScore(8, 10)->mode('sokwe')->create(['user_id' => $user->id]);
        Round::factory()->withScore(6, 10)->mode('sokwe')->create(['user_id' => $user->id]);
        Round::factory()->withScore(9, 10)->mode('hera')->create(['user_id' => $user->id]);

        // Another user's round must not leak in.
        Round::factory()->withScore(10, 10)->mode('sokwe')->create(['user_id' => User::factory()->create()->id]);

        Sanctum::actingAs($user);

        $data = $this->getJson('/api/games/history')->assertOk()->json('data');

        $this->assertSame(23, $data['total']);   // 8 + 6 + 9
        $this->assertSame(3, $data['games']);
        $this->assertSame(9, $data['best']);

        $rows = collect($data['rows'])->keyBy('mode');
        $this->assertSame(2, $rows['sokwe']['games']);
        $this->assertSame(14, $rows['sokwe']['points']);
        $this->assertSame(1, $rows['hera']['games']);
        $this->assertSame(9, $rows['hera']['points']);
    }

    public function test_history_excludes_active_rounds(): void
    {
        $user = $this->user();

        Round::factory()->create(['user_id' => $user->id]); // active
        Round::factory()->withScore(5, 10)->create(['user_id' => $user->id]); // completed

        Sanctum::actingAs($user);

        $data = $this->getJson('/api/games/history')->assertOk()->json('data');

        $this->assertSame(1, $data['games']);
        $this->assertSame(5, $data['total']);
    }

    public function test_destroy_resets_history_cascading_items(): void
    {
        $user = $this->user();

        $round = Round::factory()->withScore(7, 10)->create(['user_id' => $user->id]);
        foreach ([0, 1, 2] as $position) {
            RoundItem::factory()->position($position)->create(['round_id' => $round->id]);
        }

        Sanctum::actingAs($user);

        $this->deleteJson('/api/games/history')->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseCount('rounds', 0);
        $this->assertDatabaseCount('round_items', 0);
    }
}
