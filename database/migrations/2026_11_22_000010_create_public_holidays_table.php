<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->unsignedBigInteger('administrative_area_id')->nullable();
            $table->date('holiday_date');
            $table->string('name', 180);
            $table->string('status', 20);

            $table->foreign('country_code')->references('iso2')->on('countries')
                ->restrictOnUpdate()->restrictOnDelete();
            $table->foreign(['country_code', 'administrative_area_id'], 'public_holidays_area_country_fk')
                ->references(['country_code', 'id'])->on('administrative_areas')
                ->restrictOnUpdate()->restrictOnDelete();

            $table->index(['country_code', 'holiday_date', 'id']);
            $table->index(['country_code', 'administrative_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
    }
};
