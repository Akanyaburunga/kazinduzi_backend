<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessTokenHeaderTest extends TestCase
{
    use RefreshDatabase;

    private function guestUser(): User
    {
        return User::create([
            'name' => 'Guest',
            'email' => null,
            'password' => 'not-a-real-password',
            'guest_uid' => 'device-access-token',
        ]);
    }

    public function test_bearer_token_via_standard_authorization_header_still_works(): void
    {
        $token = $this->guestUser()->createToken('GuestApp')->plainTextToken;

        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    public function test_bearer_token_promoted_from_x_access_token_header(): void
    {
        $token = $this->guestUser()->createToken('GuestApp')->plainTextToken;

        $this->getJson('/api/me', ['X-Access-Token' => $token])->assertOk();
    }

    public function test_unauthenticated_without_any_token_header(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}