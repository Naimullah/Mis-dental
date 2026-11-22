<?php

namespace App\Http\Requests;

use App\Concerns\IdentityDocumentTypeValidationRules;
use App\Models\IdentityDocumentType;
use Illuminate\Foundation\Http\FormRequest;

class IdentityDocumentTypeRequest extends FormRequest
{
    use IdentityDocumentTypeValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeDocumentTypeInput($this->only(['name', 'code', 'optional_format_regex'])));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = ! $this->route('documentType') instanceof IdentityDocumentType;

        return $this->documentTypeRules($creating, $this->input('country_code'));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->documentTypeMessages();
    }
}
