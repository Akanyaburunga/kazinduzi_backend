<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiJsonErrorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_request_returns_json_401_without_accept_header(): void
    {
        $this->get('/api/riddles')
            ->assertStatus(401)
            ->assertHeader('content-type', 'application/json')
            ->assertJson(['success' => false]);
    }

    public function test_unauthenticated_api_request_returns_json_401_even_with_html_accept_header(): void
    {
        // Simulates a mobile client that does not send Accept: application/json.
        $this->withHeaders(['Accept' => 'text/html'])
            ->get('/api/riddles')
            ->assertStatus(401)
            ->assertHeader('content-type', 'application/json')
            ->assertJson(['success' => false]);
    }

    public function test_unauthenticated_request_never_redirects_to_web_login(): void
    {
        $this->get('/api/riddles')->assertStatus(401);

        $redirect = $this->get('/api/riddles')->headers->get('location');
        $this->assertNull($redirect, 'API request was redirected to the web login page.');
    }

    public function test_unverified_user_gets_json_403_on_api_request(): void
    {
        $user = User::factory()->unverified()->create();

        Sanctum::actingAs($user);

        $this->withHeaders(['Accept' => 'text/html'])
            ->get('/api/riddles')
            ->assertStatus(403)
            ->assertHeader('content-type', 'application/json')
            ->assertJson(['success' => false]);
    }

    public function test_verified_user_still_succeeds(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/riddles')->assertOk();
    }
}
