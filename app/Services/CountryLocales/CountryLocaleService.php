<?php

namespace App\Services\CountryLocales;

use App\Models\Country;
use App\Models\Locale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * P01.04 languages used in a country. At most one default per country; links can be
 * added, removed and (un)marked default. Countries and locales themselves are never changed.
 */
class CountryLocaleService
{
    /**
     * Data for the Countries page "Languages" dialog, or null when the country does not exist.
     *
     * @return array{country: array{iso2: string, english_name: string, is_active: bool}, links: list<array<string, mixed>>, available: list<array<string, string>>}|null
     */
    public function panel(mixed $iso2): ?array
    {
        if (! is_string($iso2) || preg_match('/\A[A-Z]{2}\z/', $iso2) !== 1) {
            return null;
        }

        $country = Country::query()->find($iso2);
        if ($country === null) {
            return null;
        }

        $links = DB::table('country_locales')
            ->join('locales', 'locales.code', '=', 'country_locales.locale_code')
            ->where('country_locales.country_code', $iso2)
            ->orderByDesc('country_locales.is_default')
            ->orderBy('locales.code')
            ->get(['locales.code', 'locales.english_name', 'locales.text_direction', 'locales.is_active', 'country_locales.is_default'])
            ->map(fn (object $row): array => [
                'code' => $row->code,
                'english_name' => $row->english_name,
                'text_direction' => $row->text_direction,
                'is_default' => (bool) $row->is_default,
                'is_active' => (bool) $row->is_active,
            ])
            ->all();

        // Only enabled languages can be newly linked.
        $available = Locale::query()
            ->where('is_active', true)
            ->whereNotIn('code', array_column($links, 'code'))
            ->orderBy('code')
            ->get(['code', 'english_name'])
            ->map(fn (Locale $locale): array => ['code' => $locale->code, 'english_name' => $locale->english_name])
            ->all();

        return [
            'country' => ['iso2' => $country->iso2, 'english_name' => $country->english_name, 'is_active' => $country->is_active],
            'links' => array_values($links),
            'available' => array_values($available),
        ];
    }

    public function add(Country $country, Locale $locale, bool $isDefault): void
    {
        try {
            DB::transaction(function () use ($country, $locale, $isDefault): void {
                $this->lockCountry($country);

                if (! $locale->is_active) {
                    throw ValidationException::withMessages([
                        'locale_code' => __('Only enabled languages can be linked to a country.'),
                    ]);
                }

                if ($this->link($country, $locale)->exists()) {
                    throw ValidationException::withMessages([
                        'locale_code' => __('This language is already linked to the country.'),
                    ]);
                }

                if ($isDefault) {
                    $this->clearDefaults($country);
                }

                DB::table('country_locales')->insert([
                    'country_code' => $country->iso2,
                    'locale_code' => $locale->code,
                    'is_default' => $isDefault,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'locale_code' => __('This language is already linked to the country.'),
            ]);
        }
    }

    public function setDefault(Country $country, Locale $locale, bool $isDefault): void
    {
        DB::transaction(function () use ($country, $locale, $isDefault): void {
            $this->lockCountry($country);
            $this->assertLinked($country, $locale);

            if ($isDefault && ! $locale->is_active) {
                throw ValidationException::withMessages([
                    'is_default' => __('A disabled language cannot be the default for a country.'),
                ]);
            }

            if ($isDefault) {
                $this->clearDefaults($country);
            }

            $this->link($country, $locale)->update(['is_default' => $isDefault]);
        });
    }

    public function remove(Country $country, Locale $locale): void
    {
        DB::transaction(function () use ($country, $locale): void {
            $this->lockCountry($country);
            $this->assertLinked($country, $locale);
            $this->link($country, $locale)->delete();
        });
    }

    private function link(Country $country, Locale $locale): \Illuminate\Database\Query\Builder
    {
        return DB::table('country_locales')
            ->where('country_code', $country->iso2)
            ->where('locale_code', $locale->code);
    }

    private function clearDefaults(Country $country): void
    {
        DB::table('country_locales')
            ->where('country_code', $country->iso2)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /** Serialises default changes for one country. */
    private function lockCountry(Country $country): void
    {
        Country::query()->whereKey($country->iso2)->lockForUpdate()->firstOrFail();
    }

    private function assertLinked(Country $country, Locale $locale): void
    {
        if (! $this->link($country, $locale)->exists()) {
            throw new NotFoundHttpException;
        }
    }
}
