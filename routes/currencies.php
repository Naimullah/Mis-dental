<?php

use App\Http\Controllers\Platform\CatalogTranslationController;
use App\Http\Controllers\Platform\CurrencyController;
use Illuminate\Support\Facades\Route;

// P01.02 Currencies. Access is gated by the temporary master-data switch until RBAC exists.
Route::prefix('platform/master-data/currencies')
    ->name('platform.master-data.currencies.')
    ->middleware(['auth', 'can:manage-master-data'])
    ->group(function () {
        Route::get('/', [CurrencyController::class, 'index'])->name('index');
        Route::put('/{currency}/translations', [CatalogTranslationController::class, 'currency'])
            ->where('currency', '[A-Z]{3}')->name('translations.update');
        Route::post('/', [CurrencyController::class, 'store'])->name('store');
        Route::get('/export/xlsx', [CurrencyController::class, 'exportXlsx'])->name('export.xlsx');
        Route::get('/export/pdf', [CurrencyController::class, 'exportPdf'])->name('export.pdf');
        Route::put('/{currency}', [CurrencyController::class, 'update'])
            ->where('currency', '[A-Z]{3}')->name('update');
        Route::patch('/{currency}/status', [CurrencyController::class, 'updateStatus'])
            ->where('currency', '[A-Z]{3}')->name('status.update');
    });
