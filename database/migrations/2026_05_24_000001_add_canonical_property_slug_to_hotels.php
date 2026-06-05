<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds canonical_property_slug to hotels for the unified property concept used by /holidays/{slug}.
 *
 * Multiple hotel rows from different providers that represent the same real-world property
 * share this slug, so the public URL is one segment and provider-agnostic. We seed every
 * existing row with its hotel_slug; merging providers later is a separate normaliser concern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table): void {
            $table->string('canonical_property_slug', 200)->nullable()->after('hotel_slug');
            $table->index('canonical_property_slug', 'hotels_canonical_property_slug_index');
        });

        $rows = DB::table('hotels')->select('id', 'hotel_slug', 'hotel_name')->get();
        $seen = [];
        foreach ($rows as $row) {
            $base = (string) ($row->hotel_slug ?? '');
            if ($base === '' || $base === null) {
                $base = Str::slug((string) ($row->hotel_name ?? 'hotel'));
            }
            if ($base === '') {
                $base = 'hotel-'.$row->id;
            }
            // First occurrence keeps the bare slug; later duplicates get a numeric tail so
            // backfill is deterministic and the unique index below holds.
            $count = $seen[$base] ?? 0;
            $slug = $count === 0 ? $base : $base.'-'.($count + 1);
            $seen[$base] = $count + 1;

            DB::table('hotels')->where('id', $row->id)->update([
                'canonical_property_slug' => $slug,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table): void {
            $table->dropIndex('hotels_canonical_property_slug_index');
            $table->dropColumn('canonical_property_slug');
        });
    }
};
