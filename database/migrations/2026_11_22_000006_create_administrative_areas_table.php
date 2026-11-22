<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** P01.05 administrative areas: a per-country hierarchy that can never cross countries. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_areas', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('level_code', 40);
            $table->string('area_code', 50)->nullable();
            $table->string('canonical_name', 160);
            $table->boolean('is_active')->default(true);

            // Target of the same-country parent key below.
            $table->unique(['country_code', 'id'], 'administrative_areas_country_id_unique');

            $table->foreign('country_code', 'administrative_areas_country_fk')
                ->references('iso2')->on('countries')
                ->restrictOnUpdate()->restrictOnDelete();

            // A parent must exist in the same country: (country_code, parent_id) -> (country_code, id).
            // When parent_id is NULL the key is not checked, so top-level areas are allowed.
            $table->foreign(['country_code', 'parent_id'], 'administrative_areas_parent_same_country_fk')
                ->references(['country_code', 'id'])->on('administrative_areas')
                ->restrictOnUpdate()->restrictOnDelete();

            $table->index(['country_code', 'parent_id'], 'administrative_areas_country_parent_index');
            $table->index(['country_code', 'level_code'], 'administrative_areas_country_level_index');
        });

        // Area codes are unique within a country when present (SQLite and PostgreSQL).
        DB::statement('CREATE UNIQUE INDEX administrative_areas_country_area_code_unique ON administrative_areas (country_code, area_code) WHERE area_code IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_areas');
    }
};
