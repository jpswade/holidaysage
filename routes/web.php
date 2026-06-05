<?php

use App\Http\Controllers\HolidayBrowseController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SavedHolidaySearchDetailController;
use App\Http\Controllers\SavedShortlistController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SharedHolidayShortlistController;
use App\Http\Controllers\SharedSavedHolidaySearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function (\App\Services\HomepageHighlightQuery $homepageHighlights) {
    return view('welcome', [
        'rails' => $homepageHighlights->railsForHomepage(),
    ]);
})->name('home');

Route::get('/holidays', [HolidayBrowseController::class, 'index'])->name('holidays.index');
Route::get('/holidays/{slug}', [HolidayController::class, 'show'])
    ->where('slug', '[A-Za-z0-9][A-Za-z0-9-]*')
    ->name('holidays.show');

Route::prefix('searches')->name('searches.')->group(function (): void {
    Route::get('/', [SearchController::class, 'index'])->name('index');
    Route::get('/create', [SearchController::class, 'create'])->name('create');
    Route::post('/', [SearchController::class, 'store'])->name('store');
    Route::post('/import', [SearchController::class, 'import'])->name('import');
    Route::get('/{search}/edit', [SearchController::class, 'edit'])->name('edit');
    Route::patch('/{search}', [SearchController::class, 'update'])->name('update');
    Route::get('/{search}', [SearchController::class, 'show'])->name('show');
    Route::get('/{search}/deals/{scoredOption}', [SearchController::class, 'deal'])->name('deals.show');
    Route::post('/{search}/refresh', [SearchController::class, 'refresh'])->name('refresh');
    Route::get('/{search}/results', [SearchController::class, 'results'])->name('results');
});

Route::get('/saved', [SavedShortlistController::class, 'index'])->name('saved.index');
Route::post('/saved/items', [SavedShortlistController::class, 'store'])->name('saved.items.store');
Route::middleware('auth')->group(function (): void {
    Route::delete('/saved/items/{item}', [SavedShortlistController::class, 'destroy'])->name('saved.items.destroy');
    Route::patch('/saved/sharing', [SavedShortlistController::class, 'sharing'])->name('saved.sharing.update');
});
Route::get('/shortlists/shared/{token}', [SharedHolidayShortlistController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('shortlists.shared.show');

Route::get('/saved-searches/{savedHolidaySearch}', [SavedHolidaySearchDetailController::class, 'show'])
    ->whereNumber('savedHolidaySearch')
    ->name('saved-searches.show');
Route::patch('/saved-searches/{savedHolidaySearch}/sharing', [SavedHolidaySearchDetailController::class, 'sharing'])
    ->whereNumber('savedHolidaySearch')
    ->name('saved-searches.sharing.update');
Route::get('/saved-searches/shared/{token}', [SharedSavedHolidaySearchController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('saved-searches.shared.show');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
