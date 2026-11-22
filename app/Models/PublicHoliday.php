<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CarbonImmutable $holiday_date */
class PublicHoliday extends Model
{
    public const STATUSES = ['active', 'inactive'];

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d';

    protected $fillable = ['country_code', 'administrative_area_id', 'holiday_date', 'name', 'status'];

    protected function casts(): array
    {
        return ['administrative_area_id' => 'integer', 'holiday_date' => 'immutable_date:Y-m-d'];
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'iso2');
    }

    /** @return BelongsTo<AdministrativeArea, $this> */
    public function administrativeArea(): BelongsTo
    {
        return $this->belongsTo(AdministrativeArea::class);
    }
}
