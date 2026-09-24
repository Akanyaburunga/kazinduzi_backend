<?php

namespace Tests\Feature\Admin;

use App\Models\Joke;
use App\Models\JokeAttempt;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminJokeTest extends TestCase
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

        $this->getJson('/admin/api/jokes')->assertForbidden();
        $this->postJson('/admin/api/jokes', ['setup' => 'q', 'punchline' => 'a'])->assertForbidden();
    }

    public function test_admin_index_returns_punchlines_and_stats(): void
    {
        $this->actingAs($this->admin());
        $category = $this->category();

        $joke = Joke::factory()->create([
            'category_id' => $category->id,
            'punchline' => 'secret punchline',
        ]);

        JokeAttempt::factory()->count(1)->correct()->create(['joke_id' => $joke->id]);
        JokeAttempt::factory()->count(3)->create(['joke_id' => $joke->id]);

        $response = $this->getJson('/admin/api/jokes')->assertOk();
        $row = collect($response->json('data.data'))->firstWhere('id', $joke->id);

        $this->assertSame('secret punchline', $row['punchline']);
        $this->assertSame(4, $row['attempts_count']);
        $this->assertSame(1, $row['solved_count']);
        $this->assertEquals(25.0, $row['success_rate']);
    }

    public function test_admin_can_create_update_delete_joke(): void
    {
        $this->actingAs($this->admin());

        $created = $this->postJson('/admin/api/jokes', [
            'category_id' => $this->category()->id,
            'setup' => 'Ugize iki?',
            'punchline' => 'Ntakibimba',
            'distractors' => ['Amatwi', 'Inzoga', 'umwaka'],
            'source' => 'Utujajuro',
        ])->assertCreated();

        $id = $created->json('data.id');
        $this->assertDatabaseHas('jokes', ['id' => $id, 'punchline' => 'Ntakibimba']);
        $this->assertSame(['Amatwi', 'Inzoga', 'umwaka'], $created->json('data.distractors'));

        $this->putJson("/admin/api/jokes/{$id}", [
            'setup' => 'Ugize iki none?',
            'punchline' => 'Ntakibimba maze',
            'distractors' => ['Imbwa', 'Imbga'],
        ])->assertOk();

        $this->assertDatabaseHas('jokes', ['id' => $id, 'setup' => 'Ugize iki none?', 'punchline' => 'Ntakibimba maze']);

        $this->deleteJson("/admin/api/jokes/{$id}")->assertOk();
        $this->assertSoftDeleted('jokes', ['id' => $id]);
    }

    public function test_create_rejects_duplicate_punchline(): void
    {
        $this->actingAs($this->admin());

        Joke::factory()->create(['setup' => 'Igitangaza gisanzwe', 'punchline' => 'Igisokozo gisanzwe']);

        $this->postJson('/admin/api/jokes', [
            'setup' => 'Ntukubure',
            'punchline' => 'Igisokozo gisanzwe',
        ])->assertStatus(422)
            ->assertJsonPath('errors.punchline.0', 'A joke with this punchline already exists with the setup "Igitangaza gisanzwe".');
    }

    public function test_suspend_stores_reason_and_unsuspend_clears_it(): void
    {
        $this->actingAs($this->admin());
        $joke = Joke::factory()->create(['category_id' => $this->category()->id]);

        $this->postJson("/admin/api/jokes/{$joke->id}/suspend", [
            'reason' => 'Offensive',
        ])->assertOk();

        $this->assertDatabaseHas('jokes', [
            'id' => $joke->id,
            'is_suspended' => true,
            'suspended_reason' => 'Offensive',
        ]);

        $this->postJson("/admin/api/jokes/{$joke->id}/unsuspend")->assertOk();
        $this->assertDatabaseHas('jokes', ['id' => $joke->id, 'is_suspended' => false]);
    }

    public function test_trashed_jokes_hidden_and_restorable(): void
    {
        $this->actingAs($this->admin());
        $joke = Joke::factory()->create(['category_id' => $this->category()->id]);
        $joke->delete();

        $defaultRows = $this->getJson('/admin/api/jokes')->json('data.data');
        $this->assertEmpty(collect($defaultRows)->where('id', $joke->id));

        $trashed = $this->getJson('/admin/api/jokes?trashed=1')->json('data.data');
        $this->assertCount(1, $trashed);

        $this->postJson("/admin/api/jokes/{$joke->id}/restore")->assertOk();
        $this->assertDatabaseHas('jokes', ['id' => $joke->id, 'deleted_at' => null]);
    }

    public function test_bulk_suspend(): void
    {
        $this->actingAs($this->admin());
        $a = $this->category();
        $b = $this->category();

        $j1 = Joke::factory()->create(['category_id' => $a->id]);
        $j2 = Joke::factory()->create(['category_id' => $a->id]);

        $this->postJson('/admin/api/jokes/bulk', ['ids' => [$j1->id, $j2->id], 'action' => 'suspend'])
            ->assertOk()
            ->assertJsonPath('message', '2 jokes suspended.');

        $this->assertDatabaseHas('jokes', ['id' => $j1->id, 'is_suspended' => true]);
        $this->assertDatabaseHas('jokes', ['id' => $j2->id, 'is_suspended' => true]);

        $this->postJson('/admin/api/jokes/bulk', ['ids' => [$j1->id], 'action' => 'change_category', 'category_id' => $b->id])
            ->assertOk();

        $this->assertDatabaseHas('jokes', ['id' => $j1->id, 'category_id' => $b->id]);
    }

    public function test_per_item_stats_shape(): void
    {
        $this->actingAs($this->admin());
        $joke = Joke::factory()->create(['category_id' => $this->category()->id]);

        JokeAttempt::factory()->count(2)->correct()->create(['joke_id' => $joke->id]);
        JokeAttempt::factory()->count(1)->create(['joke_id' => $joke->id]);
        JokeAttempt::factory()->create(['joke_id' => $joke->id, 'is_correct' => false, 'submitted_answer' => 'inya']);

        $response = $this->getJson("/admin/api/jokes/{$joke->id}/stats")->assertOk()->json('data');

        $this->assertSame(4, $response['attempts_total']);
        $this->assertSame(2, $response['solved_count']);
        $this->assertEquals(50.0, $response['success_rate']);
        $this->assertSame('inya', $response['wrong_answers'][0]['answer']);
    }

    public function test_export_returns_csv(): void
    {
        $this->actingAs($this->admin());
        Joke::factory()->create([
            'category_id' => $this->category()->id,
            'setup' => 'Ugize iki?',
            'punchline' => 'Ntakibimba',
        ]);

        $this->getJson('/admin/api/jokes/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}