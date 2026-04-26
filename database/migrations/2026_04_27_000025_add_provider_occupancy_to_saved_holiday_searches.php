<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saved_holiday_searches')) {
            return;
        }

        if (Schema::hasColumn('saved_holiday_searches', 'provider_occupancy')) {
            return;
        }

        Schema::table('saved_holiday_searches', function (Blueprint $table) {
            $table->json('provider_occupancy')->nullable()->after('provider_destination_ids');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('saved_holiday_searches') && Schema::hasColumn('saved_holiday_searches', 'provider_occupancy')) {
            Schema::table('saved_holiday_searches', function (Blueprint $table) {
                $table->dropColumn('provider_occupancy');
            });
        }
    }
};
