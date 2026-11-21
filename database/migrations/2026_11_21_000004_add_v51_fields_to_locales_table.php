<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P01.03 Locales — Step 02 schema extension (owner-authorized for review, 2026-11-21).
 *
 * Additive only: adds four columns to the existing locales table. It does not touch the
 * primary key (code), existing columns, existing rows, or the country_locales foreign keys.
 * Existing rows become active (is_active = true); the three optional fields start empty.
 *
 * The allowed calendar and numbering-system codes are enforced by application validation
 * (gregory, persian, islamic, islamic-civil / latn, arab, arabext), not by the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locales', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->string('native_name', 120)->nullable();
            $table->string('default_calendar_code', 20)->nullable();
            $table->string('default_numbering_system_code', 20)->nullable();
        });
    }

    /**
     * Rolling back removes the four columns and any values stored in them.
     */
    public function down(): void
    {
        Schema::table('locales', function (Blueprint $table) {
            $table->dropColumn([
                'is_active',
                'native_name',
                'default_calendar_code',
                'default_numbering_system_code',
            ]);
        });
    }
};
