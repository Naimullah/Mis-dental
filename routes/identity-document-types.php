<?php

use App\Http\Controllers\Platform\IdentityDocumentTypeController;
use Illuminate\Support\Facades\Route;

// P01.08 Identity document types. Access is gated by the temporary master-data switch until RBAC exists.
Route::prefix('platform/master-data/identity-document-types')
    ->name('platform.master-data.identity-document-types.')
    ->middleware(['auth', 'can:manage-master-data'])
    ->group(function () {
        Route::get('/', [IdentityDocumentTypeController::class, 'index'])->name('index');
        Route::post('/', [IdentityDocumentTypeController::class, 'store'])->name('store');
        Route::get('/export/xlsx', [IdentityDocumentTypeController::class, 'exportXlsx'])->name('export.xlsx');
        Route::get('/export/pdf', [IdentityDocumentTypeController::class, 'exportPdf'])->name('export.pdf');
        Route::put('/{documentType}', [IdentityDocumentTypeController::class, 'update'])->whereNumber('documentType')->name('update');
        Route::put('/{documentType}/translations', [IdentityDocumentTypeController::class, 'updateTranslations'])->whereNumber('documentType')->name('translations.update');
        Route::patch('/{documentType}/status', [IdentityDocumentTypeController::class, 'updateStatus'])->whereNumber('documentType')->name('status.update');
    });
