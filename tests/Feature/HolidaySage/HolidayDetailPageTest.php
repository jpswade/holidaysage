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

class HolidayDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_show_returns_404_for_unknown_slug(): void
    {
        $this->get(route('holidays.show', ['slug' => 'definitely-not-a-real-hotel']))->assertNotFound();
    }

    public function test_holiday_show_renders_unified_property_across_providers(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        $jet2 = ProviderSource::query()->where('key', 'jet2')->firstOrFail();
        $tui = ProviderSource::query()->where('key', 'tui')->firstOrFail();

        $search = $this->makeSearch();
        $run = $this->makeCompletedRun($search);

        $canonicalSlug = 'amazing-azure-resort';
        $hotelJet2 = $this->makeHotelForProvider($jet2, 'Amazing Azure Resort (Jet2)', $canonicalSlug);
        $hotelTui = $this->makeHotelForProvider($tui, 'Amazing Azure Resort (TUI)', $canonicalSlug);

        $packageJet2 = $this->makePackage($jet2, $hotelJet2, '1499.00', 'sig-j2');
        $packageTui = $this->makePackage($tui, $hotelTui, '1599.00', 'sig-tu');

        $this->makeScoredOption($search, $run, $packageJet2, 9.0);
        $this->makeScoredOption($search, $run, $packageTui, 8.5);

        $response = $this->get(route('holidays.show', ['slug' => $canonicalSlug]));
        $response->assertOk();
        $response->assertSeeText('Amazing Azure Resort'); // lead hotel name
        $response->assertSeeText('Jet2'); // provider grouping
        $response->assertSeeText('TUI'); // provider grouping
        $response->assertSeeText('Available across 2 providers');
    }

    public function test_searches_deals_show_redirects_to_canonical_holidays_show(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        $provider = ProviderSource::query()->where('key', 'jet2')->firstOrFail();
        $search = $this->makeSearch();
        $run = $this->makeCompletedRun($search);

        $hotel = $this->makeHotelForProvider($provider, 'Lovely Hotel', 'lovely-hotel');
        $package = $this->makePackage($provider, $hotel, '1200.00', 'sig-deal');
        $option = $this->makeScoredOption($search, $run, $package, 8.4);

        $response = $this->get(route('searches.deals.show', [
            'search' => $search->id,
            'scoredOption' => $option->id,
        ]));

        $response->assertRedirect(route('holidays.show', [
            'slug' => 'lovely-hotel',
            'p' => $option->id,
        ]));
    }

    private function makeSearch(): SavedHolidaySearch
    {
        return SavedHolidaySearch::query()->create([
            'name' => 'Test search',
            'slug' => 'test-search-'.Str::random(4),
            'departure_airport_code' => 'MAN',
            'duration_min_nights' => 7,
            'duration_max_nights' => 7,
            'adults' => 2,
            'children' => 0,
            'status' => 'active',
        ]);
    }

    private function makeCompletedRun(SavedHolidaySearch $search): SavedHolidaySearchRun
    {
        return SavedHolidaySearchRun::query()->create([
            'saved_holiday_search_id' => $search->id,
            'run_type' => SavedHolidaySearchRunType::Manual,
            'status' => SavedHolidaySearchRunStatus::Completed,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(5),
            'imported_holiday_package_ids' => [],
        ]);
    }

    private function makeHotelForProvider(ProviderSource $provider, string $name, string $canonicalSlug): Hotel
    {
        return Hotel::query()->create([
            'provider_source_id' => $provider->id,
            'provider_hotel_id' => 'H-'.Str::random(6),
            'hotel_identity_hash' => hash('sha256', $provider->id.'|'.$name),
            'hotel_name' => $name,
            'hotel_slug' => Str::slug($name),
            'canonical_property_slug' => $canonicalSlug,
            'destination_name' => 'Crete',
            'destination_country' => 'Greece',
            'review_score' => 4.5,
            'review_count' => 200,
            'distance_to_beach_meters' => 200,
        ]);
    }

    private function makePackage(ProviderSource $provider, Hotel $hotel, string $price, string $sigSeed): HolidayPackage
    {
        return HolidayPackage::query()->create([
            'provider_source_id' => $provider->id,
            'hotel_id' => $hotel->id,
            'provider_option_id' => 'OPT-'.Str::random(6),
            'provider_url' => 'https://example.com/'.$sigSeed,
            'airport_code' => 'MAN',
            'departure_date' => '2026-07-15',
            'return_date' => '2026-07-22',
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'board_type' => 'all_inclusive',
            'price_total' => $price,
            'price_per_person' => round((float) $price / 2, 2),
            'currency' => 'GBP',
            'flight_outbound_duration_minutes' => 240,
            'flight_inbound_duration_minutes' => 235,
            'transfer_minutes' => 30,
            'signature_hash' => hash('sha256', $sigSeed),
        ]);
    }

    private function makeScoredOption(SavedHolidaySearch $search, SavedHolidaySearchRun $run, HolidayPackage $package, float $score): ScoredHolidayOption
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
            'recommendation_summary' => 'Solid option.',
            'recommendation_reasons' => ['Short transfer', 'Strong reviews'],
            'rank_position' => 1,
        ]);
    }
}
