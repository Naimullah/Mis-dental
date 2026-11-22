<?php

namespace App\Services\Users;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Support\SafeText;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformAccounts
{
    use PasswordValidationRules;

    /** @param array<string, mixed> $input */
    public function save(User $actor, User $account, array $input): void
    {
        abort_if($account->exists && $account->scope !== 'platform', 404);
        foreach (['username', 'email', 'name'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $value = SafeText::normalize($input[$field]);
                $input[$field] = $field === 'name' ? $value : Str::lower($value);
            }
        }

        $username = Rule::unique('users', 'username');
        $email = Rule::unique('users', 'email');
        if ($account->exists) {
            $username->ignore($account->id);
            $email->ignore($account->id);
        }

        $attributes = Validator::make($input, [
            'username' => ['bail', 'required', 'string', 'max:64', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/', $username],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', $email],
            'name' => ['bail', 'required', 'string', 'max:255', SafeText::rule()],
            'scope' => ['required', Rule::in(['platform'])],
            'locale_code' => ['nullable', 'string', 'max:20', Rule::exists('locales', 'code')
                ->where(fn (Builder $query) => $query->where('is_active', true))],
            'password' => $account->exists ? ['prohibited'] : $this->passwordRules(),
        ])->validate();
        unset($attributes['scope']);
        if ($account->exists) {
            unset($attributes['password']);
        }

        $created = ! $account->exists;
        try {
            DB::transaction(function () use ($account, $attributes, $actor, $created): void {
                $record = $created ? $account : $this->locked($account);
                $record->forceFill($attributes);
                if ($created) {
                    // An address entered by a platform maintainer counts as verified (P03.03).
                    $record->email_verified_at = now();
                } elseif ($record->isDirty('email')) {
                    // A changed address must be verified again.
                    $record->email_verified_at = null;
                }
                $record->saveOrFail();
                $this->audit($actor, $record, $created ? 'created' : 'updated');
            });
        } catch (UniqueConstraintViolationException $exception) {
            $field = str_contains($exception->getPrevious()?->getMessage() ?? '', 'username') ? 'username' : 'email';
            throw ValidationException::withMessages([$field => __('validation.unique', ['attribute' => $field])]);
        }
    }

    public function setActive(User $actor, User $account, bool $active): void
    {
        if (! $active && $actor->is($account)) {
            throw ValidationException::withMessages(['is_active' => __('You cannot deactivate your own account.')]);
        }

        DB::transaction(function () use ($actor, $account, $active): void {
            $record = $this->locked($account);
            if ($record->is_active === $active) {
                return;
            }

            $record->forceFill(['is_active' => $active, 'remember_token' => Str::random(60)])->saveOrFail();
            if (! $active && config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $record->id)->delete();
            }
            $this->audit($actor, $record, $active ? 'activated' : 'deactivated');
        });
    }

    private function locked(User $account): User
    {
        return User::query()->where('scope', 'platform')->whereKey($account->id)->lockForUpdate()->firstOrFail();
    }

    private function audit(User $actor, User $account, string $action): void
    {
        DB::afterCommit(fn () => Log::notice('platform.user.'.$action, [
            'actor_id' => $actor->id,
            'target_id' => $account->id,
        ]));
    }
}
