<?php

$userIds = static fn (string $value): array => array_values(array_filter(
    array_map('trim', explode(',', $value)),
    static fn (string $id): bool => preg_match('/\A[1-9][0-9]*\z/', $id) === 1,
));

return [
    // Temporary until role assignments exist: platform users allowed to manage roles (comma-separated IDs).
    'maintainer_ids' => $userIds((string) env('ROLE_MAINTAINER_IDS', '')),

    /*
    | System roles (P03.04), from the legacy RBAC bootstrap. They are written only by
    | `php artisan roles:sync --apply`, cannot be changed on screen, and their code and
    | scope never change. Clinic system roles are templates shared by every clinic.
    */
    'definitions' => [
        ['code' => 'platform_admin', 'name' => 'Platform Administrator', 'scope' => 'platform'],
        ['code' => 'clinic_admin', 'name' => 'Clinic Super Administrator', 'scope' => 'clinic'],
        ['code' => 'clinic_sub_admin', 'name' => 'Clinic Sub Administrator', 'scope' => 'clinic'],
        ['code' => 'receptionist', 'name' => 'Receptionist', 'scope' => 'clinic'],
        ['code' => 'dentist', 'name' => 'Dentist', 'scope' => 'clinic'],
        ['code' => 'finance_cashier', 'name' => 'Finance / Cashier', 'scope' => 'clinic'],
        ['code' => 'hr_manager', 'name' => 'HR Manager', 'scope' => 'clinic'],
        ['code' => 'payroll_officer', 'name' => 'Payroll Officer', 'scope' => 'clinic'],
    ],
];
