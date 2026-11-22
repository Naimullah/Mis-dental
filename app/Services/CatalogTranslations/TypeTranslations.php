<?php

namespace App\Services\CatalogTranslations;

use App\Models\IdentityDocumentType;
use App\Models\LicenseType;
use App\Models\Locale;
use App\Models\TaxCode;
use App\Support\SafeText;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Translated name plus optional help text for license types, identity document types and
 * tax code versions. The API field is always help_text; for tax codes it is stored in the
 * explanation column.
 *
 * @phpstan-type Translation array{display_name: string, help_text: string|null}
 */
class TypeTranslations
{
    /**
     * @param  list<int>  $ids
     * @return array{locales: list<array{code: string, english_name: string, native_name: string|null, text_direction: string}>, translations: array<int, array<string, Translation>>, localized: array<int, Translation>}
     */
    public function forPage(LicenseType|IdentityDocumentType|TaxCode $catalog, array $ids, string $locale): array
    {
        [$table, $key, , $helpColumn] = $this->storage($catalog);
        $locales = [];
        foreach (Locale::query()->where('is_active', true)->orderBy('code')->get() as $language) {
            $locales[] = [
                'code' => $language->code,
                'english_name' => $language->english_name,
                'native_name' => $language->native_name,
                'text_direction' => $language->text_direction,
            ];
        }

        $translations = [];
        $rows = DB::table($table)
            ->whereIn($key, $ids)
            ->whereIn('locale_code', array_column($locales, 'code'))
            ->get([$key.' as entity_id', 'locale_code', 'display_name', $helpColumn.' as help_text']);
        foreach ($rows as $row) {
            $translations[$row->entity_id][$row->locale_code] = [
                'display_name' => $row->display_name,
                'help_text' => $row->help_text,
            ];
        }

        $localized = [];
        $base = Str::before($locale, '-');
        foreach ($translations as $id => $options) {
            $translation = $options[$locale] ?? $options[$base] ?? null;
            if ($translation !== null) {
                $localized[$id] = $translation;
            }
        }

        return compact('locales', 'translations', 'localized');
    }

    public function update(LicenseType|IdentityDocumentType|TaxCode $type, mixed $input): void
    {
        if (! is_array($input)) {
            throw ValidationException::withMessages(['translations' => __('validation.array', ['attribute' => __('Translations')])]);
        }

        foreach ($input as $code => &$entry) {
            if (! is_string($code) || strlen($code) > 20 || preg_match('/\A[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*\z/', $code) !== 1) {
                throw ValidationException::withMessages(['translations' => __('Unknown language code.')]);
            }

            if (is_array($entry)) {
                foreach (['display_name', 'help_text'] as $field) {
                    if (is_string($entry[$field] ?? null)) {
                        $text = $entry[$field];
                        if ($field === 'help_text') {
                            $text = str_replace(["\r\n", "\r"], "\n", $text);
                        }
                        $entry[$field] = SafeText::normalize($text);
                    }
                }
            }
        }
        unset($entry);

        [$table, $key, $limit, $helpColumn, $helpLimit] = $this->storage($type);
        Validator::make(['translations' => $input], [
            'translations.*' => ['required', 'array:display_name,help_text'],
            'translations.*.display_name' => ['present', 'nullable', 'string', 'max:'.$limit, SafeText::rule()],
            'translations.*.help_text' => ['nullable', 'string', ...($helpLimit === null ? [] : ['max:'.$helpLimit]), function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ($problem = SafeText::problem(str_replace("\n", '', $value))) !== null) {
                    $fail($problem);
                }
            }],
        ])->validate();

        $errors = [];
        foreach ($input as $code => $entry) {
            if (($entry['display_name'] ?? '') === '' && ($entry['help_text'] ?? '') !== '') {
                $errors["translations.{$code}.display_name"] = __('validation.required', ['attribute' => __('Display name')]);
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($type, $input, $table, $key, $helpColumn): void {
            $type->newQuery()->whereKey($type->id)->lockForUpdate()->firstOrFail();
            $enabled = Locale::query()->whereIn('code', array_keys($input))
                ->where('is_active', true)->orderBy('code')->lockForUpdate()->pluck('code')->all();

            $errors = [];
            foreach (array_diff(array_keys($input), $enabled) as $code) {
                $errors["translations.{$code}"] = __('Only enabled languages can have translations.');
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($input as $code => $entry) {
                $identity = [$key => $type->id, 'locale_code' => $code];
                $name = $entry['display_name'] ?? '';
                $help = $entry['help_text'] ?? '';

                if ($name === '') {
                    DB::table($table)->where($identity)->delete();
                } else {
                    DB::table($table)->upsert([
                        $identity + ['display_name' => $name, $helpColumn => $help === '' ? null : $help],
                    ], [$key, 'locale_code'], ['display_name', $helpColumn]);
                }
            }
        });
    }

    /**
     * Table, owner key column, name length, help column and help length (null = unlimited).
     *
     * @return array{non-empty-string, non-empty-string, int, non-empty-string, int|null}
     */
    private function storage(LicenseType|IdentityDocumentType|TaxCode $type): array
    {
        return match (true) {
            $type instanceof LicenseType => ['license_type_translations', 'license_type_id', 180, 'help_text', null],
            $type instanceof IdentityDocumentType => ['identity_document_type_translations', 'document_type_id', 160, 'help_text', null],
            default => ['tax_code_translations', 'tax_code_id', 140, 'explanation', 4000],
        };
    }
}
