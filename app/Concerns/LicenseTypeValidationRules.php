<?php

namespace App\Concerns;

use App\Enums\LicenseAppliesTo;
use App\Support\SafeText;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

trait LicenseTypeValidationRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeLicenseTypeInput(array $input): array
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = SafeText::normalize($input['name']);
        }

        if (is_string($input['code'] ?? null)) {
            $input['code'] = Str::trim($input['code']);
        }

        return $input;
    }

    /**
     * Identifiers (country_code, code, applies_to) are only accepted when creating.
     *
     * @return array<string, list<string|Exists|In|Unique|Closure>>
     */
    protected function licenseTypeRules(bool $creating, mixed $countryCode): array
    {
        if (! $creating) {
            return [
                'country_code' => ['missing'],
                'code' => ['missing'],
                'applies_to' => ['missing'],
                'name' => ['required', 'string', 'max:160', SafeText::rule()],
                'is_active' => ['missing'],
            ];
        }

        $code = ['bail', 'required', 'string', 'regex:/\A[A-Z][A-Z0-9_-]{0,39}\z/'];
        if (is_string($countryCode) && preg_match('/\A[A-Z]{2}\z/', $countryCode) === 1) {
            $code[] = Rule::unique('license_types', 'code')->where('country_code', $countryCode);
        }

        return [
            'country_code' => ['bail', 'required', 'string', 'regex:/\A[A-Z]{2}\z/', Rule::exists('countries', 'iso2')],
            'code' => $code,
            'applies_to' => ['required', 'string', Rule::in(LicenseAppliesTo::values())],
            'name' => ['required', 'string', 'max:160', SafeText::rule()],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function licenseTypeMessages(): array
    {
        return [
            'code.regex' => __('Use upper-case letters, digits, hyphens or underscores, starting with a letter (for example DENTIST_LIC).'),
        ];
    }
}
