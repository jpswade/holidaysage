<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_search_import_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_holiday_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_source_id')->constrained()->cascadeOnDelete();
            $table->string('original_url', 2048);
            $table->json('extracted_criteria');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_search_import_mappings');
    }
};
