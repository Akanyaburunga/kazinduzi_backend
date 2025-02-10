<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\WordController;
use App\Http\Controllers\Api\LeaderboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by the RouteServiceProvider within a group
| assigned the "api" middleware group. Enjoy building your API!
|
*/

/**
 * 🔐 Authentication & User Management
 */
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);  // User Registration
    Route::post('login', [AuthController::class, 'login']);        // User Login
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum'); // Logout
    Route::get('user', [AuthController::class, 'user'])->middleware('auth:sanctum'); // Get Authenticated User Info
    //Email Verification
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])->middleware('auth:sanctum');
});

/**
 * 👤 User Routes
 */
Route::prefix('users')->middleware('auth:sanctum', 'verified')->group(function () {
    Route::get('/', [UserController::class, 'index']);     // List Users
    Route::get('{id}', [UserController::class, 'show']);   // Get Specific User
    Route::post('{id}/profile-picture', [UserController::class, 'updateProfilePicture']); // Update Profile Picture
});

/**
 * 📖 Word & Meaning Management
 */
// ✅ Get all words with optional search query
Route::get('/words', [WordController::class, 'index']);
// ✅ Get top 10 contributors
Route::get('leaderboard', [LeaderboardController::class, 'index']);