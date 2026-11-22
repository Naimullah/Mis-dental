<?php

use App\Http\Controllers\Platform\CustomPermissionController;
use App\Http\Controllers\Platform\PermissionController;
use App\Http\Controllers\Platform\PermissionTranslationController;
use App\Models\Permission;
use Illuminate\Support\Facades\Route;

Route::get('/platform/security/permissions', [PermissionController::class, 'index'])
    ->middleware(['auth', 'can:'.Permission::VIEW_CATALOG])
    ->name('platform.security.permissions.index');

Route::prefix('/platform/security/permissions')
    ->middleware(['auth', 'can:'.Permission::MANAGE_DEFINITIONS])
    ->name('platform.security.permissions.')
    ->group(function () {
        Route::post('/', [CustomPermissionController::class, 'store'])->name('store');
        Route::put('/{permission}', [CustomPermissionController::class, 'update'])->whereNumber('permission')->name('update');
        Route::delete('/{permission}', [CustomPermissionController::class, 'destroy'])->whereNumber('permission')->name('destroy');
    });

Route::prefix('/platform/security/permissions/{permission}/translations')
    ->where(['permission' => '[0-9]+', 'locale' => '[A-Za-z0-9-]{1,20}'])
    ->middleware(['auth', 'can:'.Permission::MANAGE_TRANSLATIONS])
    ->name('platform.security.permissions.translations.')
    ->group(function () {
        Route::put('/{locale}', [PermissionTranslationController::class, 'update'])->name('update');
        Route::delete('/{locale}', [PermissionTranslationController::class, 'destroy'])->name('destroy');
    });
