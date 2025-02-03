<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\MeaningController;
use App\Http\Controllers\VoteController;

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

Route::middleware($middleware)->group(function () {
    //Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');

    //Words
    Route::get('/words/create', [WordController::class, 'create'])->name('words.create');
    Route::post('/words', [WordController::class, 'store'])->name('words.store');
    //Edit and delete words
    Route::get('/words/{word}/edit', [WordController::class, 'edit'])->name('words.edit');
    Route::put('/words/{word}', [WordController::class, 'update'])->name('words.update');
    Route::delete('/words/{word}', [WordController::class, 'destroy'])->name('words.destroy');

    Route::post('/words/{word}/meanings', [MeaningController::class, 'store'])->name('meanings.store');
    
    //Votes
    Route::post('/meanings/{meaning}/vote', [VoteController::class, 'store'])
    ->name('meanings.vote')
    ->middleware('auth.vote');
});

Route::get('/words/{word}', [WordController::class, 'show'])->name('words.show');
Route::get('/search', [WordController::class, 'search'])->name('words.search');


require __DIR__.'/auth.php';
