<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_holiday_search_runs', function (Blueprint $table) {
            $table->json('imported_holiday_package_ids')->nullable()->after('imported_holiday_option_ids');
        });
    }

    public function down(): void
    {
        Schema::table('saved_holiday_search_runs', function (Blueprint $table) {
            $table->dropColumn('imported_holiday_package_ids');
        });
    }
};
