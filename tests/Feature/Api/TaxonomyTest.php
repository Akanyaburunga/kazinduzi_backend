<?php

namespace Tests\Feature\Api;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create();
    }

    private function makeRiddle(array $overrides = []): Riddle
    {
        $category = RiddleCategory::factory()->create();

        return Riddle::factory()->create(array_merge([
            'category_id' => $category->id,
            'question' => 'Ikintu kingana n’urugo kikongana n’inzu?',
            'answer' => 'Inkerebuzo',
        ], $overrides));
    }

    public function test_game_payload_includes_type_and_tags_but_not_answer(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $riddle = $this->makeRiddle(['riddle_type' => 'what_am_i']);
        $riddle->tags()->sync(Tag::factory()->count(2)->create()->pluck('id'));

        $data = $this->getJson('/api/riddles')->json('data');
        $this->assertNotEmpty($data);

        $row = collect($data)->firstWhere('id', $riddle->id);
        $this->assertSame('what_am_i', $row['riddle_type']);
        $this->assertCount(2, $row['tags']);
        $this->assertArrayNotHasKey('answer', $row);
    }

    public function test_index_filters_by_type(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->makeRiddle(['riddle_type' => 'what_am_i']);
        $math = $this->makeRiddle(['riddle_type' => 'math', 'question' => 'Math one?']);

        $data = $this->getJson('/api/riddles?type=math')->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($math->id, $data[0]['id']);
    }

    public function test_index_sorts_by_new(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->makeRiddle();
        $newest = $this->makeRiddle(['question' => 'Newest one?']);

        $data = $this->getJson('/api/riddles?sort=new')->json('data');
        $this->assertSame($newest->id, $data[0]['id']);
    }

    public function test_trending_returns_riddles_ranked_by_popularity(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $popular = $this->makeRiddle();
        $lessPopular = $this->makeRiddle();

        $popular->forceFill(['popularity_score' => 50])->save();
        $lessPopular->forceFill(['popularity_score' => 10])->save();

        $data = $this->getJson('/api/riddles/trending')->json('data');

        $this->assertSame($popular->id, $data[0]['id']);
        $this->assertSame($lessPopular->id, $data[1]['id']);
    }

    public function test_correct_solve_increases_popularity_score(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $riddle = $this->makeRiddle();

        $this->postJson("/api/riddles/{$riddle->id}/answer", ['answer' => 'inkerebuzo'])->assertOk();

        $this->assertDatabaseHas('riddles', [
            'id' => $riddle->id,
            'popularity_score' => 3, // total solves (1) + 2 * recent-week solves (1)
        ]);
    }
}
