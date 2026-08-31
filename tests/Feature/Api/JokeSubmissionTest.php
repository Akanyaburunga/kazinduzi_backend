<?php

namespace Tests\Feature\Api;

use App\Models\Joke;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JokeSubmissionTest extends TestCase
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
        return $this->postJson('/api/submissions/jokes', array_merge([
            'category_id' => $this->category()->id,
            'setup' => 'Kuki inkoko yambutse umuhanda?',
            'punchline' => 'Kubera ko yari ijyanye kureba.',
            'source' => 'https://utwenaya.example/kunwera',
        ], $payload));
    }

    public function test_submit_requires_source(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/submissions/jokes', [
            'setup' => 'Kuki inkoko yambutse umuhanda?',
            'punchline' => 'Kubera ko yari ijyanye kureba.',
            'category_id' => $this->category()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('source');
    }

    public function test_submit_creates_pending_submission(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $response = $this->submit()->assertCreated();

        $this->assertSame('pending', $response->json('data.status'));
        $this->assertDatabaseHas('joke_submissions', [
            'user_id' => $user->id,
            'setup' => 'Kuki inkoko yambutse umuhanda?',
            'punchline' => 'Kubera ko yari ijyanye kureba.',
            'source' => 'https://utwenaya.example/kunwera',
            'status' => 'pending',
        ]);
    }

    public function test_submit_blocks_duplicate_punchline(): void
    {
        $user = $this->verifiedUser();
        Joke::factory()->create([
            'setup' => 'Existing joke',
            'punchline' => 'Kubera ko yari ijyanye kureba.',
        ]);

        Sanctum::actingAs($user);

        $this->submit([
            'punchline' => 'Kubera ko yari ijyanye kureba.',
        ])->assertStatus(422);
    }

    public function test_user_can_list_their_own_submissions(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $this->submit();
        $this->submit(['setup' => 'Ikindi?', 'punchline' => 'Nta njyewe nzi.']);

        $data = $this->getJson('/api/submissions/jokes')->assertOk()->json('data');

        $this->assertCount(2, $data);
        $this->assertArrayHasKey('status', $data[0]);
    }

    public function test_unauthenticated_submit_is_rejected(): void
    {
        $this->postJson('/api/submissions/jokes', [
            'setup' => 'x?',
            'punchline' => 'y',
            'source' => 'https://example.com',
        ])->assertStatus(401);
    }
}