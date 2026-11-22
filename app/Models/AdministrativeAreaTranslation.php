<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** P01.06 read model; writes go through AdministrativeAreaTranslationService (composite key). */
class AdministrativeAreaTranslation extends Model
{
    protected $table = 'administrative_area_translations';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $guarded = ['*'];

    /** @return BelongsTo<AdministrativeArea, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(AdministrativeArea::class, 'area_id');
    }
}
