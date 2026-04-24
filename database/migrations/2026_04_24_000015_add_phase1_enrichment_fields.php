<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->unsignedInteger('rooms_count')->nullable()->after('distance_to_centre_meters');
            $table->unsignedInteger('blocks_count')->nullable()->after('rooms_count');
            $table->unsignedInteger('floors_count')->nullable()->after('blocks_count');
            $table->unsignedInteger('restaurants_count')->nullable()->after('floors_count');
            $table->unsignedInteger('bars_count')->nullable()->after('restaurants_count');
            $table->unsignedInteger('pools_count')->nullable()->after('bars_count');
            $table->unsignedInteger('sports_leisure_count')->nullable()->after('pools_count');
            $table->decimal('distance_to_airport_km', 8, 2)->nullable()->after('sports_leisure_count');
            $table->boolean('has_lift')->nullable()->after('has_family_rooms');
            $table->boolean('ground_floor_available')->nullable()->after('has_lift');
            $table->text('accessibility_issues')->nullable()->after('ground_floor_available');
        });

        Schema::table('holiday_packages', function (Blueprint $table) {
            $table->decimal('local_beer_price', 8, 2)->nullable()->after('transfer_minutes');
            $table->decimal('three_course_meal_for_two_price', 8, 2)->nullable()->after('local_beer_price');
            $table->string('board_recommended', 64)->nullable()->after('board_type');
            $table->string('outbound_flight_time_text', 64)->nullable()->after('transfer_minutes');
            $table->string('inbound_flight_time_text', 64)->nullable()->after('outbound_flight_time_text');
        });
    }

    public function down(): void
    {
        Schema::table('holiday_packages', function (Blueprint $table) {
            $table->dropColumn([
                'local_beer_price',
                'three_course_meal_for_two_price',
                'board_recommended',
                'outbound_flight_time_text',
                'inbound_flight_time_text',
            ]);
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'rooms_count',
                'blocks_count',
                'floors_count',
                'restaurants_count',
                'bars_count',
                'pools_count',
                'sports_leisure_count',
                'distance_to_airport_km',
                'has_lift',
                'ground_floor_available',
                'accessibility_issues',
            ]);
        });
    }
};
