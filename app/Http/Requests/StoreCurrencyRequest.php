<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Unique;

class StoreCurrencyRequest extends CurrencyRequest
{
    /** @return array<string, list<string|Unique>> */
    public function rules(): array
    {
        return $this->currencyRules(creating: true);
    }
}
