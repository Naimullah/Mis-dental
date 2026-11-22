<?php

$ownerId = trim(
    (string) env('ROLE_PERMISSION_OWNER_ID', '')
);

return [
    'owner_user_id' =>
        preg_match('/\A[1-9][0-9]*\z/', $ownerId) === 1
            ? (int) $ownerId
            : null,

    'approval_expiry_minutes' => 10,
];
