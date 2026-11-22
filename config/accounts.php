<?php

return [
    'maintainer_ids' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('PLATFORM_ACCOUNT_MAINTAINER_IDS', ''))),
        static fn (string $id): bool => preg_match('/\A[1-9][0-9]*\z/', $id) === 1,
    )),
];
