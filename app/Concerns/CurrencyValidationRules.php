<?php

namespace App\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait CurrencyValidationRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeCurrencyInput(array $input): array
    {
        if (is_string($input['english_name'] ?? null)) {
            $input['english_name'] = Str::trim($input['english_name']);
        }

        if (is_string($input['symbol'] ?? null)) {
            $symbol = Str::trim($input['symbol']);
            $input['symbol'] = $symbol === '' ? null : $symbol;
        }

        return $input;
    }

    /** @return array<string, list<string|Unique>> */
    protected function currencyRules(bool $creating): array
    {
        return [
            'code' => $creating
                ? ['bail', 'required', 'string', 'regex:/\A[A-Z]{3}\z/', Rule::unique('currencies', 'code')]
                : ['missing'],
            'english_name' => ['required', 'string', 'max:100'],
            'symbol' => ['bail', 'nullable', 'string', 'max:12'],
            'minor_units' => ['bail', 'required', 'integer', 'between:0,4'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
