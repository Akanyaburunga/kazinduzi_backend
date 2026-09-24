<?php

namespace Tests\Feature\Api;

use App\Models\RiddleAttempt;
use App\Models\User;
use App\Support\Levels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_standard_envelope(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Remy',
            'email' => 'remy@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => null,
            ])
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('users', ['email' => 'remy@example.com']);
    }

    public function test_register_rejects_invalid_payload(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ])->assertStatus(422);
    }

    public function test_login_returns_token_and_user_in_envelope(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => 'secret123']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'a@example.com',
            'password' => 'secret123',
            'device_name' => 'AndroidApp',
        ])->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Logged in successfully.',
            ]);

        $data = $response->json('data');
        $this->assertNotNull($data['token']);
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertArrayHasKey('expires_at', $data);
        $this->assertSame($user->id, $data['user']['id']);
        $this->assertArrayNotHasKey('password', $data['user']);
    }

    public function test_login_with_bad_credentials_is_rejected(): void
    {
        User::factory()->create(['email' => 'b@example.com', 'password' => 'secret123']);

        $this->postJson('/api/auth/login', [
            'email' => 'b@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_login_enforces_single_active_token_per_device(): void
    {
        $user = User::factory()->create(['email' => 'c@example.com', 'password' => 'secret123']);

        $this->postJson('/api/auth/login', ['email' => 'c@example.com', 'password' => 'secret123', 'device_name' => 'Android'])
            ->assertOk();

        $this->postJson('/api/auth/login', ['email' => 'c@example.com', 'password' => 'secret123', 'device_name' => 'Android'])
            ->assertOk();

        $this->assertSame(1, $user->tokens()->where('name', 'Android')->count());
    }

    public function test_logout_revokes_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Android')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk()->assertJson(['success' => true]);

        $this->assertSame(0, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_returns_profile_points_level_and_stats_without_secrets(): void
    {
        $user = User::factory()->create(['reputation' => 120]);

        $w1 = \App\Models\Word::create(['word' => 'umurizo', 'user_id' => $user->id]);
        $w2 = \App\Models\Word::create(['word' => 'igitabo', 'user_id' => $user->id]);
        \App\Models\Word::create(['word' => 'umugati', 'user_id' => $user->id]);
        \App\Models\Meaning::create(['meaning' => 'a thing', 'word_id' => $w1->id, 'user_id' => $user->id]);
        \App\Models\Meaning::create(['meaning' => 'another', 'word_id' => $w2->id, 'user_id' => $user->id]);

        $riddleA = \App\Models\Riddle::factory()->create();
        $riddleB = \App\Models\Riddle::factory()->create();
        RiddleAttempt::factory()->correct()->create(['user_id' => $user->id, 'riddle_id' => $riddleA->id]);
        RiddleAttempt::factory()->create(['user_id' => $user->id, 'riddle_id' => $riddleB->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me')->assertOk()->assertJson(['success' => true]);
        $data = $response->json('data');

        $this->assertSame($user->id, $data['id']);
        $this->assertSame(120, $data['points']['reputation']);
        $this->assertSame(Levels::levelForReputation(120), $data['points']['level']['level']);
        $this->assertSame(3, $data['stats']['words_contributed']);
        $this->assertSame(2, $data['stats']['meanings_contributed']);
        $this->assertSame(1, $data['stats']['riddles_solved']);
        $this->assertSame(2, $data['stats']['riddle_attempts']);
        $this->assertArrayNotHasKey('password', $data);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_change_password_revokes_all_tokens(): void
    {
        $user = User::factory()->create(['password' => 'oldpass1']);
        $token = $user->createToken('Android')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/password/change', [
            'current_password' => 'oldpass1',
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(0, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertStatus(401);

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpass1', $user->fresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'oldpass1']);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/password/change', [
            'current_password' => 'wrong',
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])->assertStatus(422);
    }

    public function test_resend_verification_code_endpoint_exists(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'v@example.com']);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/resend', [
            'email' => 'v@example.com',
        ])->assertOk()
            ->assertJson(['success' => true]);
    }
}
