<?php

namespace Tests\Feature\Admin;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRiddleTest extends TestCase
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

    private function category(): RiddleCategory
    {
        return RiddleCategory::factory()->create();
    }

    public function test_non_admin_is_rejected_from_admin_api(): void
    {
        $this->actingAs($this->nonAdmin());

        $this->getJson('/admin/api/riddles')->assertForbidden();
        $this->postJson('/admin/api/riddles', [
            'question' => 'q',
            'answer' => 'a',
        ])->assertForbidden();
        $this->getJson('/admin/api/dashboard')->assertForbidden();
    }

    public function test_unauthenticated_is_rejected_from_admin_api(): void
    {
        $this->getJson('/admin/api/riddles')->assertStatus(401);
    }

    public function test_admin_can_view_dashboard_stats(): void
    {
        $this->actingAs($this->admin());

        Riddle::factory()->count(3)->create(['category_id' => $this->category()->id]);
        Riddle::factory()->count(1)->create(['category_id' => $this->category()->id, 'is_suspended' => true]);

        $response = $this->getJson('/admin/api/dashboard')->assertOk();

        $this->assertSame(4, $response->json('data.total_riddles'));
        $this->assertSame(1, $response->json('data.suspended_riddles'));
    }

    public function test_admin_index_returns_answers(): void
    {
        $this->actingAs($this->admin());

        $riddle = Riddle::factory()->create([
            'category_id' => $this->category()->id,
            'answer' => 'secret answer',
        ]);

        $response = $this->getJson('/admin/api/riddles')->assertOk();
        $this->assertSame($riddle->id, $response->json('data.data.0.id'));
        $this->assertSame('secret answer', $response->json('data.data.0.answer'));
    }

    public function test_admin_can_create_and_update_and_delete_riddle(): void
    {
        $this->actingAs($this->admin());
        $category = $this->category();

        $created = $this->postJson('/admin/api/riddles', [
            'category_id' => $category->id,
            'question' => 'Ubwo umva?',
            'answer' => '  Amatwi  ',
        ])->assertCreated();

        $riddleId = $created->json('data.id');
        $this->assertDatabaseHas('riddles', ['id' => $riddleId, 'answer' => 'amatwi']);

        $this->putJson("/admin/api/riddles/{$riddleId}", [
            'question' => 'New question',
            'answer' => ' NEW ANSWER ',
        ])->assertOk();

        $this->assertDatabaseHas('riddles', ['id' => $riddleId, 'question' => 'New question', 'answer' => 'new answer']);

        $this->deleteJson("/admin/api/riddles/{$riddleId}")->assertOk();
        $this->assertDatabaseMissing('riddles', ['id' => $riddleId]);
    }

    public function test_admin_can_suspend_and_unsuspend_riddle(): void
    {
        $this->actingAs($this->admin());

        $riddle = Riddle::factory()->create(['category_id' => $this->category()->id]);

        $this->postJson("/admin/api/riddles/{$riddle->id}/suspend")->assertOk();
        $this->assertDatabaseHas('riddles', ['id' => $riddle->id, 'is_suspended' => true]);

        $this->postJson("/admin/api/riddles/{$riddle->id}/unsuspend")->assertOk();
        $this->assertDatabaseHas('riddles', ['id' => $riddle->id, 'is_suspended' => false]);
    }

    public function test_admin_can_manage_categories(): void
    {
        $this->actingAs($this->admin());

        $created = $this->postJson('/admin/api/categories', [
            'name' => 'Indorerezi',
        ])->assertCreated();

        $categoryId = $created->json('data.id');
        $this->assertDatabaseHas('riddle_categories', ['id' => $categoryId, 'slug' => 'indorerezi']);

        $this->putJson("/admin/api/categories/{$categoryId}", [
            'name' => 'Indorerezi nshya',
        ])->assertOk();
        $this->assertDatabaseHas('riddle_categories', ['id' => $categoryId, 'slug' => 'indorerezi-nshya']);

        $this->deleteJson("/admin/api/categories/{$categoryId}")->assertOk();
        $this->assertDatabaseMissing('riddle_categories', ['id' => $categoryId]);
    }
}
