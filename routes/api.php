<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\WordController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\Riddle\GameController;
use App\Http\Controllers\Api\Riddle\AnswerController;
use App\Http\Controllers\Api\Riddle\RiddleController;
use App\Http\Controllers\Api\Riddle\CategoryController;

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
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('api.verification.verify');
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

/**
 * 🧩 Riddle Game
 */
Route::prefix('riddles')->group(function () {
    // Game-facing routes (authenticated + verified)
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);      // List categories (curator view)
        Route::get('/', [GameController::class, 'index']);                    // List riddles (no answers)
        Route::get('/daily', [GameController::class, 'daily']);               // Riddle of the day
        Route::get('/{riddle}', [GameController::class, 'show']);             // Single riddle (no answer)
        Route::post('/{riddle}/answer', [AnswerController::class, 'store']);  // Submit an answer
    });

    // Curator routes (reputation-gated)
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::post('/', [RiddleController::class, 'store']);
        Route::put('/{riddle}', [RiddleController::class, 'update']);
        Route::delete('/{riddle}', [RiddleController::class, 'destroy']);
        Route::post('/{riddle}/suspend', [RiddleController::class, 'suspend']);
        Route::post('/{riddle}/unsuspend', [RiddleController::class, 'unsuspend']);
    });
});