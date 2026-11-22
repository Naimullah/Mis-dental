<?php

use App\Http\Controllers\Platform\CountryLocaleController;
use Illuminate\Support\Facades\Route;

// P01.04 Country languages. Access is gated by the temporary master-data switch until RBAC exists.
Route::prefix('platform/master-data/countries/{country}/locales')
    ->name('platform.master-data.country-locales.')
    // Unlocked behind the temporary master-data switch (owner decision 2026-11-22); Countries' own edits stay blocked.
    ->middleware(['auth', 'can:manage-master-data'])
    ->where(['country' => '[A-Z]{2}', 'locale' => '[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*'])
    ->group(function () {
        Route::post('/', [CountryLocaleController::class, 'store'])->name('store');
        Route::patch('/{locale}/default', [CountryLocaleController::class, 'updateDefault'])->name('default.update');
        Route::delete('/{locale}', [CountryLocaleController::class, 'destroy'])->name('destroy');
    });
