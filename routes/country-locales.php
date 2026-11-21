<?php

use App\Http\Controllers\Platform\CountryLocaleController;
use App\Http\Middleware\BlockCountryMutationsAndExports;
use Illuminate\Support\Facades\Route;

// P01.04 Country languages. Access is gated by the temporary master-data switch until RBAC exists.
Route::prefix('platform/master-data/countries/{country}/locales')
    ->name('platform.master-data.country-locales.')
    // Blocked like other Countries changes until explicit platform permissions are wired.
    ->middleware(['auth', 'can:manage-master-data', BlockCountryMutationsAndExports::class])
    ->where(['country' => '[A-Z]{2}', 'locale' => '[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*'])
    ->group(function () {
        Route::post('/', [CountryLocaleController::class, 'store'])->name('store');
        Route::patch('/{locale}/default', [CountryLocaleController::class, 'updateDefault'])->name('default.update');
        Route::delete('/{locale}', [CountryLocaleController::class, 'destroy'])->name('destroy');
    });
