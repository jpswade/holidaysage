<?php

namespace Tests\Feature\HolidaySage;

use App\Enums\SavedHolidaySearchRunStatus;
use App\Enums\SavedHolidaySearchRunType;
use App\Jobs\RefreshSavedHolidaySearchJob;
use App\Models\HolidayPackage;
use App\Models\Hotel;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Models\ScoredHolidayOption;
use Database\Seeders\ProviderSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class FrontendCustomerPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_has_core_ctas(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSeeText('Find your perfect');
        $response->assertSeeText('holiday, effortlessly');
        $response->assertSee(route('searches.create'));
        $response->assertSee(route('searches.index'));
    }

    public function test_create_search_page_renders_and_allows_creation(): void
    {
        $response = $this->get(route('searches.create'));
        $response->assertOk()->assertSee('Tell us about your perfect holiday');

        $post = $this->post(route('searches.store'), [
            'name' => 'Summer Family Holiday',
            'departure_airport_code' => 'MAN',
            'travel_start_date' => '2026-07-15',
            'travel_end_date' => '2026-07-25',
            'duration_min_nights' => 7,
            'duration_max_nights' => 10,
            'adults' => 2,
            'children' => 2,
            'budget_total' => 3000,
            'feature_preferences' => ['family_friendly', 'near_beach'],
        ]);

        $post->assertRedirect();
        $this->assertDatabaseHas('saved_holiday_searches', [
            'name' => 'Summer Family Holiday',
            'departure_airport_code' => 'MAN',
        ]);
    }

    public function test_saved_searches_and_show_page_render_ranked_data(): void
    {
        $search = $this->seedScoredSearch();

        $index = $this->get(route('searches.index'));
        $index->assertOk()->assertSee($search->name);

        $show = $this->get(route('searches.show', $search));
        $show->assertOk();
        $show->assertSee('Top');
        $show->assertSee('recommended options');
        $show->assertSee('Kids club');
        $show->assertDontSee('Recent run activity');

        $results = $this->get(route('searches.results', $search));
        $results->assertRedirect(route('searches.show', $search));
    }

    public function test_import_endpoint_returns_prefill_for_supported_url(): void
    {
        $this->seed(ProviderSourceSeeder::class);

        $response = $this->postJson(route('searches.import'), [
            'url' => 'https://www.jet2holidays.com/search/results?depairportiata=MAN&adults=2&children=1&duration=10&outbounddate=2026-07-15',
        ]);

        $response->assertOk();
        $response->assertJsonPath('criteria.departure_airport_code', 'MAN');
        $response->assertJsonPath('criteria.duration_min_nights', 10);
        $payload = $response->json();
        $this->assertArrayHasKey('suggested_name', $payload);
        $this->assertIsString($payload['suggested_name']);
        $this->assertStringContainsString('MAN', $payload['suggested_name']);
        $this->assertStringContainsString('Jet2', $payload['suggested_name']);
    }

    public function test_refresh_endpoint_dispatches_manual_refresh_job(): void
    {
        Queue::fake();

        $search = SavedHolidaySearch::query()->create([
            'name' => 'Refreshable search',
            'slug' => 'refreshable-search',
            'departure_airport_code' => 'MAN',
            'duration_min_nights' => 7,
            'duration_max_nights' => 7,
            'adults' => 2,
            'children' => 0,
            'status' => 'active',
        ]);

        $response = $this->post(route('searches.refresh', $search));
        $response->assertRedirect(route('searches.show', $search));

        Queue::assertPushed(RefreshSavedHolidaySearchJob::class, function (RefreshSavedHolidaySearchJob $job) use ($search): bool {
            return $job->savedHolidaySearchId === $search->id
                && $job->runType === SavedHolidaySearchRunType::Manual->value;
        });
    }

    private function seedScoredSearch(): SavedHolidaySearch
    {
        $this->seed(ProviderSourceSeeder::class);
        $provider = ProviderSource::query()->where('key', 'jet2')->firstOrFail();

        $search = SavedHolidaySearch::query()->create([
            'name' => 'Summer Family Holiday',
            'slug' => 'summer-family-holiday-'.Str::random(4),
            'departure_airport_code' => 'MAN',
            'duration_min_nights' => 7,
            'duration_max_nights' => 7,
            'adults' => 2,
            'children' => 2,
            'status' => 'active',
            'feature_preferences' => ['family_friendly', 'kids_club', 'near_beach'],
            'last_scored_at' => now(),
        ]);

        $run = SavedHolidaySearchRun::query()->create([
            'saved_holiday_search_id' => $search->id,
            'run_type' => SavedHolidaySearchRunType::Manual,
            'status' => SavedHolidaySearchRunStatus::Completed,
            'started_at' => now()->subMinutes(8),
            'finished_at' => now()->subMinutes(4),
            'imported_holiday_package_ids' => [],
        ]);

        $hotel = Hotel::query()->create([
            'provider_source_id' => $provider->id,
            'provider_hotel_id' => 'H123',
            'hotel_identity_hash' => hash('sha256', 'hotel-h123'),
            'hotel_name' => 'Sunrise Family Resort',
            'hotel_slug' => 'sunrise-family-resort',
            'resort_name' => 'Alcudia',
            'destination_name' => 'Majorca',
            'destination_country' => 'Spain',
            'has_kids_club' => true,
            'pools_count' => 3,
            'review_score' => 4.6,
            'review_count' => 211,
            'distance_to_beach_meters' => 180,
        ]);

        $package = HolidayPackage::query()->create([
            'provider_source_id' => $provider->id,
            'hotel_id' => $hotel->id,
            'provider_option_id' => 'OPT-1',
            'provider_url' => 'https://www.jet2holidays.com/fake-option',
            'airport_code' => 'MAN',
            'departure_date' => '2026-07-15',
            'return_date' => '2026-07-22',
            'nights' => 7,
            'adults' => 2,
            'children' => 2,
            'infants' => 0,
            'board_type' => 'all_inclusive',
            'price_total' => 1847,
            'price_per_person' => 924,
            'currency' => 'GBP',
            'flight_outbound_duration_minutes' => 255,
            'flight_inbound_duration_minutes' => 240,
            'transfer_minutes' => 20,
            'signature_hash' => hash('sha256', 'sig-opt-1'),
        ]);

        ScoredHolidayOption::query()->create([
            'saved_holiday_search_id' => $search->id,
            'saved_holiday_search_run_id' => $run->id,
            'holiday_package_id' => $package->id,
            'overall_score' => 9.4,
            'travel_score' => 9.0,
            'value_score' => 9.2,
            'family_fit_score' => 9.6,
            'location_score' => 9.1,
            'board_score' => 8.8,
            'price_score' => 8.9,
            'is_disqualified' => false,
            'warning_flags' => ['Popular dates - limited availability'],
            'recommendation_summary' => 'Strong family fit with short transfer and excellent value.',
            'recommendation_reasons' => ['Kids club and family facilities', 'Short transfer', 'Strong review profile'],
            'rank_position' => 1,
        ]);

        return $search;
    }
}
