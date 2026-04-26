<?php

namespace Tests\Unit\Models;

use App\Enums\ProviderSourceStatus;
use App\Models\ProviderDestination;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderDestinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_search_exposes_ids_per_provider_key(): void
    {
        $s = new SavedHolidaySearch(['provider_destination_ids' => [
            'jet2' => ['10', '20'],
            'tui' => ['500'],
        ]]);
        $this->assertSame(['10', '20'], $s->providerDestinationIdListFor('jet2'));
        $this->assertSame(['500'], $s->providerDestinationIdListFor('tui'));
        $this->assertSame([], $s->providerDestinationIdListFor('other'));
    }

    public function test_saved_search_exposes_occupancy_wire_string_per_provider(): void
    {
        $s = new SavedHolidaySearch(['provider_occupancy' => [
            'jet2' => 'r2c_r2c1_4',
            'tui' => '2a-0c-0i',
        ]]);
        $this->assertSame('r2c_r2c1_4', $s->providerOccupancyWireFor('jet2'));
        $this->assertSame('2a-0c-0i', $s->providerOccupancyWireFor('tui'));
        $this->assertNull($s->providerOccupancyWireFor('other'));
    }

    public function test_saved_search_exposes_provider_url_param_string(): void
    {
        $s = new SavedHolidaySearch(['provider_url_params' => [
            'jet2' => [
                'airport' => '98_63_3',
                'outboundflighttimes' => '07:00-09:59,10:00-13:59',
            ],
        ]]);
        $this->assertSame('98_63_3', $s->providerUrlParamFor('jet2', 'airport'));
        $this->assertSame('07:00-09:59,10:00-13:59', $s->providerUrlParamFor('jet2', 'outboundflighttimes'));
        $this->assertNull($s->providerUrlParamFor('other', 'airport'));
    }

    public function test_register_stores_jet2_area_id_rows_idempotently(): void
    {
        $p = ProviderSource::query()->create([
            'key' => 'jet2',
            'name' => 'Jet2 Holidays',
            'base_url' => 'https://www.jet2holidays.com',
            'status' => ProviderSourceStatus::Active,
        ]);
        $ids = ['39', '1679'];
        ProviderDestination::registerProviderIdsWithoutNames($p, $ids);
        $this->assertDatabaseCount('provider_destinations', 2);
        ProviderDestination::registerProviderIdsWithoutNames($p, $ids);
        $this->assertDatabaseCount('provider_destinations', 2);
        $this->assertSame(2, ProviderDestination::query()->where('provider_source_id', $p->id)->count());
    }

    public function test_same_area_id_may_exist_for_different_provider(): void
    {
        $jet2 = ProviderSource::query()->create([
            'key' => 'jet2',
            'name' => 'Jet2 Holidays',
            'base_url' => 'https://www.jet2holidays.com',
            'status' => ProviderSourceStatus::Active,
        ]);
        $tui = ProviderSource::query()->create([
            'key' => 'tui',
            'name' => 'TUI',
            'base_url' => 'https://www.tui.co.uk',
            'status' => ProviderSourceStatus::Active,
        ]);
        ProviderDestination::query()->create(['provider_source_id' => $jet2->id, 'area_id' => '1']);
        ProviderDestination::query()->create(['provider_source_id' => $tui->id, 'area_id' => '1']);
        $this->assertDatabaseCount('provider_destinations', 2);
    }

    public function test_unique_enforced_on_duplicate_area_for_same_provider(): void
    {
        $p = ProviderSource::query()->create([
            'key' => 'jet2',
            'name' => 'Jet2 Holidays',
            'base_url' => 'https://www.jet2holidays.com',
            'status' => ProviderSourceStatus::Active,
        ]);
        ProviderDestination::query()->create([
            'provider_source_id' => $p->id,
            'area_id' => '1',
        ]);
        $this->expectException(QueryException::class);
        ProviderDestination::query()->create([
            'provider_source_id' => $p->id,
            'area_id' => '1',
        ]);
    }
}
