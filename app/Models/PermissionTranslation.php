<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PermissionTranslation extends Pivot
{
    protected $table = 'permission_translations';

    public $timestamps = false;

    /** @return BelongsTo<Locale, $this> */
    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class, 'locale_code', 'code');
    }
}
