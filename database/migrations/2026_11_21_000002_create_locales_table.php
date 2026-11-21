<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('english_name', 100);
            $table->string('text_direction', 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locales');
    }
};
