<?php

namespace App\Http\Requests;

use App\Models\Country;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;

class UpdateCountryRequest extends CountryRequest
{
    /** @return array<string, list<string|RequiredIf|Unique>> */
    public function rules(): array
    {
        $country = $this->route('country');
        return $this->countryRules($this->all(), creating: false, country: $country instanceof Country ? $country : null);
    }
}
