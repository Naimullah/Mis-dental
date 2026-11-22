<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_translations', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('locale_code', 20);
            $table->string('display_name', 160);
            $table->text('description')->nullable();
            $table->primary(['permission_id', 'locale_code']);
            $table->foreign('permission_id')->references('id')->on('permissions')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('locale_code')->references('code')->on('locales')->restrictOnUpdate()->restrictOnDelete();
            $table->index('locale_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_translations');
    }
};
