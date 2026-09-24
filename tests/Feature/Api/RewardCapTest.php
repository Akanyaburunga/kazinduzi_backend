<?php

namespace Tests\Feature\Api;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RewardCapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 2 solves at 5 pts each reach this cap; a 3rd solve is capped.
        config(['riddles.daily_solve_reputation_cap' => 10]);
    }

    private function verifiedUser(): User
    {
        return User::factory()->create();
    }

    private function makeRiddle(string $answer): Riddle
    {
        $category = RiddleCategory::factory()->create();

        return Riddle::factory()->create([
            'category_id' => $category->id,
            'question' => "Riddle for {$answer}",
            'answer' => $answer,
        ]);
    }

    public function test_correct_solves_reward_until_daily_cap_is_reached(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $first = $this->makeRiddle('impene')->fresh();
        $second = $this->makeRiddle('inkoko')->fresh();
        $third = $this->makeRiddle('imbwa')->fresh();

        $r1 = $this->postJson("/api/riddles/{$first->id}/answer", ['answer' => 'impene'])->assertOk();
        $r2 = $this->postJson("/api/riddles/{$second->id}/answer", ['answer' => 'inkoko'])->assertOk();

        $this->assertSame(5, $r1->json('points'));
        $this->assertTrue($r1->json('rewarded'));
        $this->assertSame(5, $r2->json('points'));

        // Third solve: cap reached, no reward but still correct.
        $r3 = $this->postJson("/api/riddles/{$third->id}/answer", ['answer' => 'imbwa'])->assertOk();

        $this->assertTrue($r3->json('correct'));
        $this->assertSame(0, $r3->json('points'));
        $this->assertFalse($r3->json('rewarded'));
        $this->assertTrue($r3->json('capped'));

        $this->assertSame(10, (int) $user->fresh()->reputation);
    }
}
