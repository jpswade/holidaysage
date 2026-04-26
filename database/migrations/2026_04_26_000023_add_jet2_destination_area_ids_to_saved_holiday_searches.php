<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_holiday_searches', function (Blueprint $table) {
            $table->json('jet2_destination_area_ids')->nullable()->after('destination_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('saved_holiday_searches', function (Blueprint $table) {
            $table->dropColumn('jet2_destination_area_ids');
        });
    }
};
