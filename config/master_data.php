<?php

return [

    /*
    | Temporary access switch for master-data screens (Countries, Currencies).
    |
    | The roles/permissions foundation (platform_admin) is not built yet. Until it
    | is, these screens are closed unless this switch is enabled, and they are
    | always closed in production regardless of the switch.
    */
    'open_access' => (bool) env('MASTER_DATA_OPEN_ACCESS', false),

    'export_max_rows' => (int) env('MASTER_DATA_EXPORT_MAX_ROWS', 5000),

];
