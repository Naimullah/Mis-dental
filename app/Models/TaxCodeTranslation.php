<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TaxCodeTranslation extends Pivot
{
    protected $table = 'tax_code_translations';

    public $timestamps = false;
}
