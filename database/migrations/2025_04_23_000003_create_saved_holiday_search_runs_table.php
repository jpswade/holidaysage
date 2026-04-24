<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_holiday_search_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_holiday_search_id')->constrained()->cascadeOnDelete();
            $table->string('run_type', 20);
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('provider_count')->default(0);
            $table->unsignedInteger('raw_record_count')->default(0);
            $table->unsignedInteger('parsed_record_count')->default(0);
            $table->unsignedInteger('normalised_record_count')->default(0);
            $table->unsignedInteger('scored_record_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_holiday_search_runs');
    }
};
