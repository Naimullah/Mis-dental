<?php

namespace App\Concerns;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

trait LocaleValidationRules
{
    /**
     * Trims text, and turns blank optional values into null.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeLocaleInput(array $input): array
    {
        if (is_string($input['english_name'] ?? null)) {
            $input['english_name'] = Str::trim($input['english_name']);
        }

        foreach (['native_name', 'default_calendar_code', 'default_numbering_system_code'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $value = Str::trim($input[$field]);
                $input[$field] = $value === '' ? null : $value;
            }
        }

        return $input;
    }

    /**
     * @param  mixed  $code  The submitted code when creating, or the stored code when updating.
     * @return array<string, list<string|In|Unique|Closure>>
     */
    protected function localeRules(bool $creating, mixed $code): array
    {
        $catalog = config('locale_catalog', []);
        $expected = is_string($code) ? ($catalog[$code]['direction'] ?? null) : null;

        return [
            'code' => $creating
                ? ['bail', 'required', 'string', Rule::in(array_keys($catalog)), Rule::unique('locales', 'code')]
                : ['missing'],
            'english_name' => ['required', 'string', 'max:100'],
            'text_direction' => [
                'bail',
                'required',
                'string',
                Rule::in(['LTR', 'RTL']),
                function (string $attribute, mixed $value, Closure $fail) use ($code, $expected): void {
                    if ($expected !== null && $value !== $expected) {
                        $fail(__('The text direction for :code must be :direction.', ['code' => $code, 'direction' => $expected]));
                    }
                },
            ],
            // Optional on input: the database default (true) applies when omitted.
            'is_active' => ['sometimes', 'required', 'boolean'],
            'native_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'default_calendar_code' => ['sometimes', 'nullable', 'string', 'max:32', Rule::in(config('locale_options.calendars', []))],
            'default_numbering_system_code' => ['sometimes', 'nullable', 'string', 'max:32', Rule::in(config('locale_options.numbering_systems', []))],
        ];
    }

    /** @return array<string, string> */
    protected function localeMessages(): array
    {
        return [
            'code.in' => __('Choose a language code from the supported list.'),
            'default_calendar_code.in' => __('Choose a CLDR calendar from the supported list.'),
            'default_numbering_system_code.in' => __('Choose a CLDR numbering system from the supported list.'),
        ];
    }
}
