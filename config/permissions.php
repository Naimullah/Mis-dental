<?php

use App\Enums\PermissionSensitivity;
use App\Models\Permission;

$userIds = static fn (string $value): array => array_values(array_filter(
    array_map('trim', explode(',', $value)),
    static fn (string $id): bool => preg_match('/\A[1-9][0-9]*\z/', $id) === 1,
));

return [
    // Temporary read access until role assignments exist. Use comma-separated user IDs.
    'viewer_ids' => $userIds((string) env('PERMISSION_CATALOG_VIEWER_IDS', '')),
    'translation_maintainer_ids' => $userIds((string) env('PERMISSION_TRANSLATION_MAINTAINER_IDS', '')),
    'definition_maintainer_ids' => $userIds((string) env('PERMISSION_DEFINITION_MAINTAINER_IDS', '')),

    'definitions' => [
        [
            'code' => Permission::MANAGE_DEFINITIONS,
            'description' => 'Create, update, and delete custom permission definitions.',
            'sensitivity' => PermissionSensitivity::Critical->value,
        ],
        [
            'code' => Permission::MANAGE_ROLES,
            'description' => 'Create and rename custom platform roles; system roles stay read-only.',
            'sensitivity' => PermissionSensitivity::Critical->value,
        ],
        [
            'code' => Permission::MANAGE_USERS,
            'description' => 'Create, edit, activate, and deactivate platform user accounts.',
            'sensitivity' => PermissionSensitivity::Critical->value,
        ],
        [
            'code' => Permission::VIEW_CATALOG,
            'description' => 'View the platform permission catalog.',
            'sensitivity' => PermissionSensitivity::Medium->value,
        ],
        [
            'code' => Permission::MANAGE_MASTER_DATA,
            'description' => 'Access platform master data workflows according to their catalog-specific restrictions.',
            'sensitivity' => PermissionSensitivity::High->value,
        ],
        [
            'code' => Permission::MANAGE_TRANSLATIONS,
            'description' => 'Maintain localized names and descriptions in the permission catalog.',
            'sensitivity' => PermissionSensitivity::High->value,
        ],
    ],
];
