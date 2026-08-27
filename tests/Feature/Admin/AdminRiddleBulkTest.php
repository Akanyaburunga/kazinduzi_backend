<?php

namespace Tests\Feature\Admin;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRiddleBulkTest extends TestCase
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

    public function test_bulk_suspend_and_unsuspend(): void
    {
        $this->actingAs($this->admin());
        $riddles = Riddle::factory()->count(2)->create(['category_id' => $this->category()->id]);
        $ids = $riddles->pluck('id')->all();

        $this->postJson('/admin/api/riddles/bulk', [
            'ids' => $ids,
            'action' => 'suspend',
            'reason' => 'Spam',
        ])->assertOk();

        foreach ($ids as $id) {
            $this->assertDatabaseHas('riddles', ['id' => $id, 'is_suspended' => true, 'suspended_reason' => 'Spam']);
        }

        $this->postJson('/admin/api/riddles/bulk', [
            'ids' => $ids,
            'action' => 'unsuspend',
        ])->assertOk();

        foreach ($ids as $id) {
            $this->assertDatabaseHas('riddles', ['id' => $id, 'is_suspended' => false, 'suspended_reason' => null]);
        }
    }

    public function test_bulk_delete_and_restore(): void
    {
        $this->actingAs($this->admin());
        $riddles = Riddle::factory()->count(2)->create(['category_id' => $this->category()->id]);
        $ids = $riddles->pluck('id')->all();

        $this->postJson('/admin/api/riddles/bulk', [
            'ids' => $ids,
            'action' => 'delete',
        ])->assertOk();

        foreach ($ids as $id) {
            $this->assertSoftDeleted('riddles', ['id' => $id]);
        }

        $this->postJson('/admin/api/riddles/bulk', [
            'ids' => $ids,
            'action' => 'restore',
        ])->assertOk();

        foreach ($ids as $id) {
            $this->assertDatabaseHas('riddles', ['id' => $id, 'deleted_at' => null]);
        }
    }

    public function test_bulk_change_category(): void
    {
        $this->actingAs($this->admin());
        $target = $this->category();
        $riddles = Riddle::factory()->count(2)->create(['category_id' => $this->category()->id]);
        $ids = $riddles->pluck('id')->all();

        $this->postJson('/admin/api/riddles/bulk', [
            'ids' => $ids,
            'action' => 'change_category',
            'category_id' => $target->id,
        ])->assertOk();

        foreach ($ids as $id) {
            $this->assertDatabaseHas('riddles', ['id' => $id, 'category_id' => $target->id]);
        }
    }

    public function test_bulk_requires_admin(): void
    {
        $this->actingAs(User::factory()->create(['reputation' => 0]));

        $this->postJson('/admin/api/riddles/bulk', [
            'ids' => [1],
            'action' => 'suspend',
        ])->assertForbidden();
    }

    public function test_bulk_rejects_invalid_action(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/admin/api/riddles/bulk', [
            'ids' => [1],
            'action' => 'explode',
        ])->assertStatus(422);
    }

    public function test_bulk_change_category_requires_category_when_moving(): void
    {
        $this->actingAs($this->admin());
        $riddle = Riddle::factory()->create(['category_id' => $this->category()->id]);

        $this->postJson('/admin/api/riddles/bulk', [
            'ids' => [$riddle->id],
            'action' => 'change_category',
        ])->assertStatus(422);
    }
}
