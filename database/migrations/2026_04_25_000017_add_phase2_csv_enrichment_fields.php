<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->unsignedTinyInteger('kids_club_age_min')->nullable()->after('has_kids_club');
            $table->boolean('play_area')->nullable()->after('has_family_rooms');
            $table->boolean('evening_entertainment')->nullable()->after('play_area');
            $table->boolean('kids_disco')->nullable()->after('evening_entertainment');
            $table->boolean('gym')->nullable()->after('kids_disco');
            $table->boolean('spa')->nullable()->after('gym');
            $table->boolean('adults_only_area')->nullable()->after('spa');
            $table->boolean('promenade')->nullable()->after('adults_only_area');
            $table->boolean('near_shops')->nullable()->after('promenade');
            $table->unsignedInteger('distance_to_shops_meters')->nullable()->after('near_shops');
            $table->boolean('cafes_bars')->nullable()->after('distance_to_shops_meters');
            $table->unsignedInteger('distance_to_cafes_bars_meters')->nullable()->after('cafes_bars');
            $table->boolean('harbour')->nullable()->after('distance_to_cafes_bars_meters');
            $table->unsignedInteger('steps_count')->nullable()->after('accessibility_issues');
            $table->text('accessibility_notes')->nullable()->after('steps_count');
            $table->boolean('cots_available')->nullable()->after('accessibility_notes');
            $table->text('introduction_snippet')->nullable()->after('cots_available');
            $table->text('style_keywords')->nullable()->after('introduction_snippet');
        });

        Schema::table('holiday_packages', function (Blueprint $table) {
            $table->decimal('flight_time_hours_est', 4, 2)->nullable()->after('transfer_minutes');
            $table->string('transfer_type', 32)->nullable()->after('flight_time_hours_est');
        });
    }

    public function down(): void
    {
        Schema::table('holiday_packages', function (Blueprint $table) {
            $table->dropColumn([
                'flight_time_hours_est',
                'transfer_type',
            ]);
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'kids_club_age_min',
                'play_area',
                'evening_entertainment',
                'kids_disco',
                'gym',
                'spa',
                'adults_only_area',
                'promenade',
                'near_shops',
                'distance_to_shops_meters',
                'cafes_bars',
                'distance_to_cafes_bars_meters',
                'harbour',
                'steps_count',
                'accessibility_notes',
                'cots_available',
                'introduction_snippet',
                'style_keywords',
            ]);
        });
    }
};
