<?php

namespace App\Services\Roles;

use App\Concerns\RoleValidationRules;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * P03.04 custom roles: create and rename. System roles are read-only here, and only active
 * platform users may act. Clinic custom roles wait for the clinics module; a clinic-scope user
 * can never create or change a platform role.
 */
class RoleService
{
    use RoleValidationRules;

    /** @param array<string, mixed> $input */
    public function create(User $actor, array $input): Role
    {
        $this->assertPlatformActor($actor);

        return $this->write(function () use ($actor, $input): Role {
            $input = $this->normalizeRoleInput($input);
            $attributes = Validator::make($input, [
                'scope' => ['required', 'string', Rule::in(Role::SCOPES)],
                'code' => [...$this->roleCodeRules(), Rule::unique('roles', 'code')->whereNull('clinic_id')],
                'name' => $this->roleNameRules(),
                'clinic_id' => ['prohibited'],
                'is_system' => ['prohibited'],
            ], $this->roleMessages())->validate();

            if ($attributes['scope'] !== 'platform') {
                throw ValidationException::withMessages([
                    'scope' => __('Clinic roles can be added once clinics exist.'),
                ]);
            }

            $role = new Role;
            $role->code = $attributes['code'];
            $role->name = $attributes['name'];
            $role->scope = 'platform';
            $role->clinic_id = null;
            $role->is_system = false;
            $this->save($role);
            $this->audit($actor, $role, 'created');

            return $role;
        });
    }

    /** @param array<string, mixed> $input */
    public function rename(User $actor, int $id, array $input): Role
    {
        $this->assertPlatformActor($actor);

        return $this->write(function () use ($actor, $id, $input): Role {
            $role = Role::query()->lockForUpdate()->findOrFail($id);

            if ($role->is_system) {
                throw ValidationException::withMessages(['name' => __('System roles cannot be changed.')]);
            }

            // Clinic custom roles will be managed by their own clinic's administrators.
            abort_unless($role->scope === 'platform', 404);

            $attributes = Validator::make($this->normalizeRoleInput($input), [
                'name' => $this->roleNameRules(),
                'code' => ['missing'],
                'scope' => ['missing'],
                'clinic_id' => ['missing'],
                'is_system' => ['missing'],
            ])->validate();

            if ($role->name === $attributes['name']) {
                return $role;
            }

            $role->name = $attributes['name'];
            $this->save($role);
            $this->audit($actor, $role, 'renamed');

            return $role;
        });
    }

    private function assertPlatformActor(User $actor): void
    {
        abort_unless($actor->is_active && $actor->scope === 'platform', 403);
    }

    /** @param Closure(): Role $operation */
    private function write(Closure $operation): Role
    {
        try {
            return DB::transaction($operation);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => __('validation.unique', ['attribute' => 'code'])]);
        }
    }

    private function save(Role $role): void
    {
        if (! $role->save()) {
            throw new RuntimeException('The role write was cancelled.');
        }
    }

    private function audit(User $actor, Role $role, string $action): void
    {
        DB::afterCommit(fn () => Log::notice('platform.role.'.$action, [
            'actor_id' => $actor->id,
            'role_id' => $role->id,
            'code' => $role->code,
        ]));
    }
}
