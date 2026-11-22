<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P01.03 v5.1 alignment: default_calendar_code and default_numbering_system_code are
 * VARCHAR(32) in the specification; migration 000004 created them as VARCHAR(20).
 *
 * Widening only. PostgreSQL: metadata-only change (no table rewrite, no data change).
 * SQLite does not enforce VARCHAR lengths, so nothing is altered there; this avoids
 * SQLite's table rebuild, which would touch the table referenced by country_locales.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE locales ALTER COLUMN default_calendar_code TYPE VARCHAR(32)');
        DB::statement('ALTER TABLE locales ALTER COLUMN default_numbering_system_code TYPE VARCHAR(32)');
    }

    /**
     * Narrowing back would fail if any value is longer than 20 characters, so rollback
     * deliberately leaves the wider columns in place.
     */
    public function down(): void
    {
        //
    }
};
