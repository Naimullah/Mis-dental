<?php

namespace App\Http\Requests;

use App\Models\Locale;

class UpdateLocaleRequest extends LocaleRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $locale = $this->route('locale');

        return $this->localeRules(
            creating: false,
            code: $locale instanceof Locale ? $locale->getRawOriginal('code') : null,
        );
    }
}
