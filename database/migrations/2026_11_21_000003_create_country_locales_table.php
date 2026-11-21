<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_locales', function (Blueprint $table) {
            $table->char('country_code', 2);
            $table->string('locale_code', 20);
            $table->boolean('is_default');

            $table->primary(['country_code', 'locale_code']);
            $table->foreign('country_code')->references('iso2')->on('countries')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('locale_code')->references('code')->on('locales')->restrictOnUpdate()->restrictOnDelete();
            $table->index('locale_code');
        });

        // At most one default language per country (partial unique index; SQLite and PostgreSQL).
        DB::statement('CREATE UNIQUE INDEX country_locales_one_default ON country_locales (country_code) WHERE is_default');
    }

    public function down(): void
    {
        Schema::dropIfExists('country_locales');
    }
};
