<?php

namespace Tests\Feature\Api;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\RiddleShare;
use App\Models\RiddleAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocialSharingTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create();
    }

    private function makeRiddle(array $overrides = [], ?RiddleCategory $category = null): Riddle
    {
        $category = $category ?? RiddleCategory::factory()->create();

        return Riddle::factory()->create(array_merge([
            'category_id' => $category->id,
            'question' => 'Ikintu kingana n’urugo kikongana n’inzu?',
            'answer' => 'Inkerebuzo',
            'hint' => 'Gihamba ku rukuta.',
            'hint2' => 'Ugikoresha kuyabira.',
        ], $overrides));
    }

    public function test_add_list_and_remove_favorite(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();
        $other = $this->makeRiddle();

        Sanctum::actingAs($user);
        $this->postJson("/api/me/favorites/{$riddle->id}")->assertOk()
            ->assertJsonPath('data.favorited', true);
        $this->postJson("/api/me/favorites/{$other->id}")->assertOk();

        $ids = $this->getJson('/api/me/favorites')->assertOk()
            ->json('data');

        $this->assertCount(2, $ids);
        $this->assertSame([$other->id, $riddle->id], collect($ids)->pluck('id')->all());
        $this->assertArrayNotHasKey('answer', $ids[0]);
    }

    public function test_adding_favorite_is_idempotent(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        Sanctum::actingAs($user);
        $this->postJson("/api/me/favorites/{$riddle->id}")->assertOk();
        $this->postJson("/api/me/favorites/{$riddle->id}")->assertOk();

        $this->assertSame(1, $user->favoriteRiddles()->count());
    }

    public function test_removing_favorite_is_idempotent(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        Sanctum::actingAs($user);
        $this->deleteJson("/api/me/favorites/{$riddle->id}")->assertOk()
            ->assertJsonPath('data.favorited', false);
        $this->deleteJson("/api/me/favorites/{$riddle->id}")->assertOk();
    }

    public function test_cannot_favorite_a_suspended_riddle(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle(['is_suspended' => true]);

        Sanctum::actingAs($user);
        $this->postJson("/api/me/favorites/{$riddle->id}")->assertNotFound();

        $this->assertSame(0, $user->favoriteRiddles()->count());
    }

    public function test_share_creates_short_link_and_invitation_record(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        Sanctum::actingAs($user);
        $response = $this->postJson("/api/riddles/{$riddle->id}/share", [
            'recipient_email' => 'friend@example.com',
        ])->assertCreated();

        $code = $response->json('data.code');
        $this->assertNotEmpty($code);
        $this->assertStringContainsString('/api/riddles/share/' . $code, $response->json('data.share_url'));

        $this->assertDatabaseHas('riddle_shares', [
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'code' => $code,
            'recipient_email' => 'friend@example.com',
        ]);
    }

    public function test_resolving_share_reveals_riddle_without_answer_and_counts_view(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();
        $share = RiddleShare::create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'code' => 'abc123',
        ]);

        $this->getJson("/api/riddles/share/abc123")->assertOk()
            ->assertJsonPath('data.riddle.id', $riddle->id)
            ->assertJsonPath('data.shared_by', $user->id)
            ->assertJsonMissingPath('data.riddle.answer');

        $this->assertSame(1, $share->fresh()->views);
    }

    public function test_resolving_unknown_share_returns_404(): void
    {
        $this->getJson('/api/riddles/share/does-not-exist')->assertNotFound();
    }

    public function test_hint_records_saved_progress_and_payload_exposes_it(): void
    {
        $user = $this->verifiedUser();
        $riddle = $this->makeRiddle();

        Sanctum::actingAs($user);
        $this->getJson("/api/riddles/{$riddle->id}/hint")->assertOk()
            ->assertJsonPath('data.hints_revealed', 2);

        $this->assertDatabaseHas('user_riddle_progress', [
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'hints_revealed' => 2,
        ]);

        $this->getJson("/api/riddles/{$riddle->id}")
            ->assertOk()
            ->assertJsonPath('data.hints_revealed', 2);
    }

    public function test_next_prefers_categories_with_user_history(): void
    {
        $user = $this->verifiedUser();
        $catA = RiddleCategory::factory()->create();
        $catB = RiddleCategory::factory()->create();

        $a1 = $this->makeRiddle([], $catA);
        $a2 = $this->makeRiddle([], $catA);
        $b1 = $this->makeRiddle([], $catB);

        RiddleAttempt::factory()->correct()->create([
            'user_id' => $user->id,
            'riddle_id' => $a1->id,
        ]);

        Sanctum::actingAs($user);
        $next = $this->getJson('/api/riddles/next')->assertOk()->json('data');

        // User has history in catA, so the unsolved catA riddle is preferred over catB's.
        $this->assertSame($a2->id, $next['id']);
    }
}
