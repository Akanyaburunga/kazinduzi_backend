<?php

namespace Tests\Feature\Admin;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminAchievementTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private function admin(): User
    {
        return User::factory()->create(['reputation' => 50]);
    }

    private function nonAdmin(): User
    {
        return User::factory()->create(['reputation' => 0]);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->nonAdmin());
        $this->getJson('/admin/api/achievements')->assertForbidden();
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/admin/api/achievements')->assertStatus(401);
    }

    public function test_admin_can_create_list_update_and_delete(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/admin/api/achievements', [
            'slug' => 'marathon_runner',
            'name' => 'Marathon Runner',
            'description' => 'Solve 200 riddles.',
            'category' => 'solved',
            'metric' => 'solved',
            'threshold' => 200,
        ])->assertStatus(201);

        $this->assertDatabaseHas('achievements', ['slug' => 'marathon_runner']);

        $id = Achievement::where('slug', 'marathon_runner')->value('id');

        $this->putJson("/admin/api/achievements/{$id}", [
            'threshold' => 250,
        ])->assertOk();
        $this->assertDatabaseHas('achievements', ['id' => $id, 'threshold' => 250]);

        $data = $this->getJson('/admin/api/achievements')->assertOk()->json('data');
        $this->assertNotEmpty($data);

        $this->deleteJson("/admin/api/achievements/{$id}")->assertOk();
        $this->assertDatabaseMissing('achievements', ['id' => $id]);
    }

    public function test_admin_can_sync_default_catalogue(): void
    {
        $this->actingAs($this->admin());

        $data = $this->postJson('/admin/api/achievements/sync')->assertOk()->json('data');

        $this->assertCount(10, $data);
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        $this->actingAs($this->admin());

        Achievement::factory()->create(['slug' => 'dup_badge']);

        $this->postJson('/admin/api/achievements', [
            'slug' => 'dup_badge',
            'name' => 'Dup',
            'description' => 'x',
            'category' => 'solved',
            'metric' => 'solved',
            'threshold' => 1,
        ])->assertStatus(422);
    }
}
