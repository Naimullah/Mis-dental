<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_type_translations', function (Blueprint $table) {
            $table->unsignedBigInteger('license_type_id');
            $table->string('locale_code', 20);
            $table->string('display_name', 180);
            $table->text('help_text')->nullable();
            $table->primary(['license_type_id', 'locale_code']);
            $table->foreign('license_type_id')->references('id')->on('license_types')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('locale_code')->references('code')->on('locales')->restrictOnUpdate()->restrictOnDelete();
            $table->index('locale_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_type_translations');
    }
};
