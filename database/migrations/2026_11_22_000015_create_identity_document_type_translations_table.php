<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_document_type_translations', function (Blueprint $table) {
            $table->unsignedBigInteger('document_type_id');
            $table->string('locale_code', 20);
            $table->string('display_name', 160);
            $table->text('help_text')->nullable();
            $table->primary(['document_type_id', 'locale_code']);
            $table->foreign('document_type_id')->references('id')->on('identity_document_types')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('locale_code')->references('code')->on('locales')->restrictOnUpdate()->restrictOnDelete();
            $table->index('locale_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_document_type_translations');
    }
};
