<?php

namespace Tests\Feature\HolidaySage;

use App\Enums\ProviderSourceStatus;
use App\Models\HolidayPackage;
use App\Models\Hotel;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Models\ScoredHolidayOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeachinFrozenParityExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_scoped_export_matches_frozen_beachin_subset_for_canonical_columns(): void
    {
        $expectedRows = $this->readFrozenFixture();

        $provider = ProviderSource::query()->create([
            'key' => 'jet2',
            'name' => 'Jet2 Holidays',
            'base_url' => 'https://www.jet2holidays.com',
            'status' => ProviderSourceStatus::Active,
        ]);
        $search = SavedHolidaySearch::query()->create([
            'name' => 'Frozen Parity Search',
            'slug' => 'frozen-parity-search',
            'provider_import_url' => 'https://www.jet2holidays.com/search/results',
            'departure_airport_code' => 'BHX',
            'duration_min_nights' => 10,
            'duration_max_nights' => 10,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'status' => 'active',
        ]);
        $run = SavedHolidaySearchRun::query()->create([
            'saved_holiday_search_id' => $search->id,
            'run_type' => 'import',
            'status' => 'completed',
            'imported_holiday_package_ids' => [],
        ]);

        $packageIds = [];
        foreach ($expectedRows as $index => $row) {
            $hotel = Hotel::query()->create([
                'provider_source_id' => $provider->id,
                'provider_hotel_id' => 'frozen-hotel-'.$index,
                'hotel_identity_hash' => hash('sha256', 'frozen-hotel-'.$index),
                'hotel_name' => $row['hotel_name'],
                'hotel_slug' => 'frozen-'.($index + 1),
                'resort_name' => $row['resort'],
                'destination_name' => 'Frozen Destination',
                'destination_country' => 'Frozen Country',
            ]);
            $package = HolidayPackage::query()->create([
                'provider_source_id' => $provider->id,
                'hotel_id' => $hotel->id,
                'provider_option_id' => 'frozen-opt-'.$index,
                'provider_url' => '/beach/frozen/'.$index,
                'airport_code' => 'AGP',
                'departure_date' => '2026-07-25',
                'return_date' => '2026-08-04',
                'nights' => 10,
                'adults' => 2,
                'children' => 1,
                'infants' => 0,
                'board_type' => $row['board_recommended'],
                'board_recommended' => $row['board_recommended'],
                'price_total' => 3000 + ($index * 100),
                'price_per_person' => 1000 + ($index * 10),
                'currency' => 'GBP',
                'outbound_flight_time_text' => $row['outbound_flight'],
                'inbound_flight_time_text' => $row['inbound_flight'],
                'flight_time_hours_est' => (float) $row['flight_time_hours_est'],
                'transfer_type' => $row['transfer_type'],
                'signature_hash' => hash('sha256', 'frozen-opt-'.$index),
            ]);
            $packageIds[] = $package->id;

            ScoredHolidayOption::query()->create([
                'saved_holiday_search_id' => $search->id,
                'saved_holiday_search_run_id' => $run->id,
                'holiday_package_id' => $package->id,
                'overall_score' => 8.0 - ($index * 0.1),
                'travel_score' => 8.0,
                'value_score' => 8.0,
                'family_fit_score' => 8.0,
                'location_score' => 8.0,
                'board_score' => 8.0,
                'price_score' => 8.0,
                'is_disqualified' => false,
                'disqualification_reasons' => [],
            ]);
        }
        $run->update(['imported_holiday_package_ids' => $packageIds]);

        $path = storage_path('app/exports/frozen-beachin-parity.csv');
        $this->artisan('holidaysage:export-csv', ['--path' => $path, '--run-id' => (string) $run->id])
            ->assertExitCode(0);

        $actualRows = $this->readCsvRows($path);
        $this->assertCount(count($expectedRows), $actualRows);

        $expectedByKey = [];
        foreach ($expectedRows as $row) {
            $expectedByKey[$this->rowKey($row)] = $row;
        }
        $actualByKey = [];
        foreach ($actualRows as $row) {
            $actualByKey[$this->rowKey($row)] = $row;
        }

        $this->assertSame(array_keys($expectedByKey), array_keys($actualByKey));
        foreach ($expectedByKey as $key => $expected) {
            $actual = $actualByKey[$key];
            foreach ([
                'board_recommended',
                'outbound_flight',
                'inbound_flight',
                'flight_time_hours_est',
                'transfer_type',
            ] as $column) {
                $this->assertSame($expected[$column], (string) ($actual[$column] ?? ''), $key.' '.$column);
            }
        }
    }

    /**
     * @return list<array<string,string>>
     */
    private function readFrozenFixture(): array
    {
        return $this->readCsvRows(base_path('tests/Fixtures/beachin_frozen_parity_subset.csv'));
    }

    /**
     * @return list<array<string,string>>
     */
    private function readCsvRows(string $path): array
    {
        $this->assertFileExists($path);
        $rows = [];
        $handle = fopen($path, 'rb');
        $this->assertNotFalse($handle);
        $headers = fgetcsv($handle);
        $this->assertIsArray($headers);
        while (($values = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $values);
            $this->assertNotFalse($row);
            $rows[] = array_map(static fn ($v) => is_string($v) ? trim($v) : (string) $v, $row);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string,string>  $row
     */
    private function rowKey(array $row): string
    {
        return strtolower(trim($row['hotel_name'])).'|'.strtolower(trim($row['resort']));
    }
}
