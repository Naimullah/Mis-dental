<?php
namespace App\Services\RolePermissions;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

final class RolePermissionOwnerAuthorizer
{
    /**
     * Authorize the current authenticated platform owner.
     *
     * Approval remains disabled until fresh MFA
     * verification is implemented.
     */
    public function authorize(): User
    {
        $actor = Auth::user();

        $ownerId = config(
            'role-permissions.owner_user_id'
        );

        if (
            ! $actor instanceof User
            || ! is_int($ownerId)
            || $ownerId <= 0
            || ! $actor->exists
            || (int) $actor->getKey() !== $ownerId
            || ! $actor->is_active
            || $actor->scope !== 'platform'
            || ! $actor->hasVerifiedEmail()
            || empty($actor->two_factor_secret)
            || $actor->two_factor_confirmed_at === null
        ) {
            throw new AuthorizationException(
                'Role-permission owner authorization denied.'
            );
        }

        // Fail closed until the application verifies
        // fresh MFA for this exact approval operation.
        throw new AuthorizationException(
            'Fresh MFA verification is not implemented. '
            . 'Role-permission approval remains disabled.'
        );
    }
}
