<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use App\Services\CatalogTranslations\CatalogTranslations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CatalogTranslationController extends Controller
{
    public function country(Request $request, Country $country, CatalogTranslations $translations): RedirectResponse
    {
        $translations->update($country, $request->input('translations'));

        return $this->saved($country->english_name);
    }

    public function currency(Request $request, Currency $currency, CatalogTranslations $translations): RedirectResponse
    {
        $translations->update($currency, $request->input('translations'));

        return $this->saved($currency->english_name);
    }

    private function saved(string $name): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Translations for :name saved.', ['name' => $name])]);

        return back();
    }
}
