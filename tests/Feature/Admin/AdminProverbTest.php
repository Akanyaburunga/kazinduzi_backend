<?php

namespace Tests\Feature\Admin;

use App\Models\Proverb;
use App\Models\ProverbAttempt;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminProverbTest extends TestCase
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

        $this->getJson('/admin/api/proverbs')->assertForbidden();
        $this->postJson('/admin/api/proverbs', ['question' => 'q', 'answer' => 'a'])->assertForbidden();
    }

    public function test_unauthenticated_is_rejected_from_admin_api(): void
    {
        $this->getJson('/admin/api/proverbs')->assertStatus(401);
    }

    public function test_admin_index_returns_answers_and_stats(): void
    {
        $this->actingAs($this->admin());
        $category = $this->category();

        $proverb = Proverb::factory()->create([
            'category_id' => $category->id,
            'answer' => 'secret answer',
        ]);

        ProverbAttempt::factory()->count(2)->correct()->create(['proverb_id' => $proverb->id]);
        ProverbAttempt::factory()->count(2)->create(['proverb_id' => $proverb->id]);

        $response = $this->getJson('/admin/api/proverbs')->assertOk();
        $row = collect($response->json('data.data'))->firstWhere('id', $proverb->id);

        $this->assertSame('secret answer', $row['answer']);
        $this->assertSame(4, $row['attempts_count']);
        $this->assertSame(2, $row['solved_count']);
        $this->assertEquals(50.0, $row['success_rate']);
    }

    public function test_admin_can_create_update_delete_proverb(): void
    {
        $this->actingAs($this->admin());
        $category = $this->category();

        $created = $this->postJson('/admin/api/proverbs', [
            'category_id' => $category->id,
            'question' => 'Umwibutsa: ...',
            'answer' => '  AMatwi  ',
            'answer_aliases' => 'amatwi, ugutwi',
            'difficulty' => 'hard',
            'source' => 'Imyandiko',
        ])->assertCreated();

        $id = $created->json('data.id');
        $this->assertDatabaseHas('proverbs', ['id' => $id, 'answer' => 'amatwi']);

        $this->putJson("/admin/api/proverbs/{$id}", [
            'question' => 'Umwibutsa nshasha: ...',
            'answer' => ' INZOKA ',
        ])->assertOk();

        $this->assertDatabaseHas('proverbs', ['id' => $id, 'question' => 'Umwibutsa nshasha: ...', 'answer' => 'inzoka']);

        $this->deleteJson("/admin/api/proverbs/{$id}")->assertOk();
        $this->assertSoftDeleted('proverbs', ['id' => $id]);
    }

    public function test_create_defaults_difficulty_to_easy_when_absent(): void
    {
        $this->actingAs($this->admin());

        $created = $this->postJson('/admin/api/proverbs', [
            'category_id' => $this->category()->id,
            'question' => 'q',
            'answer' => 'a',
        ])->assertCreated();

        $this->assertDatabaseHas('proverbs', ['id' => $created->json('data.id'), 'difficulty' => 'easy']);
    }

    public function test_create_rejects_duplicate_answer_in_same_category(): void
    {
        $this->actingAs($this->admin());
        $category = $this->category();

        Proverb::factory()->create([
            'category_id' => $category->id,
            'question' => 'Existing proverb:',
            'answer' => '  Igisokozo  ',
        ]);

        $response = $this->postJson('/admin/api/proverbs', [
            'category_id' => $category->id,
            'question' => 'Another proverb:',
            'answer' => 'igisokozo',
        ])->assertStatus(422);

        $this->assertArrayHasKey('answer', $response->json('errors'));
        $this->assertSame('Existing proverb:', $response->json('duplicate.question'));
    }

    public function test_create_allows_same_answer_in_different_category(): void
    {
        $this->actingAs($this->admin());
        $a = $this->category();
        $b = $this->category();

        Proverb::factory()->create(['category_id' => $a->id, 'answer' => 'igisokozo']);

        $this->postJson('/admin/api/proverbs', [
            'category_id' => $b->id,
            'question' => 'q',
            'answer' => 'igisokozo',
        ])->assertCreated();
    }

    public function test_suspend_stores_reason_and_unsuspend_clears_it(): void
    {
        $this->actingAs($this->admin());
        $proverb = Proverb::factory()->create(['category_id' => $this->category()->id]);

        $this->postJson("/admin/api/proverbs/{$proverb->id}/suspend", [
            'reason' => 'Duplicate content',
        ])->assertOk();

        $this->assertDatabaseHas('proverbs', [
            'id' => $proverb->id,
            'is_suspended' => true,
            'suspended_reason' => 'Duplicate content',
        ]);

        $this->postJson("/admin/api/proverbs/{$proverb->id}/unsuspend")->assertOk();
        $this->assertDatabaseHas('proverbs', [
            'id' => $proverb->id,
            'is_suspended' => false,
            'suspended_reason' => null,
        ]);
    }

    public function test_trashed_proverbs_hidden_and_restorable(): void
    {
        $this->actingAs($this->admin());
        $proverb = Proverb::factory()->create(['category_id' => $this->category()->id]);
        $proverb->delete();

        $defaultRows = $this->getJson('/admin/api/proverbs')->json('data.data');
        $this->assertEmpty(collect($defaultRows)->where('id', $proverb->id));

        $trashed = $this->getJson('/admin/api/proverbs?trashed=1')->json('data.data');
        $this->assertCount(1, $trashed);

        $this->postJson("/admin/api/proverbs/{$proverb->id}/restore")->assertOk();
        $this->assertDatabaseHas('proverbs', ['id' => $proverb->id, 'deleted_at' => null]);
    }

    public function test_index_filters_by_status_and_category(): void
    {
        $this->actingAs($this->admin());
        $catA = $this->category();
        $catB = $this->category();

        Proverb::factory()->create(['category_id' => $catA->id, 'is_suspended' => false]);
        Proverb::factory()->suspended()->create(['category_id' => $catA->id]);
        Proverb::factory()->create(['category_id' => $catB->id, 'is_suspended' => false]);

        $suspended = $this->getJson('/admin/api/proverbs?status=suspended')->json('data.data');
        $this->assertCount(1, $suspended);
        $this->assertTrue($suspended[0]['is_suspended']);

        $byCat = $this->getJson("/admin/api/proverbs?category_id={$catA->id}")->json('data.data');
        $this->assertCount(2, $byCat);
    }

    public function test_per_item_stats_shape(): void
    {
        $this->actingAs($this->admin());
        $proverb = Proverb::factory()->create(['category_id' => $this->category()->id]);
        ProverbAttempt::factory()->count(3)->correct()->create(['proverb_id' => $proverb->id]);
        ProverbAttempt::factory()->count(1)->create(['proverb_id' => $proverb->id]);
        ProverbAttempt::factory()->create(['proverb_id' => $proverb->id, 'is_correct' => false, 'submitted_answer' => 'ikosa']);

        $response = $this->getJson("/admin/api/proverbs/{$proverb->id}/stats")->assertOk()->json('data');

        $this->assertSame(5, $response['attempts_total']);
        $this->assertSame(3, $response['solved_count']);
        $this->assertEquals(60.0, $response['success_rate']);
        $this->assertContains('ikosa', collect($response['wrong_answers'])->pluck('answer'));
    }

    public function test_bulk_actions(): void
    {
        $this->actingAs($this->admin());
        $a = $this->category();
        $b = $this->category();

        $p1 = Proverb::factory()->create(['category_id' => $a->id]);
        $p2 = Proverb::factory()->create(['category_id' => $a->id]);

        $this->postJson('/admin/api/proverbs/bulk', ['ids' => [$p1->id, $p2->id], 'action' => 'suspend'])
            ->assertOk()
            ->assertJsonPath('message', '2 proverbs suspended.');

        $this->assertDatabaseHas('proverbs', ['id' => $p1->id, 'is_suspended' => true]);
        $this->assertDatabaseHas('proverbs', ['id' => $p2->id, 'is_suspended' => true]);

        $this->postJson('/admin/api/proverbs/bulk', ['ids' => [$p1->id, $p2->id], 'action' => 'change_category', 'category_id' => $b->id])
            ->assertOk();

        $this->assertDatabaseHas('proverbs', ['id' => $p1->id, 'category_id' => $b->id]);

        $this->postJson('/admin/api/proverbs/bulk', ['ids' => [$p1->id], 'action' => 'delete'])->assertOk();
        $this->assertSoftDeleted('proverbs', ['id' => $p1->id]);

        $this->postJson('/admin/api/proverbs/bulk', ['ids' => [$p1->id], 'action' => 'restore'])->assertOk();
        $this->assertDatabaseHas('proverbs', ['id' => $p1->id, 'deleted_at' => null]);
    }

    public function test_export_returns_csv(): void
    {
        $this->actingAs($this->admin());
        Proverb::factory()->create([
            'category_id' => $this->category()->id,
            'question' => 'Imana ntiribwa, ngo ibe...',
            'answer' => 'Umunsi',
        ]);

        $this->getJson('/admin/api/proverbs/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}