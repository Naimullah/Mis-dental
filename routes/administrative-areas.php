<?php

use App\Http\Controllers\Platform\AdministrativeAreaController;
use Illuminate\Support\Facades\Route;

// P01.05/P01.06. Access is gated by the temporary master-data switch until RBAC exists.
Route::prefix('platform/master-data/administrative-areas')
    ->name('platform.master-data.administrative-areas.')
    ->middleware(['auth', 'can:manage-master-data'])
    ->group(function () {
        Route::get('/', [AdministrativeAreaController::class, 'index'])->name('index');
        Route::post('/', [AdministrativeAreaController::class, 'store'])->name('store');
        Route::get('/parents', [AdministrativeAreaController::class, 'parents'])->name('parents');
        Route::get('/export/xlsx', [AdministrativeAreaController::class, 'exportXlsx'])->name('export.xlsx');
        Route::get('/export/pdf', [AdministrativeAreaController::class, 'exportPdf'])->name('export.pdf');
        Route::put('/{area}', [AdministrativeAreaController::class, 'update'])->whereNumber('area')->name('update');
        Route::patch('/{area}/status', [AdministrativeAreaController::class, 'updateStatus'])->whereNumber('area')->name('status.update');
        Route::put('/{area}/translations', [AdministrativeAreaController::class, 'updateTranslations'])->whereNumber('area')->name('translations.update');
    });
