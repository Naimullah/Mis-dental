<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->char('iso2', 2)->primary();
            $table->char('iso3', 3)->nullable()->unique();
            $table->string('english_name', 120);
            $table->boolean('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
