<?php

namespace Tests\Feature\Api;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiddleCuratorTest extends TestCase
{
    use RefreshDatabase;

    private function regularUser(): User
    {
        return User::factory()->create(['reputation' => 0]);
    }

    private function curator(): User
    {
        return User::factory()->create(['reputation' => 50]);
    }

    private function category(): RiddleCategory
    {
        return RiddleCategory::factory()->create();
    }

    public function test_curator_can_create_a_riddle_with_normalized_answer(): void
    {
        Sanctum::actingAs($this->curator());
        $category = $this->category();

        $response = $this->postJson('/api/riddles', [
            'category_id' => $category->id,
            'question' => 'Ubwo ukora imiryango?',
            'answer' => '  IKI GIKORWA  ',
        ])->assertCreated();

        $this->assertDatabaseHas('riddles', [
            'id' => $response->json('data.id'),
            'answer' => 'iki gikorwa',
        ]);
    }

    public function test_curator_can_update_and_normalize_answer(): void
    {
        $curator = $this->curator();
        Sanctum::actingAs($curator);

        $riddle = Riddle::factory()->create([
            'category_id' => $this->category()->id,
            'answer' => 'old answer',
            'created_by' => $curator->id,
        ]);

        $this->putJson("/api/riddles/{$riddle->id}", [
            'question' => 'New question',
            'answer' => '  NEW ANSWER  ',
        ])->assertOk();

        $this->assertDatabaseHas('riddles', [
            'id' => $riddle->id,
            'question' => 'New question',
            'answer' => 'new answer',
        ]);
    }

    public function test_curator_can_delete_a_riddle(): void
    {
        $curator = $this->curator();
        Sanctum::actingAs($curator);

        $riddle = Riddle::factory()->create([
            'category_id' => $this->category()->id,
            'created_by' => $curator->id,
        ]);

        $this->deleteJson("/api/riddles/{$riddle->id}")->assertOk();

        $this->assertSoftDeleted('riddles', ['id' => $riddle->id]);
    }

    public function test_curator_can_suspend_and_unsuspend_a_riddle(): void
    {
        $curator = $this->curator();
        Sanctum::actingAs($curator);

        $riddle = Riddle::factory()->create([
            'category_id' => $this->category()->id,
            'created_by' => $curator->id,
        ]);

        $this->postJson("/api/riddles/{$riddle->id}/suspend")->assertOk();
        $this->assertDatabaseHas('riddles', ['id' => $riddle->id, 'is_suspended' => true]);

        $this->postJson("/api/riddles/{$riddle->id}/unsuspend")->assertOk();
        $this->assertDatabaseHas('riddles', ['id' => $riddle->id, 'is_suspended' => false]);
    }

    public function test_curator_can_manage_categories(): void
    {
        Sanctum::actingAs($this->curator());

        $response = $this->postJson('/api/riddles/categories', [
            'name' => 'Indorerezi',
        ])->assertCreated();

        $categoryId = $response->json('data.id');

        $this->assertDatabaseHas('riddle_categories', [
            'id' => $categoryId,
            'slug' => 'indorerezi',
        ]);

        $this->putJson("/api/riddles/categories/{$categoryId}", [
            'name' => 'Indorerezi nshya',
        ])->assertOk();

        $this->deleteJson("/api/riddles/categories/{$categoryId}")->assertOk();

        $this->assertDatabaseMissing('riddle_categories', ['id' => $categoryId]);
    }

    public function test_low_reputation_user_cannot_create_riddle(): void
    {
        Sanctum::actingAs($this->regularUser());
        $category = $this->category();

        $this->postJson('/api/riddles', [
            'category_id' => $category->id,
            'question' => 'Ikibazo',
            'answer' => 'Igisubizo',
        ])->assertForbidden();

        $this->assertDatabaseCount('riddles', 0);
    }

    public function test_low_reputation_user_cannot_suspend_or_delete(): void
    {
        $curator = $this->curator();
        $riddle = Riddle::factory()->create([
            'category_id' => $this->category()->id,
            'created_by' => $curator->id,
        ]);

        Sanctum::actingAs($this->regularUser());

        $this->postJson("/api/riddles/{$riddle->id}/suspend")->assertForbidden();
        $this->deleteJson("/api/riddles/{$riddle->id}")->assertForbidden();

        $this->assertDatabaseHas('riddles', ['id' => $riddle->id, 'is_suspended' => false]);
    }
}
