<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_source_id')->constrained()->cascadeOnDelete();
            $table->string('provider_hotel_id', 120)->nullable();
            $table->string('hotel_identity_hash', 64);
            $table->string('hotel_name', 200);
            $table->string('hotel_slug', 200);
            $table->string('resort_name', 200)->nullable();
            $table->string('destination_name', 200);
            $table->string('destination_country', 120)->nullable();
            $table->unsignedTinyInteger('star_rating')->nullable();
            $table->decimal('review_score', 3, 1)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->boolean('is_family_friendly')->nullable();
            $table->boolean('has_kids_club')->nullable();
            $table->boolean('has_waterpark')->nullable();
            $table->boolean('has_family_rooms')->nullable();
            $table->unsignedInteger('distance_to_beach_meters')->nullable();
            $table->unsignedInteger('distance_to_centre_meters')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('raw_attributes')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_source_id', 'hotel_identity_hash'], 'hotels_provider_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
