<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_holiday_searches', function (Blueprint $table) {
            $table->json('provider_url_params')->nullable()->after('provider_occupancy');
        });
    }

    public function down(): void
    {
        Schema::table('saved_holiday_searches', function (Blueprint $table) {
            $table->dropColumn('provider_url_params');
        });
    }
};
