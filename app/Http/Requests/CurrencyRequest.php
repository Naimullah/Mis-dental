<?php

namespace App\Http\Requests;

use App\Concerns\CurrencyValidationRules;
use Illuminate\Foundation\Http\FormRequest;

abstract class CurrencyRequest extends FormRequest
{
    use CurrencyValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeCurrencyInput($this->only(['english_name', 'symbol'])));
    }
}
