<?php

use App\Http\Controllers\Api\V1\CompetitionController;
use App\Http\Controllers\Api\V1\WinnerController;
use Illuminate\Support\Facades\Route;

/*
 * Public, unauthenticated, cacheable GETs. Shapes: docs/API_CONTRACT.md.
 * Browsers and the CDN may cache for as long as the server-side cache (15s).
 */
Route::prefix('v1')->name('api.v1.')->middleware('cache.headers:public;max_age=15;etag')->group(function () {
    Route::get('competitions', [CompetitionController::class, 'index'])->name('competitions.index');
    Route::get('competitions/{slug}', [CompetitionController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('competitions.show');
    Route::get('winners', [WinnerController::class, 'index'])->name('winners.index');
});
