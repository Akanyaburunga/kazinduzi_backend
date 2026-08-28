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
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');  // User Registration
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');        // User Login
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum'); // Logout
    Route::get('user', [AuthController::class, 'user'])->middleware('auth:sanctum'); // Get Authenticated User Info
    Route::post('password/change', [AuthController::class, 'changePassword'])->middleware('auth:sanctum'); // Change password & revoke tokens
    //Email Verification
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('api.verification.verify');
    Route::post('/email/resend', [AuthController::class, 'resendVerificationCode'])->middleware('auth:sanctum', 'throttle:3,1');
});

/**
 * 👤 Authenticated profile, points & stats
 */
Route::prefix('me')->middleware('auth:sanctum')->group(function () {
    Route::get('/', \App\Http\Controllers\Api\MeController::class);
    Route::get('/levels', [\App\Http\Controllers\Api\MeController::class, 'levels']);
});

/**
 * 💰 Points ledger
 */
Route::prefix('points')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\PointsController::class, 'index']);
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
Route::get('leaderboard', [LeaderboardController::class, 'index'])->middleware('auth:sanctum');

/**
 * 🧩 Riddle Game
 */
Route::prefix('riddles')->group(function () {
    // Game-facing routes (authenticated + verified)
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);      // List categories (curator view)
        Route::get('/', [GameController::class, 'index']);                    // List riddles (no answers)
        Route::get('/trending', [GameController::class, 'trending']);         // Trending riddles (popularity score)
        Route::get('/daily', [GameController::class, 'daily']);               // Riddle of the day
        Route::get('/next', [GameController::class, 'next']);                 // Next unsolved riddle (difficulty filter)
        Route::get('/history', [GameController::class, 'history']);           // Paginated attempt history
        Route::get('/history/stats', [GameController::class, 'historyStats']); // Attempt statistics
        Route::get('/{riddle}', [GameController::class, 'show']);             // Single riddle (no answer)
        Route::get('/{riddle}/hint', [GameController::class, 'hint']);        // Progressive hint(s)
        Route::post('/{riddle}/answer', [AnswerController::class, 'store']);  // Submit an answer
        Route::post('/{riddle}/reveal', [GameController::class, 'reveal']);   // Reveal answer (learning, no reward)
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