<?php

namespace App\Concerns;

use App\Support\SafeText;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

trait AdministrativeAreaValidationRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeAreaInput(array $input): array
    {
        if (is_string($input['canonical_name'] ?? null)) {
            $input['canonical_name'] = SafeText::normalize($input['canonical_name']);
        }

        if (is_string($input['level_code'] ?? null)) {
            $input['level_code'] = Str::trim($input['level_code']);
        }

        if (is_string($input['area_code'] ?? null)) {
            $code = Str::trim($input['area_code']);
            $input['area_code'] = $code === '' ? null : $code;
        }

        if (($input['parent_id'] ?? null) === '') {
            $input['parent_id'] = null;
        }

        return $input;
    }

    /**
     * Hierarchy rules (same country, no cycles, depth, activation) are checked by the
     * service under a country lock; these are the field rules.
     *
     * @return array<string, list<string|Exists|Unique|Closure>>
     */
    protected function areaRules(bool $creating, mixed $countryCode, ?int $areaId = null): array
    {
        $areaCode = ['nullable', 'string', 'max:50', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/'];

        if (is_string($countryCode) && preg_match('/\A[A-Z]{2}\z/', $countryCode) === 1) {
            $areaCode[] = Rule::unique('administrative_areas', 'area_code')
                ->where('country_code', $countryCode)
                ->ignore($areaId);
        }

        return [
            'country_code' => $creating
                ? ['bail', 'required', 'string', 'regex:/\A[A-Z]{2}\z/', Rule::exists('countries', 'iso2')]
                : ['missing'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'level_code' => ['required', 'string', 'max:40', 'regex:/\A[a-z][a-z0-9_-]*\z/'],
            'area_code' => $areaCode,
            'canonical_name' => ['required', 'string', 'max:160', SafeText::rule()],
            // Status changes after creation go through activate/deactivate.
            'is_active' => $creating ? ['sometimes', 'required', 'boolean'] : ['missing'],
        ];
    }

    /** @return array<string, string> */
    protected function areaMessages(): array
    {
        return [
            'level_code.regex' => __('Use a lowercase level code such as province, district or state.'),
            'area_code.regex' => __('Use letters, digits, dots, hyphens or underscores for the area code.'),
        ];
    }
}
