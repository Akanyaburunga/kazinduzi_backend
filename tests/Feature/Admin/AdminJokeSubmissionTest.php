<?php

namespace Tests\Feature\Admin;

use App\Models\Joke;
use App\Models\JokeSubmission;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminJokeSubmissionTest extends TestCase
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

    private function pendingSubmission(?User $submitter = null): JokeSubmission
    {
        $submitter = $submitter ?? User::factory()->create();

        return JokeSubmission::create([
            'user_id' => $submitter->id,
            'category_id' => RiddleCategory::factory()->create()->id,
            'setup' => 'Kuki inkoko yambutse umuhanda?',
            'punchline' => 'Kubera ko yari ijyanye kureba.',
            'source' => 'https://example.com/source',
            'status' => JokeSubmission::STATUS_PENDING,
        ]);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->nonAdmin());
        $this->getJson('/admin/api/submissions/jokes')->assertForbidden();
    }

    public function test_admin_can_list_submissions(): void
    {
        $this->pendingSubmission();

        $this->actingAs($this->admin());
        $data = $this->getJson('/admin/api/submissions/jokes')->assertOk()->json('data');

        $this->assertNotEmpty($data['data']);
        $this->assertSame('pending', $data['data'][0]['status']);
    }

    public function test_approve_publishes_a_joke_and_marks_approved(): void
    {
        $submission = $this->pendingSubmission();
        $reviewer = $this->admin();

        $this->actingAs($reviewer);
        $response = $this->postJson("/admin/api/submissions/jokes/{$submission->id}/approve")->assertOk();

        $this->assertSame('approved', $response->json('data.submission.status'));

        $this->assertDatabaseHas('jokes', [
            'setup' => 'Kuki inkoko yambutse umuhanda?',
            'punchline' => 'Kubera ko yari ijyanye kureba.',
            'source' => 'https://example.com/source',
        ]);

        $jokeId = $response->json('data.joke.id');
        $this->assertDatabaseHas('joke_submissions', [
            'id' => $submission->id,
            'joke_id' => $jokeId,
            'reviewed_by' => $reviewer->id,
            'status' => 'approved',
        ]);
    }

    public function test_approving_twice_is_rejected(): void
    {
        $submission = $this->pendingSubmission();
        $this->actingAs($this->admin());

        $this->postJson("/admin/api/submissions/jokes/{$submission->id}/approve")->assertOk();
        $this->postJson("/admin/api/submissions/jokes/{$submission->id}/approve")->assertStatus(422);
    }

    public function test_reject_marks_rejected_with_reason(): void
    {
        $submission = $this->pendingSubmission();
        $reviewer = $this->admin();

        $this->actingAs($reviewer);
        $response = $this->postJson("/admin/api/submissions/jokes/{$submission->id}/reject", [
            'reason' => 'Duplicate content.',
        ])->assertOk();

        $this->assertSame('rejected', $response->json('data.status'));
        $this->assertDatabaseHas('joke_submissions', [
            'id' => $submission->id,
            'status' => 'rejected',
            'rejection_reason' => 'Duplicate content.',
            'reviewed_by' => $reviewer->id,
        ]);

        $this->assertSame(0, Joke::count());
    }
}