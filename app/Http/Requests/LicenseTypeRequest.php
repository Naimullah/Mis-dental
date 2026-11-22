<?php

namespace App\Http\Requests;

use App\Concerns\LicenseTypeValidationRules;
use App\Models\LicenseType;
use Illuminate\Foundation\Http\FormRequest;

class LicenseTypeRequest extends FormRequest
{
    use LicenseTypeValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeLicenseTypeInput($this->only(['name', 'code'])));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = ! $this->route('licenseType') instanceof LicenseType;

        return $this->licenseTypeRules($creating, $this->input('country_code'));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->licenseTypeMessages();
    }
}
