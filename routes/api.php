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
use App\Http\Controllers\Api\Riddle\FavoriteController;
use App\Http\Controllers\Api\Riddle\DuelController;
use App\Http\Controllers\Api\Riddle\ShareController;
use App\Http\Controllers\Api\Riddle\SubmissionController;
use App\Http\Controllers\Api\Proverb\ProverbController;
use App\Http\Controllers\Api\Proverb\ProverbGameController;
use App\Http\Controllers\Api\Proverb\ProverbAnswerController;
use App\Http\Controllers\Api\Joke\JokeController;
use App\Http\Controllers\Api\Joke\JokeGameController;
use App\Http\Controllers\Api\Joke\JokeAnswerController;
use App\Http\Controllers\Api\Joke\JokeSubmissionController as ApiJokeSubmissionController;
use App\Http\Controllers\Api\Proverb\ProverbSubmissionController as ApiProverbSubmissionController;
use App\Http\Controllers\Api\Game\RoundController;
use App\Http\Controllers\Api\Game\RoundAnswerController;

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
    Route::get('/achievements', [\App\Http\Controllers\Api\MeController::class, 'achievements']);
    Route::get('/summary', [\App\Http\Controllers\Api\MeController::class, 'summary']);
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
    // Public share resolution (viewed via a link, no auth required)
    Route::get('/share/{code}', [ShareController::class, 'show'])
        ->name('api.riddles.share.show');

    // Game-facing routes (authenticated + verified)
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);      // List categories (curator view)
        Route::get('/', [GameController::class, 'index']);                    // List riddles (no answers)
        Route::get('/trending', [GameController::class, 'trending']);         // Trending riddles (popularity score)
        Route::get('/daily', [GameController::class, 'daily']);               // Riddle of the day
        Route::get('/daily/history', [GameController::class, 'dailyHistory']); // Revisit a past daily riddle
        Route::get('/daily/status', [GameController::class, 'dailyStatus']);   // Notifications badge data
        Route::post('/streak/freeze', [GameController::class, 'useStreakFreeze']); // Spend a streak saver freeze
        Route::get('/next', [GameController::class, 'next']);                 // Next unsolved riddle (difficulty filter)
        Route::get('/history', [GameController::class, 'history']);           // Paginated attempt history
        Route::get('/history/stats', [GameController::class, 'historyStats']); // Attempt statistics
        Route::post('/{riddle}/share', [ShareController::class, 'store']);    // Create shareable short link
        Route::get('/{riddle}', [GameController::class, 'show']);             // Single riddle (no answer)
        Route::get('/{riddle}/hint', [GameController::class, 'hint']);        // Progressive hint(s)
        Route::post('/{riddle}/answer', [AnswerController::class, 'store'])
            ->middleware('throttle:30,1'); // Guard against brute-force answer submissions
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

/**
 * 🔖 Favorites and bookmarks
 */
Route::prefix('me/favorites')->middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/', [FavoriteController::class, 'index']);                 // List bookmarked riddles
    Route::post('/{riddle}', [FavoriteController::class, 'store']);        // Bookmark a riddle
    Route::delete('/{riddle}', [FavoriteController::class, 'destroy']);    // Remove a bookmark
});

/**
 * 🧑‍🌾 User-generated submissions (moderation queue)
 */
Route::prefix('submissions/riddles')->middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/', [SubmissionController::class, 'index']);        // My submissions
    Route::post('/', [SubmissionController::class, 'store']);       // Submit a riddle for review
});

/**
 * ⚔️ Player-versus-player challenge duels
 */
Route::prefix('duels')->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->group(function () {
    Route::get('/', [DuelController::class, 'index']);                        // My challenges
    Route::post('/', [DuelController::class, 'store']);                       // Create a challenge
    Route::get('{challenge}', [DuelController::class, 'show']);               // Live duel status
    Route::post('{challenge}/accept', [DuelController::class, 'accept']);     // Accept a pending duel
    Route::post('{challenge}/decline', [DuelController::class, 'decline']);   // Decline a pending duel
    Route::post('{challenge}/solve', [DuelController::class, 'solve']);       // Submit a single answer
});

/**
 * 📜 Proverbs (Heraheza) — complete the ending
 */
Route::prefix('proverbs')->middleware(['auth:sanctum', 'verified'])->group(function () {
    // Game-facing routes (answers never exposed)
    Route::get('/', [ProverbGameController::class, 'index']);               // List proverbs (no answers)
    Route::get('/next', [ProverbGameController::class, 'next']);            // Next unsolved proverb
    Route::get('/{proverb}', [ProverbGameController::class, 'show']);       // Single proverb (no answer)
    Route::post('/{proverb}/answer', [ProverbAnswerController::class, 'store'])
        ->middleware('throttle:30,1');                                      // Solve (lenient matcher)
    Route::post('/{proverb}/reveal', [ProverbGameController::class, 'reveal']); // Reveal answer (no reward)
});

// Curator routes (reputation-gated)
Route::prefix('proverbs')->middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/', [ProverbController::class, 'store']);
    Route::put('/{proverb}', [ProverbController::class, 'update']);
    Route::delete('/{proverb}', [ProverbController::class, 'destroy']);
    Route::post('/{proverb}/suspend', [ProverbController::class, 'suspend']);
    Route::post('/{proverb}/unsuspend', [ProverbController::class, 'unsuspend']);
});

/**
 * 😄 Jokes (Tujajure) — pick the punchline from four options
 */
Route::prefix('jokes')->middleware(['auth:sanctum', 'verified'])->group(function () {
    // Game-facing routes
    Route::get('/round', [JokeGameController::class, 'round']);               // One round: setup + 4 shuffled options
    Route::get('/next', [JokeGameController::class, 'next']);                 // Next unsolved setup
    Route::post('/{joke}/answer', [JokeAnswerController::class, 'store'])
        ->middleware('throttle:30,1');                                        // Pick a punchline
    Route::post('/{joke}/reveal', [JokeGameController::class, 'reveal']);      // Reveal punchline (no reward)
});

// Curator routes (reputation-gated)
Route::prefix('jokes')->middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/', [JokeController::class, 'store']);
    Route::put('/{joke}', [JokeController::class, 'update']);
    Route::delete('/{joke}', [JokeController::class, 'destroy']);
    Route::post('/{joke}/suspend', [JokeController::class, 'suspend']);
    Route::post('/{joke}/unsuspend', [JokeController::class, 'unsuspend']);
});

/**
 * 🧑‍🌾 User-generated submissions (moderation queue) — proverbs & jokes
 */
Route::prefix('submissions/proverbs')->middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/', [ApiProverbSubmissionController::class, 'index']);   // My submissions
    Route::post('/', [ApiProverbSubmissionController::class, 'store']);  // Submit a proverb for review
});

Route::prefix('submissions/jokes')->middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/', [ApiJokeSubmissionController::class, 'index']);   // My submissions
    Route::post('/', [ApiJokeSubmissionController::class, 'store']);  // Submit a joke for review
});

/**
 * 🎮 Games (Rinjora-parity rounds of 10)
 */
Route::prefix('games')->middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::prefix('{mode}')->where(['mode' => 'sokwe|hera|tuja'])->group(function () {
        Route::post('/rounds', [RoundController::class, 'store']);              // Start a round
        Route::get('/rounds', [RoundController::class, 'index']);               // Recent rounds (resume)
        Route::get('/rounds/{round}', [RoundController::class, 'show']);        // Resume current item
        Route::post('/rounds/{round}/complete', [RoundController::class, 'complete']); // Finalize
        Route::post('/rounds/{round}/items/{position}/answer', [RoundAnswerController::class, 'answer'])
            ->middleware('throttle:30,1');                                      // Play an item
        Route::post('/rounds/{round}/items/{position}/skip', [RoundAnswerController::class, 'skip'])
            ->middleware('throttle:30,1');                                      // Skip == concede
    });
});
