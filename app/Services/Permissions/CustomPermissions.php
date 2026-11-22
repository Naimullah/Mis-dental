<?php

namespace App\Services\Permissions;

use App\Enums\PermissionSensitivity;
use App\Models\Permission;
use App\Models\User;
use App\Support\SafeText;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomPermissions
{
    public function canEdit(Permission $permission): bool
    {
        return str_starts_with($permission->code, 'custom.')
            && ! in_array($permission->code, array_column(config('permissions.definitions', []), 'code'), true);
    }

    /** @param array<string, mixed> $input */
    public function save(User $actor, Permission $permission, array $input): void
    {
        $creating = ! $permission->exists;
        if (! $creating) {
            abort_unless($this->canEdit($permission), 403);
        }

        if (is_string($input['code'] ?? null)) {
            $input['code'] = Str::lower(trim($input['code']));
        }
        if (is_string($input['description'] ?? null)) {
            $input['description'] = SafeText::normalize($input['description']);
        }

        $attributes = Validator::make($input, [
            'code' => $creating
                ? ['bail', 'required', 'string', 'max:120', 'regex:/\Acustom\.[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*\z/',
                    Rule::notIn(array_column(config('permissions.definitions', []), 'code')), Rule::unique('permissions')]
                : ['missing'],
            'description' => ['bail', 'required', 'string', 'max:255', SafeText::rule()],
            'sensitivity' => ['required', 'string', Rule::enum(PermissionSensitivity::class)],
        ])->validate();

        try {
            DB::transaction(function () use ($actor, $permission, $attributes, $creating): void {
                $record = $creating ? $permission : $this->lockEditable($permission);
                $record->forceFill($attributes)->saveOrFail();
                $this->audit($actor, $record, $creating ? 'created' : 'updated');
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => __('validation.unique', ['attribute' => __('Code')])]);
        }
    }

    public function remove(User $actor, Permission $permission): void
    {
        DB::transaction(function () use ($actor, $permission): void {
            $record = $this->lockEditable($permission);
            if ($record->translations()->exists()) {
                throw ValidationException::withMessages(['permission' => __('Remove the translations before deleting this permission.')]);
            }

            $record->deleteOrFail();
            $this->audit($actor, $record, 'deleted');
        });
    }

    private function lockEditable(Permission $permission): Permission
    {
        $record = Permission::query()->whereKey($permission->id)->lockForUpdate()->firstOrFail();
        abort_unless($this->canEdit($record), 403);

        return $record;
    }

    private function audit(User $actor, Permission $permission, string $action): void
    {
        DB::afterCommit(fn () => Log::notice('platform.permission.'.$action, [
            'actor_id' => $actor->id,
            'permission_id' => $permission->id,
            'code' => $permission->code,
        ]));
    }
}
