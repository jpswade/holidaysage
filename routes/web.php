<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::prefix('searches')->name('searches.')->group(function (): void {
    Route::get('/', [SearchController::class, 'index'])->name('index');
    Route::get('/create', [SearchController::class, 'create'])->name('create');
    Route::post('/', [SearchController::class, 'store'])->name('store');
    Route::post('/import', [SearchController::class, 'import'])->name('import');
    Route::get('/{search}', [SearchController::class, 'show'])->name('show');
    Route::post('/{search}/refresh', [SearchController::class, 'refresh'])->name('refresh');
    Route::get('/{search}/results', [SearchController::class, 'results'])->name('results');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
