<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_shortlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('holiday_shortlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scored_holiday_option_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['holiday_shortlist_id', 'scored_holiday_option_id'], 'holiday_shortlist_items_unique_per_list');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_shortlist_items');
    }
};
