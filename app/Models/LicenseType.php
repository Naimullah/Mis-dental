<?php

namespace App\Models;

use App\Enums\LicenseAppliesTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

/**
 * P01.07 license type. country_code, code and applies_to identify what issued licenses
 * mean, so they are set once at creation and never change.
 */
#[Fillable(['name', 'is_active'])]
class LicenseType extends Model
{
    public $timestamps = false;

    /** @return BelongsToMany<Locale, $this, LicenseTypeTranslation> */
    public function translationLocales(): BelongsToMany
    {
        return $this->belongsToMany(Locale::class, 'license_type_translations', 'license_type_id', 'locale_code', 'id', 'code')
            ->using(LicenseTypeTranslation::class)->withPivot(['display_name', 'help_text']);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return Attribute<string, string> */
    protected function countryCode(): Attribute
    {
        return $this->setOnce('country_code');
    }

    /** @return Attribute<string, string> */
    protected function code(): Attribute
    {
        return $this->setOnce('code');
    }

    /** @return Attribute<string, string> */
    protected function appliesTo(): Attribute
    {
        return $this->setOnce('applies_to');
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'iso2');
    }

    /** @param Builder<LicenseType> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Types that dependent workflows (practitioner, facility or legal-entity licenses)
     * may offer for new records: active, same country, matching applies_to.
     *
     * @param  Builder<LicenseType>  $query
     */
    public function scopeSelectable(Builder $query, string $countryCode, LicenseAppliesTo $appliesTo): void
    {
        $query->where('country_code', $countryCode)
            ->where('applies_to', $appliesTo->value)
            ->where('is_active', true);
    }

    /** @return Attribute<string, string> */
    private function setOnce(string $attribute): Attribute
    {
        return Attribute::make(
            set: function (string $value) use ($attribute): string {
                if ($this->exists && $value !== $this->getRawOriginal($attribute)) {
                    throw new LogicException("A license type's {$attribute} cannot be changed after creation.");
                }

                return $value;
            },
        );
    }
}
