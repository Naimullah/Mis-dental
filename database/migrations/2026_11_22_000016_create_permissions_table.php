<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite requires CHECK constraints to be declared while creating the table.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE permissions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    code VARCHAR(120) NOT NULL UNIQUE,
                    description VARCHAR(255) NOT NULL,
                    sensitivity VARCHAR(16) NOT NULL,
                    CONSTRAINT permissions_sensitivity_check CHECK (sensitivity IN ('low', 'medium', 'high', 'critical')),
                    CONSTRAINT permissions_description_check CHECK (TRIM(description) <> '')
                )
                SQL);

            return;
        }

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('description', 255);
            $table->string('sensitivity', 16);
        });
        DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_sensitivity_check CHECK (sensitivity IN ('low', 'medium', 'high', 'critical'))");
        DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_description_check CHECK (TRIM(description) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
