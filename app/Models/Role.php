<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

/**
 * P03.04 role. clinic_id, code, scope and is_system never change after creation; system roles
 * are only written by `php artisan roles:sync`. Roles are not deleted (assignments come later).
 *
 * @property int $id
 * @property int|null $clinic_id
 * @property string $code
 * @property string $name
 * @property 'platform'|'clinic' $scope
 * @property bool $is_system
 */
#[Fillable(['name'])]
class Role extends Model
{
    public const SCOPES = ['platform', 'clinic'];

    public const LOCKED = ['clinic_id', 'code', 'scope', 'is_system'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'clinic_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (Role $role): void {
            if ($role->getOriginal('is_system')) {
                throw new LogicException('System roles are defined in config/roles.php and synchronized by roles:sync.');
            }

            foreach (self::LOCKED as $attribute) {
                if ($role->isDirty($attribute)) {
                    throw new LogicException("A role's {$attribute} cannot be changed after creation.");
                }
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Roles cannot be deleted.');
        });
    }

    /** @param Builder<Role> $query */
    public function scopeCustom(Builder $query): void
    {
        $query->where('is_system', false);
    }

    /** @param Builder<Role> $query */
    public function scopeSystem(Builder $query): void
    {
        $query->where('is_system', true);
    }

/**
 * Permissions assigned to this role.
 *
 * @return BelongsToMany<Permission, $this>
 */
public function permissions(): BelongsToMany
{
    return $this->belongsToMany(
        Permission::class,
        'role_permissions',
        'role_id',
        'permission_id'
    );
}

}
