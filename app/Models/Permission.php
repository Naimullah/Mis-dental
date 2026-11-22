<?php

namespace App\Models;

use App\Enums\PermissionSensitivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @property PermissionSensitivity $sensitivity */
class Permission extends Model
{
    public const VIEW_CATALOG = 'platform.permissions.view';

    public const MANAGE_TRANSLATIONS = 'platform.permissions.translations.manage';

    public const MANAGE_DEFINITIONS = 'platform.permissions.definitions.manage';

    public const MANAGE_MASTER_DATA = 'platform.master_data.manage';

    public const MANAGE_USERS = 'platform.users.manage';

    public const MANAGE_ROLES = 'platform.roles.manage';

    public $timestamps = false;

    /** @return HasMany<PermissionTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(PermissionTranslation::class);
    }

/**
 * Roles associated with this permission.
 *
 * @return BelongsToMany<Role, $this>
 */
public function roles(): BelongsToMany
{
    return $this->belongsToMany(
        Role::class,
        'role_permissions',
        'permission_id',
        'role_id'
    );
}

    protected function casts(): array
    {
        return ['sensitivity' => PermissionSensitivity::class];
    }
}
