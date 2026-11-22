<?php

use App\Http\Controllers\Platform\LicenseTypeController;
use Illuminate\Support\Facades\Route;

// P01.07 License types. Access is gated by the temporary master-data switch until RBAC exists.
Route::prefix('platform/master-data/license-types')
    ->name('platform.master-data.license-types.')
    ->middleware(['auth', 'can:manage-master-data'])
    ->group(function () {
        Route::get('/', [LicenseTypeController::class, 'index'])->name('index');
        Route::post('/', [LicenseTypeController::class, 'store'])->name('store');
        Route::get('/export/xlsx', [LicenseTypeController::class, 'exportXlsx'])->name('export.xlsx');
        Route::get('/export/pdf', [LicenseTypeController::class, 'exportPdf'])->name('export.pdf');
        Route::put('/{licenseType}', [LicenseTypeController::class, 'update'])->whereNumber('licenseType')->name('update');
        Route::put('/{licenseType}/translations', [LicenseTypeController::class, 'updateTranslations'])->whereNumber('licenseType')->name('translations.update');
        Route::patch('/{licenseType}/status', [LicenseTypeController::class, 'updateStatus'])->whereNumber('licenseType')->name('status.update');
    });
