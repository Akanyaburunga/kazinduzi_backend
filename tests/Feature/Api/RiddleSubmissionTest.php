<?php

namespace Tests\Feature\Api;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiddleSubmissionTest extends TestCase
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
        return $this->postJson('/api/submissions/riddles', array_merge([
            'category_id' => $this->category()->id,
            'question' => 'Ngwino mu mwaka?',
            'answer' => 'impene',
            'difficulty' => 'easy',
            'riddle_type' => 'what_am_i',
            'source' => 'https://ibikorwa.example/riddle',
        ], $payload));
    }

    public function test_submit_requires_source(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/submissions/riddles', [
            'question' => 'Ngwino mu mwaka?',
            'answer' => 'impene',
            'category_id' => $this->category()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('source');
    }

    public function test_submit_creates_pending_submission(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $response = $this->submit()->assertCreated();

        $this->assertSame('pending', $response->json('data.status'));
        $this->assertSame($user->id, \DB::table('riddle_submissions')->where('user_id', $user->id)->value('user_id'));

        $this->assertDatabaseHas('riddle_submissions', [
            'user_id' => $user->id,
            'question' => 'Ngwino mu mwaka?',
            'answer' => 'impene',
            'source' => 'https://ibikorwa.example/riddle',
            'status' => 'pending',
        ]);
    }

    public function test_submit_blocks_duplicate_answer(): void
    {
        $user = $this->verifiedUser();
        $category = $this->category();
        Riddle::factory()->create([
            'category_id' => $category->id,
            'question' => 'Existing riddle',
            'answer' => 'impene',
        ]);

        Sanctum::actingAs($user);

        $this->submit([
            'category_id' => $category->id,
            'answer' => 'impene',
        ])->assertStatus(422);
    }

    public function test_user_can_list_their_own_submissions(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->submit();
        $this->submit(['question' => 'Ikindi kintu?', 'answer' => 'umugani']);

        $data = $this->getJson('/api/submissions/riddles')->assertOk()->json('data');

        $this->assertCount(2, $data);
        $this->assertArrayHasKey('status', $data[0]);
    }

    public function test_unauthenticated_submit_is_rejected(): void
    {
        $this->postJson('/api/submissions/riddles', [
            'question' => 'x',
            'answer' => 'y',
            'source' => 'https://example.com',
        ])->assertStatus(401);
    }
}
