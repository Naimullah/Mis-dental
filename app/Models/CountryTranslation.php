<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CountryTranslation extends Pivot
{
    protected $table = 'country_translations';

    public $timestamps = false;
}
