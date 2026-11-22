<?php

use App\Http\Middleware\BlockCountryMutationsAndExports;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/locale.php',
            __DIR__.'/../routes/countries.php',
            __DIR__.'/../routes/currencies.php',
            // Locales management: temporary master-data switch until platform authorization exists.
            __DIR__.'/../routes/locales.php',
            __DIR__.'/../routes/country-locales.php',
            __DIR__.'/../routes/administrative-areas.php',
            __DIR__.'/../routes/license-types.php',
            __DIR__.'/../routes/identity-document-types.php',
            __DIR__.'/../routes/public-holidays.php',
            __DIR__.'/../routes/tax-codes.php',
            __DIR__.'/../routes/permissions.php',
            __DIR__.'/../routes/platform-users.php',
            __DIR__.'/../routes/roles.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToPriorityList(SubstituteBindings::class, BlockCountryMutationsAndExports::class);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
