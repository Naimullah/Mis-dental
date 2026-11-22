<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P01.10 tax codes: one row per historical version of a country's tax code.
 *
 * A version's country, code, rate and start date never change once saved; a rate change
 * closes the open version and adds a new one. Overlapping versions of the same code are
 * rejected by the service under a lock on the country row (no database exclusion
 * constraint, so no btree_gist extension is needed). Dates are inclusive on both ends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->string('code', 40);
            $table->string('description', 160);
            $table->decimal('rate_percent', 9, 5);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            // Two versions of one code cannot start on the same day.
            $table->unique(['country_code', 'code', 'effective_from'], 'tax_codes_country_code_from_unique');
            // Target for future same-country foreign keys (for example invoice lines).
            $table->unique(['country_code', 'id'], 'tax_codes_country_id_unique');

            $table->foreign('country_code', 'tax_codes_country_fk')
                ->references('iso2')->on('countries')
                ->restrictOnUpdate()->restrictOnDelete();

            $table->index(['country_code', 'code', 'effective_to'], 'tax_codes_lookup_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tax_codes ADD CONSTRAINT tax_codes_rate_chk CHECK (rate_percent >= 0 AND rate_percent <= 100)');
            DB::statement('ALTER TABLE tax_codes ADD CONSTRAINT tax_codes_dates_chk CHECK (effective_to IS NULL OR effective_to >= effective_from)');
            DB::statement("ALTER TABLE tax_codes ADD CONSTRAINT tax_codes_code_chk CHECK (code ~ '^[A-Z0-9][A-Z0-9_-]{0,39}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_codes');
    }
};
