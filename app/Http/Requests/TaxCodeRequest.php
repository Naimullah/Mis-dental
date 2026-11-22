<?php

namespace App\Http\Requests;

use App\Concerns\TaxCodeValidationRules;
use App\Models\TaxCode;
use Illuminate\Foundation\Http\FormRequest;

/** Create a tax code (first version) or edit a version's description. */
class TaxCodeRequest extends FormRequest
{
    use TaxCodeValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeTaxCodeInput(
            $this->only(['description', 'code', 'rate_percent', 'effective_from', 'effective_to']),
        ));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->route('taxCode') instanceof TaxCode
            ? $this->taxCodeUpdateRules()
            : $this->taxCodeCreateRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->taxCodeMessages();
    }
}
