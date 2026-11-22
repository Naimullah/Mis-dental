<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/** P01.05 administrative area. country_code is set once and never changes. */
#[Fillable(['parent_id', 'level_code', 'area_code', 'canonical_name', 'is_active'])]
class AdministrativeArea extends Model
{
    public const MAX_DEPTH = 6;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['parent_id' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return Attribute<string, string> */
    protected function countryCode(): Attribute
    {
        return Attribute::make(
            set: function (string $value): string {
                if ($this->exists && $value !== $this->getRawOriginal('country_code')) {
                    throw new LogicException('An administrative area cannot move to another country.');
                }

                return $value;
            },
        );
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'iso2');
    }

    /** @return BelongsTo<AdministrativeArea, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<AdministrativeArea, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<AdministrativeAreaTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(AdministrativeAreaTranslation::class, 'area_id');
    }

    /** @param Builder<AdministrativeArea> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * P01.06 fallback: exact locale (fa-AF) → base language (fa) → canonical name.
     * Uses loaded translations when available.
     */
    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $names = $this->relationLoaded('translations')
            ? $this->translations->pluck('display_name', 'locale_code')
            : $this->translations()->pluck('display_name', 'locale_code');

        foreach (self::nameChain($locale) as $code) {
            if (isset($names[$code]) && $names[$code] !== '') {
                return $names[$code];
            }
        }

        return $this->canonical_name;
    }

    /** @return list<string> exact locale, then its base language (never another language). */
    public static function nameChain(string $locale): array
    {
        return array_values(array_unique([$locale, Str::before($locale, '-')]));
    }
}
