<?php

namespace Tests\Feature\Api;

use App\Models\JokeSubmission;
use App\Models\ModerationLog;
use App\Models\ProverbSubmission;
use App\Models\RiddleSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContributionTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_contribution_requires_auth(): void
    {
        $this->postJson('/api/contributions', [
            'type' => 'sokwe', 'body' => 'Ikibazo', 'answer' => 'Igisubizo',
        ])->assertStatus(401);
    }

    public function test_sokwe_contribution_routes_to_riddle_submission(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->postJson('/api/contributions', [
            'type' => 'sokwe',
            'body' => 'Ikibazo cya Sokwe?',
            'answer' => 'Igisubizo',
            'who' => 'Blaise',
        ])->assertStatus(201)->assertJson(['success' => true]);

        $this->assertDatabaseHas('riddle_submissions', [
            'user_id' => $user->id,
            'question' => 'Ikibazo cya Sokwe?',
            'answer' => 'igisubizo',
            'status' => 'pending',
        ]);

        $submission = RiddleSubmission::first();
        $this->assertSame('Ibisokozo', $submission->category->name);
    }

    public function test_hera_contribution_routes_to_proverb_submission(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->postJson('/api/contributions', [
            'type' => 'hera',
            'body' => 'Umutwe umwe…',
            'answer' => 'ntiwigira inama',
        ])->assertStatus(201);

        $submission = ProverbSubmission::first();
        $this->assertSame($user->id, $submission->user_id);
        $this->assertSame('Imigani', $submission->category->name);
        $this->assertSame('Umutwe umwe…', $submission->question);
    }

    public function test_tuja_contribution_routes_to_joke_submission(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->postJson('/api/contributions', [
            'type' => 'tuja',
            'body' => 'Agaca gacakiye agahori gati:',
            'answer' => 'Punchline',
        ])->assertStatus(201);

        $submission = JokeSubmission::first();
        $this->assertSame($user->id, $submission->user_id);
        $this->assertSame('Utujajuro', $submission->category->name);
        $this->assertSame('Agaca gacakiye agahori gati:', $submission->setup);
        $this->assertSame('Punchline', $submission->punchline);
    }

    public function test_other_contribution_is_logged_as_moderation_note(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->postJson('/api/contributions', [
            'type' => 'other',
            'body' => 'Ivyo nibwiriza umutima.',
            'who' => 'Anon',
        ])->assertStatus(201);

        $this->assertDatabaseHas('moderation_logs', [
            'action_by' => $user->id,
            'action' => 'contribution_other',
        ]);

        $log = ModerationLog::first();
        $this->assertStringContainsString('(Anon)', $log->reason);
    }

    public function test_contribution_requires_valid_type_and_body(): void
    {
        Sanctum::actingAs($this->user());

        $this->postJson('/api/contributions', ['type' => 'sokwe', 'body' => ''])
            ->assertStatus(422);

        $this->postJson('/api/contributions', ['type' => 'invalid', 'body' => 'x'])
            ->assertStatus(422);
    }
}
