<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('provider_option_id', 120);
            $table->string('provider_url', 2048);
            $table->string('airport_code', 8);
            $table->date('departure_date');
            $table->date('return_date');
            $table->unsignedSmallInteger('nights');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('infants')->default(0);
            $table->string('board_type', 64)->default('');
            $table->decimal('price_total', 12, 2);
            $table->decimal('price_per_person', 10, 2)->nullable();
            $table->string('currency', 3)->default('GBP');
            $table->unsignedSmallInteger('flight_outbound_duration_minutes')->nullable();
            $table->unsignedSmallInteger('flight_inbound_duration_minutes')->nullable();
            $table->unsignedSmallInteger('transfer_minutes')->nullable();
            $table->json('raw_attributes')->nullable();
            $table->string('signature_hash', 64);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_source_id', 'signature_hash'], 'holiday_packages_provider_signature_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_packages');
    }
};
