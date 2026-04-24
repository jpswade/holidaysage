<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_holiday_searches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->string('provider_import_url', 2048)->nullable();
            $table->string('departure_airport_code', 8);
            $table->string('departure_airport_name', 120)->nullable();
            $table->date('travel_start_date')->nullable();
            $table->date('travel_end_date')->nullable();
            $table->unsignedSmallInteger('travel_date_flexibility_days')->default(0);
            $table->unsignedSmallInteger('duration_min_nights');
            $table->unsignedSmallInteger('duration_max_nights');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('infants')->default(0);
            $table->decimal('budget_total', 10, 2)->nullable();
            $table->decimal('budget_per_person', 10, 2)->nullable();
            $table->unsignedSmallInteger('max_flight_minutes')->nullable();
            $table->unsignedSmallInteger('max_transfer_minutes')->nullable();
            $table->json('board_preferences')->nullable();
            $table->json('destination_preferences')->nullable();
            $table->json('feature_preferences')->nullable();
            $table->json('excluded_destinations')->nullable();
            $table->json('excluded_features')->nullable();
            $table->string('sort_preference', 64)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamp('last_scored_at')->nullable();
            $table->timestamp('next_refresh_due_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_holiday_searches');
    }
};
