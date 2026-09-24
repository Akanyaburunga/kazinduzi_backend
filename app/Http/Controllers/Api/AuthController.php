<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

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
     * User Registration
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed'
        ]);

        // Generate 6-digit code
        $verificationCode = mt_rand(100000, 999999);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'verification_code' => $verificationCode,
            'verification_expires_at' => Carbon::now()->addMinutes(10) // Code expires in 10 min
        ]);

        // Send email
        $this->sendVerificationCode($user);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. A verification code has been sent to your email.',
            'data' => null,
        ], 201);
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

        // Check if code is correct and not expired
        if ($user->verification_code !== $request->verification_code) {
            return response()->json(['success' => false, 'message' => 'Invalid verification code.'], 400);
        }

        if (Carbon::now()->gt($user->verification_expires_at)) {
            return response()->json(['success' => false, 'message' => 'Verification code has expired.'], 400);
        }

        // Mark email as verified
        $user->email_verified_at = Carbon::now();
        $user->verification_code = null;
        $user->verification_expires_at = null;
        $user->save();

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

        if ($user->email_verified_at) {
            return response()->json(['success' => true, 'message' => 'Email already verified.'], 200);
        }

        // Generate new code
        $verificationCode = mt_rand(100000, 999999);
        $user->verification_code = $verificationCode;
        $user->verification_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

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

    public function sendVerificationCode(User $user)
    {
        if (App::environment('production')) {
            // Send email only in production
            Mail::to($user->email)->send(new VerificationCodeMail($user->verification_code));
        } else {
            // In dev mode, automatically mark the user as verified
            $user->email_verified_at = now();
            $user->save();
        }
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
