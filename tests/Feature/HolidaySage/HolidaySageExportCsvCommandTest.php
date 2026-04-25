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

class HolidaySageExportCsvCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_csv_with_expected_columns_and_values(): void
    {
        $provider = ProviderSource::query()->create([
            'key' => 'jet2',
            'name' => 'Jet2 Holidays',
            'base_url' => 'https://www.jet2holidays.com',
            'status' => ProviderSourceStatus::Active,
        ]);
        $hotel = Hotel::query()->create([
            'provider_source_id' => $provider->id,
            'provider_hotel_id' => 'test-hotel-1',
            'hotel_identity_hash' => hash('sha256', 'test-hotel-1'),
            'hotel_name' => 'Test Hotel',
            'hotel_slug' => 'test-hotel',
            'destination_name' => 'Majorca',
            'destination_country' => 'Spain',
            'has_lift' => true,
            'rooms_count' => 200,
        ]);
        $package = HolidayPackage::query()->create([
            'provider_source_id' => $provider->id,
            'hotel_id' => $hotel->id,
            'provider_option_id' => 'opt-1',
            'provider_url' => '/beach/balearics/majorca/test-hotel',
            'airport_code' => 'PMI',
            'departure_date' => '2026-07-25',
            'return_date' => '2026-08-04',
            'nights' => 10,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'board_type' => 'AI',
            'board_recommended' => 'All Inclusive',
            'price_total' => 3500,
            'price_per_person' => 875,
            'currency' => 'GBP',
            'signature_hash' => hash('sha256', 'opt-1'),
        ]);
        $search = SavedHolidaySearch::query()->create([
            'name' => 'Test Search',
            'slug' => 'test-search',
            'provider_import_url' => 'https://www.jet2holidays.com/search/results',
            'departure_airport_code' => 'MAN',
            'duration_min_nights' => 7,
            'duration_max_nights' => 14,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'status' => 'active',
        ]);
        $run = SavedHolidaySearchRun::query()->create([
            'saved_holiday_search_id' => $search->id,
            'run_type' => 'import',
            'status' => 'completed',
        ]);
        ScoredHolidayOption::query()->create([
            'saved_holiday_search_id' => $search->id,
            'saved_holiday_search_run_id' => $run->id,
            'holiday_package_id' => $package->id,
            'overall_score' => 7.5,
            'travel_score' => 8.0,
            'value_score' => 7.0,
            'family_fit_score' => 8.5,
            'location_score' => 6.0,
            'board_score' => 9.0,
            'price_score' => 7.0,
            'is_disqualified' => false,
            'disqualification_reasons' => [],
        ]);

        $path = storage_path('app/exports/test-output.csv');
        $this->artisan('holidaysage:export-csv', ['--path' => $path])
            ->assertExitCode(0);

        $this->assertFileExists($path);
        $csv = file($path, FILE_IGNORE_NEW_LINES);
        $this->assertNotFalse($csv);
        $this->assertGreaterThanOrEqual(2, count($csv));
        $this->assertStringContainsString('hotel_name', (string) $csv[0]);
        $this->assertStringContainsString('Test Hotel', (string) $csv[1]);
        $this->assertStringContainsString('All Inclusive', (string) $csv[1]);
    }

    public function test_it_filters_to_specific_run_when_run_id_is_provided(): void
    {
        $provider = ProviderSource::query()->create([
            'key' => 'jet2',
            'name' => 'Jet2 Holidays',
            'base_url' => 'https://www.jet2holidays.com',
            'status' => ProviderSourceStatus::Active,
        ]);
        $hotel = Hotel::query()->create([
            'provider_source_id' => $provider->id,
            'provider_hotel_id' => 'test-hotel-2',
            'hotel_identity_hash' => hash('sha256', 'test-hotel-2'),
            'hotel_name' => 'Scoped Hotel',
            'hotel_slug' => 'scoped-hotel',
            'destination_name' => 'Majorca',
            'destination_country' => 'Spain',
        ]);
        $packageInRun = HolidayPackage::query()->create([
            'provider_source_id' => $provider->id,
            'hotel_id' => $hotel->id,
            'provider_option_id' => 'opt-in',
            'provider_url' => '/beach/balearics/majorca/scoped-hotel',
            'airport_code' => 'PMI',
            'departure_date' => '2026-07-25',
            'return_date' => '2026-08-04',
            'nights' => 10,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'board_type' => 'AI',
            'price_total' => 3000,
            'currency' => 'GBP',
            'signature_hash' => hash('sha256', 'opt-in'),
        ]);
        $packageOutOfRun = HolidayPackage::query()->create([
            'provider_source_id' => $provider->id,
            'hotel_id' => $hotel->id,
            'provider_option_id' => 'opt-out',
            'provider_url' => '/beach/balearics/majorca/scoped-hotel',
            'airport_code' => 'PMI',
            'departure_date' => '2026-07-25',
            'return_date' => '2026-08-04',
            'nights' => 10,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'board_type' => 'BB',
            'price_total' => 3100,
            'currency' => 'GBP',
            'signature_hash' => hash('sha256', 'opt-out'),
        ]);
        $search = SavedHolidaySearch::query()->create([
            'name' => 'Scoped Search',
            'slug' => 'scoped-search',
            'provider_import_url' => 'https://www.jet2holidays.com/search/results',
            'departure_airport_code' => 'MAN',
            'duration_min_nights' => 7,
            'duration_max_nights' => 14,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'status' => 'active',
        ]);
        $targetRun = SavedHolidaySearchRun::query()->create([
            'saved_holiday_search_id' => $search->id,
            'run_type' => 'import',
            'status' => 'completed',
            'imported_holiday_package_ids' => [$packageInRun->id],
        ]);
        $otherRun = SavedHolidaySearchRun::query()->create([
            'saved_holiday_search_id' => $search->id,
            'run_type' => 'import',
            'status' => 'completed',
            'imported_holiday_package_ids' => [$packageOutOfRun->id],
        ]);
        ScoredHolidayOption::query()->create([
            'saved_holiday_search_id' => $search->id,
            'saved_holiday_search_run_id' => $targetRun->id,
            'holiday_package_id' => $packageInRun->id,
            'overall_score' => 8.0,
            'travel_score' => 8.0,
            'value_score' => 8.0,
            'family_fit_score' => 8.0,
            'location_score' => 8.0,
            'board_score' => 8.0,
            'price_score' => 8.0,
            'is_disqualified' => false,
            'disqualification_reasons' => [],
        ]);
        ScoredHolidayOption::query()->create([
            'saved_holiday_search_id' => $search->id,
            'saved_holiday_search_run_id' => $otherRun->id,
            'holiday_package_id' => $packageOutOfRun->id,
            'overall_score' => 6.0,
            'travel_score' => 6.0,
            'value_score' => 6.0,
            'family_fit_score' => 6.0,
            'location_score' => 6.0,
            'board_score' => 6.0,
            'price_score' => 6.0,
            'is_disqualified' => false,
            'disqualification_reasons' => [],
        ]);

        $path = storage_path('app/exports/test-output-scoped.csv');
        $this->artisan('holidaysage:export-csv', ['--path' => $path, '--run-id' => (string) $targetRun->id])
            ->assertExitCode(0);

        $this->assertFileExists($path);
        $csv = file($path, FILE_IGNORE_NEW_LINES);
        $this->assertNotFalse($csv);
        $this->assertCount(2, $csv); // header + exactly one scoped row
        $this->assertStringContainsString('Scoped Hotel', (string) $csv[1]);
        $this->assertStringContainsString(',3000,', (string) $csv[1]);
        $this->assertStringNotContainsString(',3100,', (string) $csv[1]);
    }
}
