<?php

namespace App\Services\Roles;

use App\Concerns\RoleValidationRules;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Synchronizes the system roles in config/roles.php. Only names are ever updated; a code that
 * is used by a custom role, or whose scope differs from the stored system role, is refused.
 * System roles missing from the config are reported and kept.
 */
class RoleCatalog
{
    use RoleValidationRules;

    /** @return array{add: list<string>, update: list<string>, unlisted: list<string>} */
    public function sync(bool $apply = false): array
    {
        $definitions = config('roles.definitions');
        if (is_array($definitions)) {
            $definitions = array_map(fn (mixed $definition): mixed => is_array($definition) ? $this->normalizeRoleInput($definition) : $definition, $definitions);
        }

        $validated = Validator::make(['catalog' => $definitions], [
            'catalog' => ['required', 'array', 'list'],
            'catalog.*' => ['required', 'array:code,name,scope'],
            'catalog.*.code' => [...$this->roleCodeRules(), 'distinct:strict'],
            'catalog.*.name' => $this->roleNameRules(),
            'catalog.*.scope' => ['required', 'string', Rule::in(Role::SCOPES)],
        ])->validate();

        return DB::transaction(function () use ($validated, $apply): array {
            $existing = Role::query()->whereNull('clinic_id')->orderBy('code')->lockForUpdate()->get()->keyBy('code');
            $add = [];
            $update = [];
            $errors = [];

            foreach ($validated['catalog'] as $index => $definition) {
                $current = $existing->pull($definition['code']);

                if ($current === null) {
                    $add[] = $definition;
                } elseif (! $current->is_system) {
                    $errors["catalog.{$index}.code"] = __('The code :code is already used by a custom role.', ['code' => $definition['code']]);
                } elseif ($current->scope !== $definition['scope']) {
                    $errors["catalog.{$index}.scope"] = __('The scope of system role :code cannot change.', ['code' => $definition['code']]);
                } elseif ($current->name !== $definition['name']) {
                    $update[] = $definition;
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($apply) {
                foreach ($add as $definition) {
                    DB::table('roles')->insert([...$definition, 'clinic_id' => null, 'is_system' => true]);
                }
                foreach ($update as $definition) {
                    DB::table('roles')->whereNull('clinic_id')->where('code', $definition['code'])->where('is_system', true)
                        ->update(['name' => $definition['name']]);
                }
            }

            $unlisted = $existing->filter(fn (Role $role): bool => $role->is_system)->keys()->values()->all();

            return [
                'add' => array_column($add, 'code'),
                'update' => array_column($update, 'code'),
                'unlisted' => $unlisted,
            ];
        });
    }
}
