<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P03.04 roles.
 *
 * - platform roles (system or custom): clinic_id NULL
 * - clinic system roles (templates shared by every clinic): clinic_id NULL
 * - clinic custom roles: clinic_id set
 *
 * Codes are unique among roles without a clinic, and unique within each clinic.
 * clinic_id gets its foreign key to clinics(id) with the clinics module; until then the
 * service refuses to create clinic custom roles, so no unlinked clinic ids can exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite only accepts CHECK constraints in CREATE TABLE.
            DB::statement(<<<'SQL'
                CREATE TABLE roles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    clinic_id INTEGER NULL,
                    code VARCHAR(100) NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    scope VARCHAR(16) NOT NULL,
                    is_system TINYINT(1) NOT NULL DEFAULT 0,
                    CONSTRAINT roles_scope_check CHECK (scope IN ('platform', 'clinic')),
                    CONSTRAINT roles_is_system_check CHECK (is_system IN (0, 1)),
                    CONSTRAINT roles_code_check CHECK (
                        length(code) BETWEEN 2 AND 100 AND code GLOB '[a-z]*' AND code NOT GLOB '*[^a-z0-9_]*'
                    ),
                    CONSTRAINT roles_name_check CHECK (trim(name) <> ''),
                    CONSTRAINT roles_clinic_scope_check CHECK (
                        (scope = 'platform' AND clinic_id IS NULL)
                        OR (scope = 'clinic' AND is_system = 1 AND clinic_id IS NULL)
                        OR (scope = 'clinic' AND is_system = 0 AND clinic_id IS NOT NULL)
                    )
                )
                SQL);
        } else {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('clinic_id')->nullable();
                $table->string('code', 100);
                $table->string('name', 150);
                $table->string('scope', 16);
                $table->boolean('is_system')->default(false);
            });

            DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_scope_check CHECK (scope IN ('platform', 'clinic'))");
            DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_code_check CHECK (code ~ '^[a-z][a-z0-9_]{1,99}$')");
            DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_name_check CHECK (trim(name) <> '')");
            DB::statement(<<<'SQL'
                ALTER TABLE roles ADD CONSTRAINT roles_clinic_scope_check CHECK (
                    (scope = 'platform' AND clinic_id IS NULL)
                    OR (scope = 'clinic' AND is_system AND clinic_id IS NULL)
                    OR (scope = 'clinic' AND NOT is_system AND clinic_id IS NOT NULL)
                )
                SQL);
        }

        // Roles without a clinic (platform roles and clinic templates) share one code space.
        DB::statement('CREATE UNIQUE INDEX roles_global_code_unique ON roles (code) WHERE clinic_id IS NULL');
        // A clinic's own custom roles are unique within that clinic.
        DB::statement('CREATE UNIQUE INDEX roles_clinic_code_unique ON roles (clinic_id, code)');
        // Target for a future same-clinic foreign key from role assignments.
        DB::statement('CREATE UNIQUE INDEX roles_id_clinic_unique ON roles (id, clinic_id)');
        DB::statement('CREATE INDEX roles_scope_system_index ON roles (scope, is_system)');
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
