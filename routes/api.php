<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WordController;
use App\Http\Controllers\MeaningController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ReputationController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GeneralController;

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
});

/**
 * 👤 User Routes
 */
Route::prefix('users')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [UserController::class, 'index']);     // List Users
    Route::get('{id}', [UserController::class, 'show']);   // Get Specific User
    Route::post('{id}/profile-picture', [UserController::class, 'updateProfilePicture']); // Update Profile Picture
});

/**
 * 📖 Word & Meaning Management
 */
Route::prefix('words')->group(function () {
    Route::get('/', [WordController::class, 'index']);  // List All Words
    Route::get('{id}', [WordController::class, 'show']);  // Get a Single Word
    Route::post('/', [WordController::class, 'store'])->middleware('auth:sanctum');  // Add New Word
    Route::put('{id}', [WordController::class, 'update'])->middleware('auth:sanctum');  // Update Word
    Route::delete('{id}', [WordController::class, 'destroy'])->middleware('auth:sanctum'); // Delete Word
    Route::get('search', [WordController::class, 'search']); // Search Words with Autocomplete
});

Route::prefix('meanings')->middleware('auth:sanctum')->group(function () {
    Route::post('{word_id}', [MeaningController::class, 'store']);  // Add Meaning to Word
    Route::put('{id}', [MeaningController::class, 'update']);       // Update Meaning
    Route::delete('{id}', [MeaningController::class, 'destroy']);   // Delete Meaning
});

/**
 * 🏆 Leaderboard & Reputation System
 */
Route::prefix('leaderboard')->group(function () {
    Route::get('/', [LeaderboardController::class, 'index']); // Get Leaderboard with Filters
});

Route::prefix('reputation')->middleware('auth:sanctum')->group(function () {
    Route::get('/logs', [ReputationController::class, 'index']); // Get User Reputation Logs
});

/**
 * 🔗 Referral System
 */
Route::prefix('referrals')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ReferralController::class, 'index']); // List User's Referrals
    Route::get('/code', [ReferralController::class, 'getCode']); // Get User's Referral Code
});

/**
 * 🎛️ Admin Panel (Filament)
 */
Route::prefix('admin')->middleware(['auth:sanctum', 'isAdmin'])->group(function () {
    Route::get('dashboard', [AdminController::class, 'dashboard']); // Admin Dashboard
    Route::get('users', [AdminController::class, 'users']); // Manage Users
    Route::get('words', [AdminController::class, 'words']); // Manage Words & Meanings
});

/**
 * 📊 General Utilities & Platform Stats
 */
Route::get('/stats', [GeneralController::class, 'stats']); // Get Platform Stats
Route::get('/settings', [GeneralController::class, 'settings']); // Get Platform Settings

