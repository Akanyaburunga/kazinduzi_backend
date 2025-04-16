<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\MeaningController;
use App\Http\Controllers\VoteController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ModerationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

$middleware = ['auth'];
if (app()->environment('production')) {
    $middleware[] = 'verified';
}

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/words', [WordController::class, 'index'])->name('words.index');
//Searches for words
Route::get('/autocomplete', [SearchController::class, 'autocomplete'])->name('autocomplete');
Route::get('/words/autocomplete', [WordController::class, 'autocomplete'])->name('words.autocomplete');

Route::middleware($middleware)->group(function () {
    //Profile
    Route::get('/reputation', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');
    Route::put('/profile/change', [ProfileController::class, 'change'])->name('profile.change');
    Route::get('/profile/view', [ProfileController::class, 'view'])->name('profile.view');

    //Words
    Route::get('/words/create', [WordController::class, 'create'])->name('words.create');
    Route::post('/words', [WordController::class, 'store'])->name('words.store');
    //Edit and delete words
    Route::get('/words/{word}/edit', [WordController::class, 'edit'])->name('words.edit');
    Route::put('/words/{word}', [WordController::class, 'update'])->name('words.update');
    Route::delete('/words/{word}', [WordController::class, 'destroy'])->name('words.destroy');

    Route::post('/words/{word}/meanings', [MeaningController::class, 'store'])->name('meanings.store');

    //Moderation
    Route::post('/moderation/ban/{user}', [ModerationController::class, 'banUser'])->name('moderation.ban');
    Route::post('/moderation/suspend/word/{word}', [ModerationController::class, 'suspendWord'])->name('moderation.suspend.word');
    Route::post('/moderation/suspend/meaning/{meaning}', [ModerationController::class, 'suspendMeaning'])->name('moderation.suspend.meaning');

    Route::post('/moderation/unsuspend/word/{word}', [ModerationController::class, 'unsuspendWord'])->name('moderation.unsuspend.word');
    Route::post('/moderation/unsuspend/meaning/{meaning}', [ModerationController::class, 'unsuspendMeaning'])->name('moderation.unsuspend.meaning');
    
    //Votes
    Route::post('/meanings/{meaning}/vote', [VoteController::class, 'store'])
    ->name('meanings.vote')
    ->middleware('auth.vote');
});

Route::get('/words/{word}', [WordController::class, 'show'])->name('words.show');
Route::get('/search', [WordController::class, 'search'])->name('words.search');
Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');
Route::get('/leaderboard/{filter?}', [LeaderboardController::class, 'index'])->name('leaderboard');
Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');

require __DIR__.'/auth.php';
