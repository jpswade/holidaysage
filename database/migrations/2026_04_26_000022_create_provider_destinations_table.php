<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_source_id')->constrained()->cascadeOnDelete();
            $table->string('area_id', 32);
            $table->string('name', 200)->nullable();
            $table->string('slug', 200)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['provider_source_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_destinations');
    }
};
