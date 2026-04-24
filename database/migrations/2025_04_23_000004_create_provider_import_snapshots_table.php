<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_import_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_holiday_search_run_id')->constrained('saved_holiday_search_runs')->cascadeOnDelete();
            $table->foreignId('provider_source_id')->constrained()->cascadeOnDelete();
            $table->string('source_url', 2048);
            $table->unsignedSmallInteger('response_status')->default(0);
            $table->string('snapshot_path', 1024)->nullable();
            $table->string('snapshot_hash', 64)->nullable();
            $table->unsignedInteger('record_count_estimate')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_import_snapshots');
    }
};
