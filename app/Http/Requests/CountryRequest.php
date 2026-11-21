<?php

namespace App\Http\Requests;

use App\Concerns\CountryValidationRules;
use Illuminate\Foundation\Http\FormRequest;

abstract class CountryRequest extends FormRequest
{
    use CountryValidationRules;

    public function authorize(): bool
    {
        return false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeCountryInput($this->only('english_name')));
    }
}
