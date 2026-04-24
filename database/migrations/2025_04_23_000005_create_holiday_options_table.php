<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_source_id')->constrained()->cascadeOnDelete();
            $table->string('provider_option_id', 120);
            $table->string('provider_hotel_id', 120)->nullable();
            $table->string('provider_url', 2048);
            $table->string('hotel_name', 200);
            $table->string('hotel_slug', 200);
            $table->string('resort_name', 200)->nullable();
            $table->string('destination_name', 200);
            $table->string('destination_country', 120);
            $table->string('airport_code', 8);
            $table->date('departure_date');
            $table->date('return_date');
            $table->unsignedSmallInteger('nights');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('infants')->default(0);
            $table->string('board_type', 64)->nullable();
            $table->decimal('price_total', 12, 2);
            $table->decimal('price_per_person', 10, 2)->nullable();
            $table->string('currency', 3)->default('GBP');
            $table->unsignedSmallInteger('flight_outbound_duration_minutes')->nullable();
            $table->unsignedSmallInteger('flight_inbound_duration_minutes')->nullable();
            $table->unsignedSmallInteger('transfer_minutes')->nullable();
            $table->unsignedInteger('distance_to_beach_meters')->nullable();
            $table->unsignedInteger('distance_to_centre_meters')->nullable();
            $table->unsignedTinyInteger('star_rating')->nullable();
            $table->decimal('review_score', 3, 1)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->boolean('is_family_friendly')->default(false);
            $table->boolean('has_kids_club')->default(false);
            $table->boolean('has_waterpark')->default(false);
            $table->boolean('has_family_rooms')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('raw_attributes')->nullable();
            $table->string('signature_hash', 64);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_source_id', 'signature_hash'], 'holiday_options_provider_signature_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_options');
    }
};
