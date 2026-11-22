<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class LicenseTypeTranslation extends Pivot
{
    protected $table = 'license_type_translations';

    public $timestamps = false;
}
