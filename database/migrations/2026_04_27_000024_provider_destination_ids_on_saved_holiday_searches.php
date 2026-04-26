<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saved_holiday_searches')) {
            return;
        }

        if (Schema::hasColumn('saved_holiday_searches', 'provider_destination_ids')) {
            return;
        }

        if (Schema::hasColumn('saved_holiday_searches', 'jet2_destination_area_ids')) {
            Schema::table('saved_holiday_searches', function (Blueprint $table) {
                $table->json('provider_destination_ids')->nullable()->after('destination_preferences');
            });

            $this->portJet2JsonToMap();

            Schema::table('saved_holiday_searches', function (Blueprint $table) {
                $table->dropColumn('jet2_destination_area_ids');
            });
        } else {
            Schema::table('saved_holiday_searches', function (Blueprint $table) {
                $table->json('provider_destination_ids')->nullable()->after('destination_preferences');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('saved_holiday_searches') || ! Schema::hasColumn('saved_holiday_searches', 'provider_destination_ids')) {
            return;
        }

        if (! Schema::hasColumn('saved_holiday_searches', 'jet2_destination_area_ids')) {
            Schema::table('saved_holiday_searches', function (Blueprint $table) {
                $table->json('jet2_destination_area_ids')->nullable()->after('destination_preferences');
            });
        }

        $rows = DB::table('saved_holiday_searches')
            ->whereNotNull('provider_destination_ids')
            ->orderBy('id')
            ->get(['id', 'provider_destination_ids']);

        foreach ($rows as $row) {
            $map = json_decode((string) $row->provider_destination_ids, true);
            $ids = (is_array($map) && isset($map['jet2']) && is_array($map['jet2'])) ? $map['jet2'] : null;
            if ($ids !== null) {
                DB::table('saved_holiday_searches')->where('id', $row->id)->update([
                    'jet2_destination_area_ids' => json_encode($ids),
                ]);
            }
        }

        if (Schema::hasColumn('saved_holiday_searches', 'jet2_destination_area_ids') && Schema::hasColumn('saved_holiday_searches', 'provider_destination_ids')) {
            Schema::table('saved_holiday_searches', function (Blueprint $table) {
                $table->dropColumn('provider_destination_ids');
            });
        }
    }

    private function portJet2JsonToMap(): void
    {
        $rows = DB::table('saved_holiday_searches')
            ->whereNotNull('jet2_destination_area_ids')
            ->orderBy('id')
            ->get(['id', 'jet2_destination_area_ids']);

        foreach ($rows as $row) {
            $ids = json_decode((string) $row->jet2_destination_area_ids, true);
            if (is_array($ids) && $ids !== []) {
                DB::table('saved_holiday_searches')->where('id', $row->id)->update([
                    'provider_destination_ids' => json_encode(['jet2' => $ids]),
                ]);
            }
        }
    }
};
