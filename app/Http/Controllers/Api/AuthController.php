<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail; // Mail class
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\App; // Import App facade

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

        // Create API token
        $token = $user->createToken('AndroidApp')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get Authenticated User Info
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
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
            'message' => 'Registration successful. A verification code has been sent to your email.'
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
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Check if code is correct and not expired
        if ($user->verification_code !== $request->verification_code) {
            return response()->json(['message' => 'Invalid verification code.'], 400);
        }

        if (Carbon::now()->gt($user->verification_expires_at)) {
            return response()->json(['message' => 'Verification code has expired.'], 400);
        }

        // Mark email as verified
        $user->email_verified_at = Carbon::now();
        $user->verification_code = null;
        $user->verification_expires_at = null;
        $user->save();

        return response()->json(['message' => 'Email verified successfully.'], 200);
    }

    // Resend Verification Email
    public function resendVerificationCode(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified.'], 200);
        }

        // Generate new code
        $verificationCode = mt_rand(100000, 999999);
        $user->verification_code = $verificationCode;
        $user->verification_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        // Send email
        $this->sendVerificationCode($user);

        return response()->json(['message' => 'A new verification code has been sent.'], 200);
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

}
