<?php

use App\Http\Controllers\Platform\TaxCodeController;
use Illuminate\Support\Facades\Route;

// P01.10 Tax codes. Access is gated by the temporary master-data switch until RBAC exists.
// There is deliberately no delete route: versions are historical records.
Route::prefix('platform/master-data/tax-codes')
    ->name('platform.master-data.tax-codes.')
    ->middleware(['auth', 'can:manage-master-data'])
    ->group(function () {
        Route::get('/', [TaxCodeController::class, 'index'])->name('index');
        Route::post('/', [TaxCodeController::class, 'store'])->name('store');
        Route::get('/export/xlsx', [TaxCodeController::class, 'exportXlsx'])->name('export.xlsx');
        Route::get('/export/pdf', [TaxCodeController::class, 'exportPdf'])->name('export.pdf');
        Route::put('/{taxCode}', [TaxCodeController::class, 'update'])->whereNumber('taxCode')->name('update');
        Route::patch('/{taxCode}/close', [TaxCodeController::class, 'close'])->whereNumber('taxCode')->name('close');
        Route::post('/{taxCode}/versions', [TaxCodeController::class, 'storeVersion'])->whereNumber('taxCode')->name('versions.store');
        // P02.05 translated names and explanations of one version.
        Route::put('/{taxCode}/translations', [TaxCodeController::class, 'updateTranslations'])->whereNumber('taxCode')->name('translations.update');
    });
