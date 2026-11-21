<?php

use App\Http\Controllers\Platform\CountryController;
use App\Http\Middleware\BlockCountryMutationsAndExports;
use Illuminate\Support\Facades\Route;

// The temporary master-data switch grants Countries read access only.
Route::prefix('platform/master-data/countries')
    ->name('platform.master-data.countries.')
    ->middleware(['auth', 'can:manage-master-data'])
    ->group(function () {
        Route::get('/', [CountryController::class, 'index'])->name('index');

        Route::middleware(BlockCountryMutationsAndExports::class)->group(function () {
            Route::post('/', [CountryController::class, 'store'])->name('store');
            Route::get('/export/xlsx', [CountryController::class, 'exportXlsx'])->name('export.xlsx');
            Route::get('/export/pdf', [CountryController::class, 'exportPdf'])->name('export.pdf');
            Route::put('/{country}', [CountryController::class, 'update'])
                ->where('country', '[A-Z]{2}')->name('update');
            Route::patch('/{country}/status', [CountryController::class, 'updateStatus'])
                ->where('country', '[A-Z]{2}')->name('status.update');
        });
    });
