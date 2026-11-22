<?php

use App\Http\Controllers\Platform\LocaleController;
use Illuminate\Support\Facades\Route;

// P01.03 Languages (locales). Access is gated by the temporary master-data switch until RBAC exists.
Route::prefix('platform/master-data/locales')
    ->name('platform.master-data.locales.')
    ->middleware(['auth', 'can:manage-master-data'])
    ->group(function () {
        Route::get('/', [LocaleController::class, 'index'])->name('index');
        Route::post('/', [LocaleController::class, 'store'])->name('store');
        Route::get('/export/xlsx', [LocaleController::class, 'exportXlsx'])->name('export.xlsx');
        Route::get('/export/pdf', [LocaleController::class, 'exportPdf'])->name('export.pdf');
        Route::put('/{locale}', [LocaleController::class, 'update'])
            ->where('locale', '[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*')->name('update');
        Route::patch('/{locale}/status', [LocaleController::class, 'updateStatus'])
            ->where('locale', '[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*')->name('status.update');
    });
