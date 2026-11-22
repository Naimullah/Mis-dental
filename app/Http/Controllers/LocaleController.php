<?php

namespace App\Http\Controllers;

use App\Support\InterfaceLocales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(app(InterfaceLocales::class)->enabled()))],
        ]);

        $request->session()->put('locale', $validated['locale']);

        return back();
    }
}
