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
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RiddleController;
use App\Http\Controllers\Admin\RiddleCategoryController;

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

// Admin panel host (Vue SPA). Serves the same shell for any /admin route.
Route::prefix('admin/api')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/session', [SessionController::class, 'show']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/riddles', [RiddleController::class, 'index']);
    Route::post('/riddles', [RiddleController::class, 'store']);
    Route::get('/riddles/{riddle}', [RiddleController::class, 'show']);
    Route::put('/riddles/{riddle}', [RiddleController::class, 'update']);
    Route::delete('/riddles/{riddle}', [RiddleController::class, 'destroy']);
    Route::post('/riddles/{riddle}/suspend', [RiddleController::class, 'suspend']);
    Route::post('/riddles/{riddle}/unsuspend', [RiddleController::class, 'unsuspend']);

    Route::get('/categories', [RiddleCategoryController::class, 'index']);
    Route::post('/categories', [RiddleCategoryController::class, 'store']);
    Route::put('/categories/{category}', [RiddleCategoryController::class, 'update']);
    Route::delete('/categories/{category}', [RiddleCategoryController::class, 'destroy']);
});

Route::middleware('admin')->group(function () {
    Route::get('/admin/{vueRoute?}', function () {
        return view('admin.app');
    })->where('vueRoute', '.*')->name('admin.index');
});

require __DIR__.'/auth.php';
