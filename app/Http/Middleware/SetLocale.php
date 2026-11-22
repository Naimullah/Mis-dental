<?php

namespace App\Http\Middleware;

use App\Support\InterfaceLocales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession() ? $request->session()->get('locale') : null;
        $locale ??= $request->user()?->locale_code;

        // A language disabled in the locales table falls back to the default language.
        if (app(InterfaceLocales::class)->isEnabled($locale)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
