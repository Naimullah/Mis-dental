<?php

namespace App\Services\Permissions;

use App\Enums\PermissionSensitivity;
use App\Models\Permission;
use App\Support\SafeText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PermissionCatalog
{
    /** @return array{add: list<string>, update: list<string>, unlisted: list<string>} */
    public function sync(bool $apply = false): array
    {
        $validated = Validator::make(['catalog' => config('permissions.definitions')], [
            'catalog' => ['required', 'array', 'list'],
            'catalog.*' => ['required', 'array:code,description,sensitivity'],
            'catalog.*.code' => ['bail', 'required', 'string', 'max:120', 'distinct:strict', 'regex:/\A[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+\z/'],
            'catalog.*.description' => ['bail', 'required', 'string', 'max:255', SafeText::rule()],
            'catalog.*.sensitivity' => ['required', 'string', Rule::enum(PermissionSensitivity::class)],
        ])->validate();

        return DB::transaction(function () use ($validated, $apply): array {
            $existing = Permission::query()->orderBy('code')->get()->keyBy('code');
            $add = [];
            $update = [];
            $changes = [];

            foreach ($validated['catalog'] as $definition) {
                $code = $definition['code'];
                $current = $existing->pull($code);

                if ($current === null) {
                    $add[] = $code;
                } elseif ($current->description !== $definition['description'] || $current->sensitivity->value !== $definition['sensitivity']) {
                    $update[] = $code;
                } else {
                    continue;
                }

                $changes[] = $definition;
            }

            if ($apply && $changes !== []) {
                DB::table('permissions')->upsert($changes, ['code'], ['description', 'sensitivity']);
            }

            $unlisted = [];
            foreach ($existing as $permission) {
                $unlisted[] = $permission->code;
            }

            return ['add' => $add, 'update' => $update, 'unlisted' => $unlisted];
        });
    }
}
