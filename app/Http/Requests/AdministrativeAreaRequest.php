<?php

namespace App\Http\Requests;

use App\Concerns\AdministrativeAreaValidationRules;
use App\Models\AdministrativeArea;
use Illuminate\Foundation\Http\FormRequest;

class AdministrativeAreaRequest extends FormRequest
{
    use AdministrativeAreaValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeAreaInput($this->only(['canonical_name', 'level_code', 'area_code', 'parent_id'])));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $area = $this->route('area');

        return $area instanceof AdministrativeArea
            ? $this->areaRules(false, $area->country_code, $area->id)
            : $this->areaRules(true, $this->input('country_code'));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->areaMessages();
    }
}
