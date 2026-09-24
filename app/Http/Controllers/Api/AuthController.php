<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\JokeAttempt;
use App\Models\ProverbAttempt;
use App\Models\RiddleAttempt;
use App\Models\Round;
use App\Support\GuestLimits;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\VerificationCodeMail;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * User Login (for Android API)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.']
            ]);
        }

        $deviceName = $request->input('device_name', 'AndroidApp');

        // Single active token per device: revoke that device's previous tokens.
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $this->issueToken($user, $deviceName);

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => [
                'user' => $user,
                'token' => $token['token'],
                'token_type' => $token['token_type'],
                'expires_at' => $token['expires_at'],
            ],
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $deviceName = $request->input('device_name');

        if ($deviceName) {
            $request->user()->tokens()->where('name', $deviceName)->delete();
        } else {
            $request->user()->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
            'data' => null,
        ]);
    }

    /**
     * Start or resume a guest (no-account) session.
     *
     * The device submits a persistent anonymous guest_uid. A guest user row
     * is created on first sight and reused on later calls, so round history
     * and progress survive app restarts on the same device.
     */
    public function guestSession(Request $request)
    {
        $request->validate([
            'guest_uid' => 'required|string|max:64',
        ]);

        $user = User::query()->where('guest_uid', $request->guest_uid)->first();

        if (! $user) {
            $user = User::create([
                'name' => 'Guest',
                'email' => null,
                'password' => Str::random(40),
                'guest_uid' => $request->guest_uid,
            ]);
        }

        // Single active token per guest device: revoke previous guest tokens.
        $user->tokens()->where('name', 'GuestApp')->delete();

        $token = $this->issueToken($user, 'GuestApp');

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Guest session started.',
            'data' => [
                'user' => $user,
                'token' => $token['token'],
                'token_type' => $token['token_type'],
                'expires_at' => $token['expires_at'],
                'guest' => GuestLimits::summary($user),
            ],
        ]);
    }

    /**
     * Guest status for the authenticated user (limits + usage per mode).
     */
    public function guestStatus(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->isGuest()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'guest' => false,
                    'summary' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'guest' => true,
                'summary' => GuestLimits::summary($user),
            ],
        ]);
    }

    /**
     * Get Authenticated User Info
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
        ]);
    }

    /**
     * User Registration (for Android API)
     *
     * Creates an unverified user, generates a 6-digit verification code valid
     * for 10 minutes and emails it. The account is not usable until
     * POST /api/auth/email/verify confirms the code.
     *
     * When register is called from a converting guest session, the caller
     * should send the same `guest_uid` it has been playing with. Any rounds
     * and attempts recorded under that guest identity are transferred to the
     * new account and the guest row is deleted.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'guest_uid' => 'nullable|string|max:64',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'verification_code' => (string) random_int(100000, 999999),
            'verification_expires_at' => now()->addMinutes(10), // Code expires in 10 min
        ]);

        $convertedGuest = false;

        if ($request->filled('guest_uid')) {
            $convertedGuest = $this->mergeGuest($user, $request->guest_uid);
        }

        // Send email with the verification code.
        $this->sendVerificationCode($user);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. A verification code has been sent to your email.',
            'data' => [
                'converted_guest' => $convertedGuest,
            ],
        ], 201);
    }

    /**
     * Transfer a guest's rounds and attempts onto a freshly-registered account.
     *
     * Returns true when a guest with the given uid was found and merged.
     * The merge runs in a transaction so a partial transfer can never happen
     * (e.g. guest register + app crash mid-transfer).
     */
    protected function mergeGuest(User $user, string $guestUid): bool
    {
        $guest = User::query()->where('guest_uid', $guestUid)->first();

        if (! $guest || $guest->id === $user->id) {
            return false;
        }

        DB::transaction(function () use ($guest, $user) {
            Round::query()->where('user_id', $guest->id)->update(['user_id' => $user->id]);
            RiddleAttempt::query()->where('user_id', $guest->id)->update(['user_id' => $user->id]);
            ProverbAttempt::query()->where('user_id', $guest->id)->update(['user_id' => $user->id]);
            JokeAttempt::query()->where('user_id', $guest->id)->update(['user_id' => $user->id]);

            $guest->tokens()->delete();
            $guest->delete();
        });

        return true;
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'verification_code' => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['success' => true, 'message' => 'Email already verified.'], 200);
        }

        // Check if code is correct and not expired
        if ($user->verification_code !== $request->verification_code) {
            return response()->json(['success' => false, 'message' => 'Invalid verification code.'], 400);
        }

        if (now()->gt($user->verification_expires_at)) {
            return response()->json(['success' => false, 'message' => 'Verification code has expired.'], 400);
        }

        // Mark email as verified and fire the framework Verified event, which
        // awards referral reputation to the referring user.
        $user->markEmailAsVerified();
        event(new \Illuminate\Auth\Events\Verified($user));
        $user->forceFill([
            'verification_code' => null,
            'verification_expires_at' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
            'data' => null,
        ], 200);
    }

    // Resend Verification Code
    public function resendVerificationCode(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['success' => true, 'message' => 'Email already verified.'], 200);
        }

        // Generate new code
        $user->forceFill([
            'verification_code' => (string) random_int(100000, 999999),
            'verification_expires_at' => now()->addMinutes(10),
        ])->save();

        // Send email
        $this->sendVerificationCode($user);

        return response()->json([
            'success' => true,
            'message' => 'A new verification code has been sent.',
            'data' => null,
        ], 200);
    }

    /**
     * Change the authenticated user's password and revoke all tokens.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Revoke all tokens so other devices must sign in again.
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password updated. Please sign in again.',
            'data' => null,
        ]);
    }

    /**
     * Deliver the 6-digit code to the user.
     *
     * In the local (dev) environment the code is written to the Laravel log
     * instead of being emailed — no mail server (e.g. Mailpit) is required, so
     * the mobile frontend must NOT surface any "mail unreachable" toasts in
     * dev builds. In every other environment (staging, production, ...) a
     * normal verification email is sent through the configured MAIL_* sender.
     *
     * If `app.env` is `local`, log; otherwise send email.
     */
    public function sendVerificationCode(User $user): void
    {
        if (app()->environment('local')) {
            Log::info(
                'Email verification code for {email}: {code} (expires {expires_at})',
                [
                    'email' => $user->email,
                    'code' => $user->verification_code,
                    'expires_at' => $user->verification_expires_at?->toIso8601String(),
                    'user_id' => $user->id,
                ]
            );

            return;
        }

        Mail::to($user->email)->send(new VerificationCodeMail($user->verification_code));
    }

    /**
     * Create a Sanctum token and return its plain-text value with metadata.
     *
     * Expiry is read from SANCTUM_TOKEN_EXPIRY (minutes); empty/unset means no expiry.
     */
    protected function issueToken(User $user, string $deviceName): array
    {
        $expiryMinutes = env('SANCTUM_TOKEN_EXPIRY');
        $expiresAt = $expiryMinutes
            ? Carbon::now()->addMinutes((int) $expiryMinutes)
            : null;

        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toISOString(),
        ];
    }

}
