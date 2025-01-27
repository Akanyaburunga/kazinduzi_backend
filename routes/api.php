<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WordController;
use App\Http\Controllers\MeaningController;
use App\Http\Controllers\VoteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\ReputationController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\SearchController;

// Authentication Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

// Word Routes
Route::get('/words', [WordController::class, 'index']);
Route::get('/words/{id}', [WordController::class, 'show']);
Route::post('/words', [WordController::class, 'store'])->middleware('auth:sanctum');
Route::put('/words/{id}', [WordController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/words/{id}', [WordController::class, 'destroy'])->middleware('auth:sanctum');

// Meaning Routes
Route::get('/words/{id}/meanings', [MeaningController::class, 'index']);
Route::post('/words/{id}/meanings', [MeaningController::class, 'store'])->middleware('auth:sanctum');
Route::put('/meanings/{id}', [MeaningController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/meanings/{id}', [MeaningController::class, 'destroy'])->middleware('auth:sanctum');

// Voting Routes
Route::post('/meanings/{id}/upvote', [VoteController::class, 'upvote'])->middleware('auth:sanctum');
Route::post('/meanings/{id}/downvote', [VoteController::class, 'downvote'])->middleware('auth:sanctum');

// User Dashboard Routes
Route::get('/user/contributions', [UserController::class, 'contributions'])->middleware('auth:sanctum');
Route::get('/user/reputation', [UserController::class, 'reputation'])->middleware('auth:sanctum');

// Reputation Routes
Route::get('/reputation/logs', [ReputationController::class, 'logs'])->middleware('auth:sanctum');

// Affiliate Program Routes
Route::post('/affiliate/code', [AffiliateController::class, 'generateCode'])->middleware('auth:sanctum');
Route::post('/affiliate/redeem', [AffiliateController::class, 'redeemCode'])->middleware('auth:sanctum');

// Activity Log Routes
Route::get('/activity/logs', [ActivityLogController::class, 'index'])->middleware('auth:sanctum');

// Search Routes
Route::get('/search', [SearchController::class, 'search']);
Route::get('/search/autocomplete', [SearchController::class, 'autocomplete']);
