<?php

namespace Tests\Feature\HolidaySage;

use App\Enums\SavedHolidaySearchRunStatus;
use App\Enums\SavedHolidaySearchRunType;
use App\Models\HolidayPackage;
use App\Models\Hotel;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Models\ScoredHolidayOption;
use Database\Seeders\ProviderSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SavedSearchDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_search_detail_renders_with_diff_sections(): void
    {
        [$search, $latestRun, $previousRun, $newPackage, $improvedPackage] = $this->seedSearchWithTwoRuns();

        $response = $this->get(route('saved-searches.show', $search));
        $response->assertOk();
        $response->assertSeeText('Saved search');
        $response->assertSeeText($search->name);
        $response->assertSeeText('Current best matches');
        $response->assertSeeText('New matches since the previous refresh');
        $response->assertSeeText('Improved matches');
        $response->assertSeeText('Brand new resort');
        $response->assertSeeText('Improved Resort');
        $response->assertDontSeeText('tracked');
        $response->assertDontSeeText('tracking');
    }

    public function test_shared_saved_search_is_404_when_disabled_and_200_when_enabled(): void
    {
        [$search] = $this->seedSearchWithTwoRuns();
        $token = $search->ensureShareToken();

        $this->get(route('saved-searches.shared.show', ['token' => $token]))->assertNotFound();

        $search->enableSharing();

        $response = $this->get(route('saved-searches.shared.show', ['token' => $token]));
        $response->assertOk();
        $response->assertSeeText('Shared saved search');
        $response->assertSeeText($search->name);
    }

    /**
     * @return array{0: SavedHolidaySearch, 1: SavedHolidaySearchRun, 2: SavedHolidaySearchRun, 3: HolidayPackage, 4: HolidayPackage}
     */
    private function seedSearchWithTwoRuns(): array
    {
        $this->seed(ProviderSourceSeeder::class);
        $provider = ProviderSource::query()->where('key', 'jet2')->firstOrFail();

        $search = SavedHolidaySearch::query()->create([
            'name' => 'Crete summer 2026',
            'slug' => 'crete-summer-2026-'.Str::random(4),
            'departure_airport_code' => 'MAN',
            'duration_min_nights' => 7,
            'duration_max_nights' => 7,
            'adults' => 2,
            'children' => 1,
            'status' => 'active',
            'last_scored_at' => now(),
        ]);

        $previousRun = SavedHolidaySearchRun::query()->create([
            'saved_holiday_search_id' => $search->id,
            'run_type' => SavedHolidaySearchRunType::Manual,
            'status' => SavedHolidaySearchRunStatus::Completed,
            'started_at' => now()->subDay()->subMinutes(5),
            'finished_at' => now()->subDay(),
            'imported_holiday_package_ids' => [],
        ]);

        $latestRun = SavedHolidaySearchRun::query()->create([
            'saved_holiday_search_id' => $search->id,
            'run_type' => SavedHolidaySearchRunType::Manual,
            'status' => SavedHolidaySearchRunStatus::Completed,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(2),
            'imported_holiday_package_ids' => [],
        ]);

        // Package present in both runs but improved score in the latest run.
        $improvedHotel = $this->makeHotel($provider, 'Improved Resort', 'improved-resort');
        $improvedPackage = $this->makePackage($provider, $improvedHotel, '1499', 'sig-improved');
        $this->makeScored($search, $previousRun, $improvedPackage, 8.0);
        $this->makeScored($search, $latestRun, $improvedPackage, 9.0);

        // Package only present in the latest run.
        $newHotel = $this->makeHotel($provider, 'Brand new resort', 'brand-new-resort');
        $newPackage = $this->makePackage($provider, $newHotel, '1399', 'sig-new');
        $this->makeScored($search, $latestRun, $newPackage, 8.6);

        return [$search, $latestRun, $previousRun, $newPackage, $improvedPackage];
    }

    private function makeHotel(ProviderSource $provider, string $name, string $slug): Hotel
    {
        return Hotel::query()->create([
            'provider_source_id' => $provider->id,
            'provider_hotel_id' => 'H-'.Str::random(6),
            'hotel_identity_hash' => hash('sha256', $provider->id.'|'.$name),
            'hotel_name' => $name,
            'hotel_slug' => $slug,
            'canonical_property_slug' => $slug,
            'destination_name' => 'Crete',
            'destination_country' => 'Greece',
            'review_score' => 4.5,
            'review_count' => 200,
        ]);
    }

    private function makePackage(ProviderSource $provider, Hotel $hotel, string $price, string $sig): HolidayPackage
    {
        return HolidayPackage::query()->create([
            'provider_source_id' => $provider->id,
            'hotel_id' => $hotel->id,
            'provider_option_id' => 'OPT-'.Str::random(6),
            'provider_url' => 'https://example.com/'.$sig,
            'airport_code' => 'MAN',
            'departure_date' => '2026-07-15',
            'return_date' => '2026-07-22',
            'nights' => 7,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'board_type' => 'all_inclusive',
            'price_total' => $price,
            'price_per_person' => round((float) $price / 3, 2),
            'currency' => 'GBP',
            'flight_outbound_duration_minutes' => 220,
            'flight_inbound_duration_minutes' => 220,
            'transfer_minutes' => 25,
            'signature_hash' => hash('sha256', $sig),
        ]);
    }

    private function makeScored(SavedHolidaySearch $search, SavedHolidaySearchRun $run, HolidayPackage $package, float $score): ScoredHolidayOption
    {
        return ScoredHolidayOption::query()->create([
            'saved_holiday_search_id' => $search->id,
            'saved_holiday_search_run_id' => $run->id,
            'holiday_package_id' => $package->id,
            'overall_score' => $score,
            'travel_score' => $score - 0.1,
            'value_score' => $score - 0.2,
            'family_fit_score' => $score - 0.3,
            'location_score' => $score - 0.1,
            'board_score' => $score - 0.2,
            'price_score' => $score - 0.1,
            'is_disqualified' => false,
            'warning_flags' => [],
            'recommendation_summary' => 'Test summary.',
            'recommendation_reasons' => [],
            'rank_position' => 1,
        ]);
    }
}
