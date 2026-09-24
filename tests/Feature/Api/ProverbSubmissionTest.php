<?php

namespace Tests\Feature\Api;

use App\Models\Proverb;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProverbSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create();
    }

    private function category(): RiddleCategory
    {
        return RiddleCategory::factory()->create();
    }

    private function submit(array $payload = [])
    {
        return $this->postJson('/api/submissions/proverbs', array_merge([
            'category_id' => $this->category()->id,
            'question' => 'Iyo ngoma iravuze, ...?',
            'answer' => 'abandi barumva',
            'difficulty' => 'medium',
            'source' => 'https://imigani.example/umugani',
        ], $payload));
    }

    public function test_submit_requires_source(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/submissions/proverbs', [
            'question' => 'Iyo ngoma iravuze, ...?',
            'answer' => 'abandi barumva',
            'category_id' => $this->category()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('source');
    }

    public function test_submit_creates_pending_submission(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $response = $this->submit()->assertCreated();

        $this->assertSame('pending', $response->json('data.status'));
        $this->assertDatabaseHas('proverb_submissions', [
            'user_id' => $user->id,
            'question' => 'Iyo ngoma iravuze, ...?',
            'answer' => 'abandi barumva',
            'source' => 'https://imigani.example/umugani',
            'status' => 'pending',
        ]);
    }

    public function test_submit_blocks_duplicate_answer(): void
    {
        $user = $this->verifiedUser();
        $category = $this->category();
        Proverb::factory()->create([
            'category_id' => $category->id,
            'question' => 'Existing proverb',
            'answer' => 'abandi barumva',
        ]);

        Sanctum::actingAs($user);

        $this->submit([
            'category_id' => $category->id,
            'answer' => 'abandi barumva',
        ])->assertStatus(422);
    }

    public function test_user_can_list_their_own_submissions(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->submit();
        $this->submit(['question' => 'Ikindi?', 'answer' => 'umugani, umumaro']);

        $data = $this->getJson('/api/submissions/proverbs')->assertOk()->json('data');

        $this->assertCount(2, $data);
        $this->assertArrayHasKey('status', $data[0]);
    }

    public function test_unauthenticated_submit_is_rejected(): void
    {
        $this->postJson('/api/submissions/proverbs', [
            'question' => 'x?',
            'answer' => 'y',
            'source' => 'https://example.com',
        ])->assertStatus(401);
    }
}