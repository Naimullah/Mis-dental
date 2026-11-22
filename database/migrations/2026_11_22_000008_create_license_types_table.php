<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P01.07 license types, owned by one country. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_types', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('applies_to', 24);
            $table->boolean('is_active')->default(true);

            // A code identifies a license type within its country.
            $table->unique(['country_code', 'code'], 'license_types_country_code_unique');
            // Target for future same-country foreign keys from license records.
            $table->unique(['country_code', 'id'], 'license_types_country_id_unique');

            $table->foreign('country_code', 'license_types_country_fk')
                ->references('iso2')->on('countries')
                ->restrictOnUpdate()->restrictOnDelete();

            $table->index(['country_code', 'applies_to', 'is_active'], 'license_types_selection_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_types');
    }
};
