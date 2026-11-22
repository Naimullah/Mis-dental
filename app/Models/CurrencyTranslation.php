<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CurrencyTranslation extends Pivot
{
    protected $table = 'currency_translations';

    public $timestamps = false;
}
