<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P01.08 identity document types, owned by one country.
 * Deliberately has no "required" flag: no identity document is ever mandatory to create a patient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_document_types', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('optional_format_regex', 200)->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['country_code', 'code'], 'identity_document_types_country_code_unique');
            // Target for a future same-country foreign key from patient identity documents.
            $table->unique(['country_code', 'id'], 'identity_document_types_country_id_unique');

            $table->foreign('country_code', 'identity_document_types_country_fk')
                ->references('iso2')->on('countries')
                ->restrictOnUpdate()->restrictOnDelete();

            $table->index(['country_code', 'is_active'], 'identity_document_types_selection_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_document_types');
    }
};
