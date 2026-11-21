<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

#[Fillable(['iso3', 'english_name', 'is_active'])]
class Country extends Model
{
    protected $primaryKey = 'iso2';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return Attribute<string, string> */
    protected function iso2(): Attribute
    {
        return Attribute::make(
            set: function (string $value): string {
                if ($this->exists && $value !== $this->getRawOriginal('iso2')) {
                    throw new LogicException('A country ISO2 code cannot be changed after creation.');
                }

                return $value;
            },
        );
    }

    /** Languages used in this country (P01.04). Not translations of the country name. */
    public function locales(): BelongsToMany
    {
        return $this->belongsToMany(Locale::class, 'country_locales', 'country_code', 'locale_code', 'iso2', 'code')
            ->withPivot('is_default');
    }
}
