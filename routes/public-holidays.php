<?php

use App\Http\Controllers\Platform\PublicHolidayController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform/master-data/public-holidays')
    ->name('platform.master-data.public-holidays.')
    ->middleware(['auth', 'can:manage-master-data'])
    ->group(function () {
        Route::get('/', [PublicHolidayController::class, 'index'])->name('index');
        Route::post('/', [PublicHolidayController::class, 'store'])->name('store');
        Route::get('/export/xlsx', [PublicHolidayController::class, 'exportXlsx'])->name('export.xlsx');
        Route::get('/export/pdf', [PublicHolidayController::class, 'exportPdf'])->name('export.pdf');
        Route::put('/{holiday}', [PublicHolidayController::class, 'update'])->whereNumber('holiday')->name('update');
        Route::patch('/{holiday}/status', [PublicHolidayController::class, 'updateStatus'])->whereNumber('holiday')->name('status.update');
    });
