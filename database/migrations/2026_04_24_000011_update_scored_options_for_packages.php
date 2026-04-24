<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('scored_holiday_options');
        Schema::create('scored_holiday_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_holiday_search_id')->constrained('saved_holiday_searches')->cascadeOnDelete();
            $table->foreignId('saved_holiday_search_run_id')->constrained('saved_holiday_search_runs')->cascadeOnDelete();
            $table->foreignId('holiday_package_id')->constrained('holiday_packages')->cascadeOnDelete();
            $table->decimal('overall_score', 5, 2);
            $table->decimal('travel_score', 5, 2)->nullable();
            $table->decimal('value_score', 5, 2)->nullable();
            $table->decimal('family_fit_score', 5, 2)->nullable();
            $table->decimal('location_score', 5, 2)->nullable();
            $table->decimal('board_score', 5, 2)->nullable();
            $table->decimal('price_score', 5, 2)->nullable();
            $table->boolean('is_disqualified')->default(false);
            $table->json('disqualification_reasons')->nullable();
            $table->json('warning_flags')->nullable();
            $table->text('recommendation_summary')->nullable();
            $table->json('recommendation_reasons')->nullable();
            $table->unsignedSmallInteger('rank_position')->nullable();
            $table->timestamps();
            $table->unique(
                ['saved_holiday_search_id', 'saved_holiday_search_run_id', 'holiday_package_id'],
                'scored_hp_search_run_package_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scored_holiday_options');
        Schema::create('scored_holiday_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_holiday_search_id')->constrained('saved_holiday_searches')->cascadeOnDelete();
            $table->foreignId('saved_holiday_search_run_id')->constrained('saved_holiday_search_runs')->cascadeOnDelete();
            $table->foreignId('holiday_option_id')->constrained('holiday_options')->cascadeOnDelete();
            $table->decimal('overall_score', 5, 2);
            $table->decimal('travel_score', 5, 2)->nullable();
            $table->decimal('value_score', 5, 2)->nullable();
            $table->decimal('family_fit_score', 5, 2)->nullable();
            $table->decimal('location_score', 5, 2)->nullable();
            $table->decimal('board_score', 5, 2)->nullable();
            $table->decimal('price_score', 5, 2)->nullable();
            $table->boolean('is_disqualified')->default(false);
            $table->json('disqualification_reasons')->nullable();
            $table->json('warning_flags')->nullable();
            $table->text('recommendation_summary')->nullable();
            $table->json('recommendation_reasons')->nullable();
            $table->unsignedSmallInteger('rank_position')->nullable();
            $table->timestamps();
            $table->unique(
                ['saved_holiday_search_id', 'saved_holiday_search_run_id', 'holiday_option_id'],
                'scored_ho_search_run_option_unique'
            );
        });
    }
};
