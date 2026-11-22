<?php

use App\Http\Controllers\Platform\RoleController;
use App\Models\Permission;
use Illuminate\Support\Facades\Route;

// P03.04 Roles. No delete route: roles are kept until role assignments can be checked.
Route::prefix('platform/security/roles')
    ->name('platform.security.roles.')
    ->middleware(['auth', 'can:'.Permission::MANAGE_ROLES])
    ->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::put('/{role}', [RoleController::class, 'update'])->whereNumber('role')->name('update');
    });
