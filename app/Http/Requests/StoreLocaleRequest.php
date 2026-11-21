<?php

namespace App\Http\Requests;

class StoreLocaleRequest extends LocaleRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->localeRules(creating: true, code: $this->input('code'));
    }
}
