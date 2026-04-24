<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->boolean('is_family_friendly')->nullable()->default(null)->change();
            $table->boolean('has_kids_club')->nullable()->default(null)->change();
            $table->boolean('has_waterpark')->nullable()->default(null)->change();
            $table->boolean('has_family_rooms')->nullable()->default(null)->change();
        });

        DB::table('hotels')->where('is_family_friendly', 0)->update(['is_family_friendly' => null]);
        DB::table('hotels')->where('has_kids_club', 0)->update(['has_kids_club' => null]);
        DB::table('hotels')->where('has_waterpark', 0)->update(['has_waterpark' => null]);
        DB::table('hotels')->where('has_family_rooms', 0)->update(['has_family_rooms' => null]);
    }

    public function down(): void
    {
        DB::table('hotels')->whereNull('is_family_friendly')->update(['is_family_friendly' => 0]);
        DB::table('hotels')->whereNull('has_kids_club')->update(['has_kids_club' => 0]);
        DB::table('hotels')->whereNull('has_waterpark')->update(['has_waterpark' => 0]);
        DB::table('hotels')->whereNull('has_family_rooms')->update(['has_family_rooms' => 0]);

        Schema::table('hotels', function (Blueprint $table) {
            $table->boolean('is_family_friendly')->nullable(false)->default(false)->change();
            $table->boolean('has_kids_club')->nullable(false)->default(false)->change();
            $table->boolean('has_waterpark')->nullable(false)->default(false)->change();
            $table->boolean('has_family_rooms')->nullable(false)->default(false)->change();
        });
    }
};
