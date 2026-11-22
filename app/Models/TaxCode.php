<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

/**
 * P01.10 tax code version (a historical snapshot).
 *
 * country_code, code, rate_percent and effective_from never change after creation.
 * effective_to may only go from empty to a date (closing the version). Rows are never deleted.
 * Dates are plain Y-m-d strings and both ends are inclusive.
 * rate_percent is always a decimal string (never a float).
 */
#[Fillable(['description'])]
class TaxCode extends Model
{
    public const LOCKED = ['country_code', 'code', 'rate_percent', 'effective_from'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['rate_percent' => 'decimal:5'];
    }

    protected static function booted(): void
    {
        static::updating(function (TaxCode $taxCode): void {
            foreach (self::LOCKED as $attribute) {
                if ($taxCode->isDirty($attribute)) {
                    throw new LogicException("A tax code version's {$attribute} cannot be changed after creation.");
                }
            }

            if ($taxCode->isDirty('effective_to') && $taxCode->getRawOriginal('effective_to') !== null) {
                throw new LogicException('A closed tax code version cannot be reopened or re-dated.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Tax code versions are historical records and cannot be deleted.');
        });
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'iso2');
    }

    /**
     * Translated names and explanations of this version (P02.05).
     *
     * @return BelongsToMany<Locale, $this, TaxCodeTranslation>
     */
    public function translationLocales(): BelongsToMany
    {
        return $this->belongsToMany(Locale::class, 'tax_code_translations', 'tax_code_id', 'locale_code', 'id', 'code')
            ->using(TaxCodeTranslation::class)->withPivot(['display_name', 'explanation']);
    }

    /**
     * Versions in effect on a date (Y-m-d), inclusive on both ends.
     * A query helper only; documents must copy the code and rate they used.
     *
     * @param  Builder<TaxCode>  $query
     */
    public function scopeEffectiveOn(Builder $query, string $date): void
    {
        $query->where('effective_from', '<=', $date)
            ->where(fn (Builder $period) => $period->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    /**
     * Versions of one code whose period overlaps [from, to] (to = null means open-ended).
     *
     * @param  Builder<TaxCode>  $query
     */
    public function scopeOverlapping(Builder $query, string $countryCode, string $code, string $from, ?string $to): void
    {
        $query->where('country_code', $countryCode)
            ->where('code', $code)
            ->where(fn (Builder $period) => $period->whereNull('effective_to')->orWhere('effective_to', '>=', $from));

        if ($to !== null) {
            $query->where('effective_from', '<=', $to);
        }
    }

    /** The version of a code in effect on a date, or null. */
    public static function versionOn(string $countryCode, string $code, string $date): ?self
    {
        return self::query()
            ->where('country_code', $countryCode)
            ->where('code', $code)
            ->effectiveOn($date)
            ->orderByDesc('effective_from')
            ->first();
    }

    /** current, future or ended, relative to $today (Y-m-d). */
    public function periodStatus(string $today): string
    {
        if ($this->effective_from > $today) {
            return 'future';
        }

        return $this->effective_to !== null && $this->effective_to < $today ? 'ended' : 'current';
    }
}
