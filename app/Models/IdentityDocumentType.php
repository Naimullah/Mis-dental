<?php

namespace App\Models;

use App\Support\SafeDocumentPattern;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * P01.08 identity document type (national ID, passport, …).
 *
 * IMPORTANT for the patient module: no identity document is ever mandatory to create a
 * patient. There is no "required" flag here on purpose; patient registration must accept
 * zero documents, and accepts() treats an empty number as "no document".
 */
#[Fillable(['name', 'optional_format_regex', 'is_active'])]
class IdentityDocumentType extends Model
{
    public $timestamps = false;

    /** @return BelongsToMany<Locale, $this, IdentityDocumentTypeTranslation> */
    public function translationLocales(): BelongsToMany
    {
        return $this->belongsToMany(Locale::class, 'identity_document_type_translations', 'document_type_id', 'locale_code', 'id', 'code')
            ->using(IdentityDocumentTypeTranslation::class)->withPivot(['display_name', 'help_text']);
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

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'iso2');
    }

    /** @param Builder<IdentityDocumentType> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Types a patient form may offer for new documents: active and in the patient's country.
     *
     * @param  Builder<IdentityDocumentType>  $query
     */
    public function scopeSelectable(Builder $query, string $countryCode): void
    {
        $query->where('country_code', $countryCode)->where('is_active', true);
    }

    /**
     * Whether a document number is acceptable for this type. An empty number means
     * "no document" and is always acceptable; a pattern only applies when a number is given.
     */
    public function accepts(?string $number): bool
    {
        $number = $number === null ? '' : Str::trim($number);

        if ($number === '' || $this->optional_format_regex === null) {
            return true;
        }

        return SafeDocumentPattern::isSafe($this->optional_format_regex)
            && SafeDocumentPattern::matches($this->optional_format_regex, $number);
    }

    /** @return Attribute<string, string> */
    private function setOnce(string $attribute): Attribute
    {
        return Attribute::make(
            set: function (string $value) use ($attribute): string {
                if ($this->exists && $value !== $this->getRawOriginal($attribute)) {
                    throw new LogicException("An identity document type's {$attribute} cannot be changed after creation.");
                }

                return $value;
            },
        );
    }
}
