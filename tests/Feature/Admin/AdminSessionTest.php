<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSessionTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'reputation' => 50,
            'email_verified_at' => now(),
        ], $overrides));
    }

    public function test_unauthenticated_user_can_load_admin_shell(): void
    {
        $this->get('/admin')->assertOk();
        $this->get('/admin/login')->assertOk();
    }

    public function test_admin_can_login_and_access_panel(): void
    {
        $this->user();

        $this->postJson('/admin/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('admin', true)
            ->assertJsonPath('authenticated', true);

        $this->getJson('/admin/api/session')->assertOk()->assertJsonPath('user.email', 'admin@example.com');
        $this->getJson('/admin/api/dashboard')->assertOk();
    }

    public function test_non_admin_cannot_login(): void
    {
        $this->user(['reputation' => 0]);

        $this->postJson('/admin/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertStatus(403);

        $this->getJson('/admin/api/session')->assertStatus(401);
    }

    public function test_banned_admin_cannot_login(): void
    {
        $this->user(['is_banned' => true, 'reputation' => 50]);

        $this->postJson('/admin/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->user();

        $this->postJson('/admin/api/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_admin_can_logout(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->postJson('/admin/api/logout')->assertOk();

        $this->getJson('/admin/api/session')->assertStatus(401);
    }
}
