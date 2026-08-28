<?php

namespace Tests\Feature\Admin;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRiddleTaxonomyTest extends TestCase
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

    private function makeRiddle(array $overrides = []): Riddle
    {
        return Riddle::factory()->create(array_merge([
            'category_id' => $this->category()->id,
            'question' => 'Ikintu kingana n’urugo kikongana n’inzu?',
            'answer' => 'Inkerebuzo',
        ], $overrides));
    }

    public function test_admin_can_create_riddle_with_type_and_tags(): void
    {
        $this->actingAs($this->admin());

        $response = $this->postJson('/admin/api/riddles', [
            'category_id' => $this->category()->id,
            'question' => 'Nyabunyana?',
            'answer' => 'intama',
            'riddle_type' => 'what_am_i',
            'tags' => ['amatungo', 'inyamaswa'],
        ])->assertStatus(201);

        $data = $response->json('data');
        $this->assertSame('what_am_i', $data['riddle_type']);
        $this->assertCount(2, $data['tags']);

        $this->assertDatabaseHas('tags', ['name' => 'amatungo']);
        $this->assertDatabaseHas('tags', ['name' => 'inyamaswa']);
    }

    public function test_admin_can_update_riddle_type_and_sync_tags(): void
    {
        $this->actingAs($this->admin());

        $riddle = $this->makeRiddle();
        $tag = Tag::factory()->create();
        $riddle->tags()->attach($tag);

        $this->putJson("/admin/api/riddles/{$riddle->id}", [
            'riddle_type' => 'math',
            'tags' => ['math', $tag->id],
        ])->assertOk();

        $this->assertDatabaseHas('riddles', ['id' => $riddle->id, 'riddle_type' => 'math']);
        $this->assertDatabaseHas('tags', ['name' => 'math']);
        $this->assertDatabaseHas('riddle_tag', ['riddle_id' => $riddle->id, 'tag_id' => $tag->id]);
        $this->assertDatabaseHas('riddle_tag', ['riddle_id' => $riddle->id]);
    }

    public function test_admin_index_filters_by_type_and_tag(): void
    {
        $this->actingAs($this->admin());

        $math = $this->makeRiddle(['riddle_type' => 'math']);
        $other = $this->makeRiddle(['riddle_type' => 'riddle']);

        $tag = Tag::factory()->create();
        $math->tags()->attach($tag);

        $byType = $this->getJson('/admin/api/riddles?type=math')->json('data.data');
        $this->assertCount(1, $byType);
        $this->assertSame($math->id, $byType[0]['id']);

        $byTag = $this->getJson("/admin/api/riddles?tag_id={$tag->id}")->json('data.data');
        $this->assertCount(1, $byTag);
        $this->assertSame($math->id, $byTag[0]['id']);

        $this->assertSame($other->id, $this->getJson('/admin/api/riddles')->json('data.data')[1]['id']);
    }

    public function test_admin_can_manage_tags(): void
    {
        $this->actingAs($this->admin());

        $tag = Tag::factory()->create(['name' => 'Old name']);

        $updated = $this->putJson("/admin/api/tags/{$tag->id}", [
            'name' => 'New name',
        ])->assertOk()->json('data');

        $this->assertSame('New name', $updated['name']);

        $this->deleteJson("/admin/api/tags/{$tag->id}")->assertOk();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_types_endpoint_returns_the_catalogue(): void
    {
        $this->actingAs($this->admin());

        $types = $this->getJson('/admin/api/riddle-types')->assertOk()->json('data');

        $this->assertContains('what_am_i', $types);
        $this->assertContains('brain_teaser', $types);
    }

    public function test_create_rejects_invalid_type(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/admin/api/riddles', [
            'category_id' => $this->category()->id,
            'question' => 'q',
            'answer' => 'a',
            'riddle_type' => 'not_a_type',
        ])->assertStatus(422);
    }
}
