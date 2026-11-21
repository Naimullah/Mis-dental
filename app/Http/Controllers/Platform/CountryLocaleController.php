<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Locale;
use App\Services\CountryLocales\CountryLocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** P01.04 links between countries and the languages used there. */
class CountryLocaleController extends Controller
{
    public function __construct(private readonly CountryLocaleService $links) {}

    public function store(Request $request, Country $country): RedirectResponse
    {
        $validated = $request->validate([
            'locale_code' => ['bail', 'required', 'string', Rule::exists('locales', 'code')],
            'is_default' => ['required', 'boolean'],
        ]);

        $locale = Locale::query()->findOrFail($validated['locale_code']);
        $this->links->add($country, $locale, filter_var($validated['is_default'], FILTER_VALIDATE_BOOLEAN));

        return $this->done(__('Language :code added to :country.', ['code' => $locale->code, 'country' => $country->iso2]));
    }

    public function updateDefault(Request $request, Country $country, Locale $locale): RedirectResponse
    {
        $validated = $request->validate(['is_default' => ['required', 'boolean']]);
        $isDefault = filter_var($validated['is_default'], FILTER_VALIDATE_BOOLEAN);

        $this->links->setDefault($country, $locale, $isDefault);

        return $this->done($isDefault
            ? __('Default language for :country set to :code.', ['country' => $country->iso2, 'code' => $locale->code])
            : __('Default language for :country cleared.', ['country' => $country->iso2]));
    }

    public function destroy(Country $country, Locale $locale): RedirectResponse
    {
        $this->links->remove($country, $locale);

        return $this->done(__('Language :code removed from :country.', ['code' => $locale->code, 'country' => $country->iso2]));
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
