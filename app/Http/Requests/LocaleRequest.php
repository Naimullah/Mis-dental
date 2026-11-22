<?php

namespace App\Http\Requests;

use App\Concerns\LocaleValidationRules;
use Illuminate\Foundation\Http\FormRequest;

abstract class LocaleRequest extends FormRequest
{
    use LocaleValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeLocaleInput($this->only(['english_name', 'native_name', 'default_calendar_code', 'default_numbering_system_code'])));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->localeMessages();
    }
}
