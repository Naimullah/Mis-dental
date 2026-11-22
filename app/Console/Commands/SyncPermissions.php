<?php

namespace App\Console\Commands;

use App\Services\Permissions\PermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync {--apply : Write the validated catalog definitions}';

    protected $description = 'Preview or apply the version-controlled permission catalog';

    public function handle(PermissionCatalog $catalog): int
    {
        $apply = (bool) $this->option('apply');

        try {
            $changes = $catalog->sync($apply);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->error($field.' — '.$message);
                }
            }

            return self::FAILURE;
        }

        $this->info($apply ? 'Permission catalog synchronized.' : 'Preview of permission catalog changes.');
        foreach (['add' => 'Add', 'update' => 'Update', 'unlisted' => 'Unlisted (retained)'] as $key => $label) {
            $this->line($label.': '.(implode(', ', $changes[$key]) ?: 'none'));
        }

        return self::SUCCESS;
    }
}
