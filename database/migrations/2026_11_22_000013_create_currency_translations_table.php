<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_translations', function (Blueprint $table) {
            $table->char('currency_code', 3);
            $table->string('locale_code', 20);
            $table->string('display_name', 120);
            $table->primary(['currency_code', 'locale_code']);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('locale_code')->references('code')->on('locales')->restrictOnUpdate()->restrictOnDelete();
            $table->index('locale_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_translations');
    }
};
