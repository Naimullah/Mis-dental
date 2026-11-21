<?php

namespace App\Concerns;

use App\Models\Country;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;

trait CountryValidationRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeCountryInput(array $input): array
    {
        if (is_string($input['english_name'] ?? null)) {
            $input['english_name'] = Str::trim($input['english_name']);
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, list<string|RequiredIf|Unique>>
     */
    protected function countryRules(array $input, bool $creating, ?Country $country = null): array
    {
        $uniqueIso3 = Rule::unique('countries', 'iso3');

        if ($country?->exists) {
            $uniqueIso3->ignore($country->getRawOriginal('iso2'), 'iso2');
        }

        return [
            'iso2' => $creating
                ? ['bail', 'required', 'string', 'regex:/\A[A-Z]{2}\z/', Rule::unique('countries', 'iso2')]
                : ['missing'],
            'iso3' => [
                'bail',
                Rule::requiredIf(($input['iso3'] ?? null) !== null),
                'nullable',
                'string',
                'regex:/\A[A-Z]{3}\z/',
                $uniqueIso3,
            ],
            'english_name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
