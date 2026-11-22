<?php
namespace App\Services\RolePermissions;

use App\Enums\PermissionSensitivity;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RolePermissionValidator
{
    /**
     * Validate a proposed permission assignment.
     *
     * @return array{0: Role, 1: Permission}
     */
    public function forAssignment(
        int $roleId,
        int $permissionId
    ): array {
        [$role, $permission] = $this->validatePair(
            $roleId,
            $permissionId
        );

        if ($this->assignmentExists($roleId, $permissionId)) {
            throw ValidationException::withMessages([
                'permission_id' =>
                    'This permission is already assigned to the role.',
            ]);
        }

        return [$role, $permission];
    }

    /**
     * Validate a proposed permission revocation.
     *
     * @return array{0: Role, 1: Permission}
     */
    public function forRevocation(
        int $roleId,
        int $permissionId
    ): array {
        [$role, $permission] = $this->validatePair(
            $roleId,
            $permissionId
        );

        if (! $this->assignmentExists($roleId, $permissionId)) {
            throw ValidationException::withMessages([
                'permission_id' =>
                    'This permission is not assigned to the role.',
            ]);
        }

        return [$role, $permission];
    }

    /**
     * Validate the role, permission, scope, and protection rules.
     *
     * @return array{0: Role, 1: Permission}
     */
    private function validatePair(
        int $roleId,
        int $permissionId
    ): array {
        $role = Role::query()->find($roleId);
        $permission = Permission::query()->find($permissionId);

        $errors = [];

        if ($role === null) {
            $errors['role_id'] = 'The selected role does not exist.';
        }

        if ($permission === null) {
            $errors['permission_id'] =
                'The selected permission does not exist.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // Permission codes must match the role's scope.
        // Unknown permission namespaces are denied.
        if (
            ! in_array($role->scope, Role::SCOPES, true)
            || ! str_starts_with(
                $permission->code,
                $role->scope . '.'
            )
        ) {
            throw ValidationException::withMessages([
                'permission_id' =>
                    'The permission scope does not match the role scope.',
            ]);
        }

        // System templates cannot be changed through ordinary
        // assignment or revocation workflows.
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role_id' =>
                    'Protected system role templates cannot be modified.',
            ]);
        }

        // Temporary fail-closed restriction.
        // Remove only after clinic-scoped authorization is implemented.
        if ($role->scope === 'clinic') {
            throw ValidationException::withMessages([
                'role_id' =>
                    'Clinic permission changes are locked until '
                    . 'clinic-scoped authorization is available.',
            ]);
        }

        // No caller-supplied approval flag may bypass this restriction.
        // An authenticated, persisted approval workflow is required.
        if ($permission->sensitivity !== PermissionSensitivity::Low) {
            throw ValidationException::withMessages([
                'permission_id' =>
                    'Sensitive permission changes require an '
                    . 'approved authorization workflow.',
            ]);
        }

        return [$role, $permission];
    }

    private function assignmentExists(
        int $roleId,
        int $permissionId
    ): bool {
        return DB::table('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();
    }
}
