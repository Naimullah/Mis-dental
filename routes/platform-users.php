<?php

use App\Http\Controllers\Platform\UserController;
use App\Models\Permission;
use Illuminate\Support\Facades\Route;

Route::prefix('/platform/security/users')
    ->middleware(['auth', 'can:'.Permission::MANAGE_USERS])
    ->name('platform.security.users.')
    ->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('/{account}', [UserController::class, 'update'])->whereNumber('account')->name('update');
        Route::patch('/{account}/status', [UserController::class, 'status'])->whereNumber('account')->name('status');
    });
