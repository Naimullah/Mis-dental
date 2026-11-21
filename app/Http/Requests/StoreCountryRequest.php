<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;

class StoreCountryRequest extends CountryRequest
{
    /** @return array<string, list<string|RequiredIf|Unique>> */
    public function rules(): array
    {
        return $this->countryRules($this->all(), creating: true);
    }
}
