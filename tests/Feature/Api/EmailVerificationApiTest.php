<?php

namespace Tests\Feature\Api;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The mobile 6-digit email verification flow:
 *   register          -> POST /api/auth/register   (201, code emailed, expiry 10 min)
 *   verify            -> POST /api/auth/email/verify  (email + 6-digit code)
 *   resend            -> POST /api/auth/email/resend  (email only)
 *
 * Delivery: in the local (dev) environment the code is logged to the Laravel
 * log; in every other environment (staging, production, testing) a normal
 * verification email is sent via the configured MAIL_* sender.
 */
class EmailVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_unverified_user_and_emails_six_digit_code(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Remy',
            'email' => 'remy@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'converted_guest' => false,
                ],
            ]);

        $user = User::where('email', 'remy@example.com')->firstOrFail();

        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->verification_code);
        $this->assertNotNull($user->verification_expires_at);
        $this->assertTrue($user->verification_expires_at->greaterThan(now()));
        $this->assertTrue(
            $user->verification_expires_at->lessThanOrEqualTo(now()->addMinutes(10))
        );

        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use ($user) {
            return $mail->hasTo('remy@example.com')
                && $mail->verificationCode === $user->verification_code;
        });
    }

    public function test_dev_environment_logs_the_code_instead_of_emailing(): void
    {
        app()->detectEnvironment(fn () => 'local');

        Event::fake([MessageLogged::class]);
        Mail::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Remy',
            'email' => 'dev@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(201);

        $user = User::where('email', 'dev@example.com')->firstOrFail();

        Mail::assertNothingSent();
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event) use ($user) {
            return $event->level === 'info'
                && str_contains($event->message, 'Email verification code')
                && $event->context['code'] === $user->verification_code
                && $event->context['email'] === 'dev@example.com';
        });

        app()->detectEnvironment(fn () => 'testing');
    }

    public function test_production_environment_emails_the_code(): void
    {
        app()->detectEnvironment(fn () => 'production');

        Mail::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Remy',
            'email' => 'prod@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(201);

        $user = User::where('email', 'prod@example.com')->firstOrFail();

        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use ($user) {
            return $mail->hasTo('prod@example.com')
                && $mail->verificationCode === $user->verification_code;
        });

        app()->detectEnvironment(fn () => 'testing');
    }

    public function test_register_does_not_leak_the_code_in_the_response(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Remy',
            'email' => 'remy2@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(201)
            ->assertJsonMissingPath('data.verification_code')
            ->assertJsonPath('data.converted_guest', false);
    }

    public function test_login_response_does_not_leak_the_code(): void
    {
        $user = User::factory()->create([
            'email' => 'a@example.com',
            'password' => 'secret123',
            'email_verified_at' => null,
            'verification_code' => '123456',
            'verification_expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'a@example.com',
            'password' => 'secret123',
        ])->assertOk()
            ->assertJsonMissingPath('data.user.verification_code')
            ->assertJsonMissingPath('data.user.verification_expires_at');
    }

    public function test_verify_with_valid_code_marks_email_verified_and_clears_code(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'email' => 'v@example.com',
            'email_verified_at' => null,
            'verification_code' => '246810',
            'verification_expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/auth/email/verify', [
            'email' => 'v@example.com',
            'verification_code' => '246810',
        ])->assertOk()->assertJson([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasVerifiedEmail());
        $this->assertNull($fresh->verification_code);
        $this->assertNull($fresh->verification_expires_at);

        Event::assertDispatched(Verified::class);
    }

    public function test_verify_with_wrong_code_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'v2@example.com',
            'email_verified_at' => null,
            'verification_code' => '111111',
            'verification_expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/auth/email/verify', [
            'email' => 'v2@example.com',
            'verification_code' => '999999',
        ])->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Invalid verification code.']);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_with_expired_code_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'v3@example.com',
            'email_verified_at' => null,
            'verification_code' => '333333',
            'verification_expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/auth/email/verify', [
            'email' => 'v3@example.com',
            'verification_code' => '333333',
        ])->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Verification code has expired.']);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_with_unknown_email_is_rejected(): void
    {
        $this->postJson('/api/auth/email/verify', [
            'email' => 'nobody@example.com',
            'verification_code' => '123456',
        ])->assertStatus(404);
    }

    public function test_verify_with_invalid_payload_is_rejected(): void
    {
        $this->postJson('/api/auth/email/verify', [
            'email' => 'not-an-email',
            'verification_code' => '12',
        ])->assertStatus(422);
    }

    public function test_verify_when_already_verified_returns_success(): void
    {
        $user = User::factory()->create([
            'email' => 'v4@example.com',
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_expires_at' => null,
        ]);

        $this->postJson('/api/auth/email/verify', [
            'email' => 'v4@example.com',
            'verification_code' => '000000',
        ])->assertOk()->assertJson(['success' => true, 'message' => 'Email already verified.']);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_regenerates_code_and_emails_it(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'r@example.com',
            'email_verified_at' => null,
            'verification_code' => '111111',
            'verification_expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/auth/email/resend', [
            'email' => 'r@example.com',
        ])->assertOk()->assertJson(['success' => true]);

        $fresh = $user->fresh();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $fresh->verification_code);
        $this->assertTrue($fresh->verification_expires_at->greaterThan(now()));

        Mail::assertSent(VerificationCodeMail::class);
    }

    public function test_resend_for_unregistered_email_is_rejected(): void
    {
        $this->postJson('/api/auth/email/resend', [
            'email' => 'ghost@example.com',
        ])->assertStatus(404);
    }

    public function test_resend_when_already_verified_returns_success_without_emails(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'rv@example.com',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/auth/email/resend', [
            'email' => 'rv@example.com',
        ])->assertOk()->assertJson(['success' => true, 'message' => 'Email already verified.']);

        Mail::assertNothingSent();
    }

    public function test_unverified_user_is_blocked_from_verified_routes(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('Android')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/riddles')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'message' => 'Your email address is not verified.']);
    }

    public function test_verified_user_can_access_verified_routes(): void
    {
        $user = User::factory()->create([
            'email' => 'ok@example.com',
            'email_verified_at' => null,
            'verification_code' => '555555',
            'verification_expires_at' => now()->addMinutes(10),
        ]);
        $token = $user->createToken('Android')->plainTextToken;

        $this->postJson('/api/auth/email/verify', [
            'email' => 'ok@example.com',
            'verification_code' => '555555',
        ])->assertOk();

        $this->withToken($token)
            ->getJson('/api/riddles')
            ->assertOk();
    }
}