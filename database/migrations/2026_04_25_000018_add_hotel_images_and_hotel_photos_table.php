<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->json('images')->nullable();
        });

        Schema::create('hotel_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->text('external_url');
            $table->string('external_url_hash', 64);
            $table->string('file_path', 512)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'external_url_hash'], 'hotel_photos_hotel_id_external_url_hash_unique');
            $table->index(['hotel_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_photos');
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
