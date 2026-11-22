<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_translations', function (Blueprint $table) {
            $table->char('country_code', 2);
            $table->string('locale_code', 20);
            $table->string('display_name', 160);
            $table->primary(['country_code', 'locale_code']);
            $table->foreign('country_code')->references('iso2')->on('countries')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('locale_code')->references('code')->on('locales')->restrictOnUpdate()->restrictOnDelete();
            $table->index('locale_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_translations');
    }
};
