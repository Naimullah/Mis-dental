<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P02.05 tax code translations: one translated name and optional explanation per tax code
 * version and language. A new tax code version copies the translations of the version it
 * replaces (see TaxCodeService::addVersion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_code_translations', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_code_id');
            $table->string('locale_code', 20);
            $table->string('display_name', 140);
            $table->text('explanation')->nullable();

            $table->primary(['tax_code_id', 'locale_code']);
            $table->foreign('tax_code_id', 'tax_code_translations_tax_code_fk')
                ->references('id')->on('tax_codes')
                ->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('locale_code', 'tax_code_translations_locale_fk')
                ->references('code')->on('locales')
                ->restrictOnUpdate()->restrictOnDelete();
            $table->index('locale_code', 'tax_code_translations_locale_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_code_translations');
    }
};
