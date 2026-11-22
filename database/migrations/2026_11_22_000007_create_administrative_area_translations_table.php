<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P01.06 one display name per administrative area and locale. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_area_translations', function (Blueprint $table) {
            $table->unsignedBigInteger('area_id');
            $table->string('locale_code', 20);
            $table->string('display_name', 180);

            $table->primary(['area_id', 'locale_code']);
            $table->foreign('area_id', 'administrative_area_translations_area_fk')
                ->references('id')->on('administrative_areas')
                ->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('locale_code', 'administrative_area_translations_locale_fk')
                ->references('code')->on('locales')
                ->restrictOnUpdate()->restrictOnDelete();
            $table->index('locale_code', 'administrative_area_translations_locale_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_area_translations');
    }
};
