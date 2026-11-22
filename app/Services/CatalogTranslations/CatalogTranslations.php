<?php

namespace App\Services\CatalogTranslations;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Locale;
use App\Support\SafeText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogTranslations
{
    /**
     * @param  array<string, string>  $canonicalNames
     * @return array{locales: list<array{code: string, english_name: string, native_name: string|null, text_direction: string}>, names: array<string, array<string, string>>, displayNames: array<string, string>}
     */
    public function forPage(Country|Currency $catalog, array $canonicalNames): array
    {
        $relation = $catalog->translationLocales();
        $key = $catalog::TRANSLATION_ENTITY_KEY;
        $names = [];

        if ($canonicalNames !== []) {
            $rows = DB::table($relation->getTable().' as translations')
                ->join('locales', 'locales.code', '=', 'translations.locale_code')
                ->where('locales.is_active', true)
                ->whereIn('translations.'.$key, array_keys($canonicalNames))
                ->get(['translations.'.$key.' as entity_code', 'translations.locale_code', 'translations.display_name']);

            foreach ($rows as $row) {
                $names[$row->entity_code][$row->locale_code] = $row->display_name;
            }
        }

        $locales = [];
        foreach (Locale::query()->where('is_active', true)->orderBy('code')->get() as $locale) {
            $locales[] = [
                'code' => $locale->code,
                'english_name' => $locale->english_name,
                'native_name' => $locale->native_name,
                'text_direction' => $locale->text_direction,
            ];
        }

        $displayNames = [];
        foreach ($canonicalNames as $code => $canonical) {
            $displayNames[$code] = $this->displayName($canonical, $names[$code] ?? []);
        }

        return ['locales' => $locales, 'names' => $names, 'displayNames' => $displayNames];
    }

    /** @param array<string, string> $names */
    public function displayName(string $canonical, array $names, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $names[$locale] ?? $names[Str::before($locale, '-')] ?? $canonical;
    }

    public function update(Country|Currency $record, mixed $input): void
    {
        if (! is_array($input)) {
            throw ValidationException::withMessages(['translations' => __('validation.array', ['attribute' => __('Translations')])]);
        }

        $limit = $record instanceof Country ? 160 : 120;
        $names = [];
        foreach ($input as $code => $name) {
            if (! is_string($code) || strlen($code) > 20 || preg_match('/\A[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*\z/', $code) !== 1) {
                throw ValidationException::withMessages(['translations' => __('Unknown language code.')]);
            }

            $name = is_string($name) ? SafeText::normalize($name) : $name;
            $names[$code] = $name === '' ? null : $name;
        }

        Validator::make(['translations' => $names], [
            'translations.*' => ['bail', 'nullable', 'string', 'max:'.$limit, SafeText::rule()],
        ])->validate();

        DB::transaction(function () use ($record, $names): void {
            $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $enabled = Locale::query()->whereIn('code', array_keys($names))
                ->where('is_active', true)->orderBy('code')->lockForUpdate()->pluck('code')->all();

            $errors = [];
            foreach (array_diff(array_keys($names), $enabled) as $code) {
                $errors["translations.{$code}"] = __('Only enabled languages can have translations.');
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $relation = $record->translationLocales();
            $key = $record::TRANSLATION_ENTITY_KEY;
            $table = DB::table($relation->getTable());
            foreach ($names as $code => $name) {
                $identity = [$key => $record->getKey(), 'locale_code' => $code];

                if ($name === null) {
                    (clone $table)->where($identity)->delete();
                } else {
                    $table->upsert([$identity + ['display_name' => $name]], [$key, 'locale_code'], ['display_name']);
                }
            }
        });
    }
}
