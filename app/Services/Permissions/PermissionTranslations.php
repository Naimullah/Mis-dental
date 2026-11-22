<?php

namespace App\Services\Permissions;

use App\Models\Locale;
use App\Models\Permission;
use App\Support\SafeText;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PermissionTranslations
{
    /** @param array<string, mixed> $input */
    public function save(Permission $permission, Locale $locale, array $input): void
    {
        if (is_string($input['display_name'] ?? null)) {
            $input['display_name'] = SafeText::normalize($input['display_name']);
        }
        if (is_string($input['description'] ?? null)) {
            $input['description'] = SafeText::normalize(str_replace(["\r\n", "\r"], "\n", $input['description']));
        }

        $attributes = Validator::make($input, [
            'display_name' => ['bail', 'required', 'string', 'max:160', SafeText::rule()],
            'description' => ['nullable', 'string', 'max:4000', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ($problem = SafeText::problem(str_replace("\n", '', $value))) !== null) {
                    $fail($problem);
                }
            }],
        ])->validate();

        DB::transaction(function () use ($permission, $locale, $attributes): void {
            Permission::query()->whereKey($permission->id)->lockForUpdate()->firstOrFail();
            $language = Locale::query()->whereKey($locale->code)->lockForUpdate()->firstOrFail();
            if (! $language->is_active) {
                throw ValidationException::withMessages(['locale_code' => __('Only enabled languages can have translations.')]);
            }

            $description = $attributes['description'] ?? '';
            DB::table('permission_translations')->upsert([[
                'permission_id' => $permission->id,
                'locale_code' => $language->code,
                'display_name' => $attributes['display_name'],
                'description' => $description === '' ? null : $description,
            ]], ['permission_id', 'locale_code'], ['display_name', 'description']);
        });
    }

    public function remove(Permission $permission, Locale $locale): void
    {
        DB::transaction(function () use ($permission, $locale): void {
            Permission::query()->whereKey($permission->id)->lockForUpdate()->firstOrFail();
            DB::table('permission_translations')->where('permission_id', $permission->id)
                ->where('locale_code', $locale->code)->delete();
        });
    }
}
