<?php

namespace App\Policies;

use App\Models\User;

/**
 * Temporary: Locales management follows the master-data access switch (closed by
 * default, always closed in production) until platform roles and permissions exist.
 */
class LocalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-master-data');
    }
}
