<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_holiday_searches', function (Blueprint $table): void {
            $table->string('share_token', 64)->nullable()->unique()->after('slug');
            $table->boolean('sharing_enabled')->default(false)->after('share_token');
        });
    }

    public function down(): void
    {
        Schema::table('saved_holiday_searches', function (Blueprint $table): void {
            $table->dropColumn(['share_token', 'sharing_enabled']);
        });
    }
};
