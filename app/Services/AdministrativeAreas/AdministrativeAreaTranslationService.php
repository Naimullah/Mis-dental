<?php

namespace App\Services\AdministrativeAreas;

use App\Models\AdministrativeArea;
use App\Support\SafeText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * P01.06 display names: at most one per area and locale. A blank value removes the
 * translation, and the area then shows its canonical name.
 */
class AdministrativeAreaTranslationService
{
    /**
     * @param  array<array-key, mixed>  $translations  locale code => display name (blank or null removes)
     */
    public function sync(AdministrativeArea $area, array $translations): void
    {
        $normalized = [];
        foreach ($translations as $code => $name) {
            if (! is_string($code) || preg_match('/\A[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*\z/', $code) !== 1) {
                throw ValidationException::withMessages(['translations' => __('Unknown language code.')]);
            }
            $normalized[$code] = is_string($name) ? SafeText::normalize($name) : $name;
            if ($normalized[$code] === '') {
                $normalized[$code] = null;
            }
        }

        Validator::make(['translations' => $normalized], [
            'translations' => ['present', 'array'],
            'translations.*' => ['nullable', 'string', 'max:180', SafeText::rule()],
        ])->validate();

        $setting = array_keys(array_filter($normalized, fn (mixed $name): bool => $name !== null));
        $enabled = DB::table('locales')->whereIn('code', $setting)->where('is_active', true)->pluck('code')->all();
        $errors = [];
        foreach (array_diff($setting, $enabled) as $code) {
            $errors["translations.{$code}"] = __('Only enabled languages can have translations.');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($area, $normalized): void {
            foreach ($normalized as $code => $name) {
                $row = DB::table('administrative_area_translations')->where('area_id', $area->id)->where('locale_code', $code);

                if ($name === null) {
                    $row->delete();
                } elseif ($row->exists()) {
                    $row->update(['display_name' => $name]);
                } else {
                    DB::table('administrative_area_translations')->insert([
                        'area_id' => $area->id,
                        'locale_code' => $code,
                        'display_name' => $name,
                    ]);
                }
            }
        });
    }
}
