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
use Illuminate\Support\Facades\Config;
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
        $response->assertSeeText('Browse holidays');
        $response->assertSee(route('holidays.index'));
        $response->assertSee(route('searches.create'));
        $response->assertSee(route('searches.index'));
    }

    public function test_browse_holidays_page_renders(): void
    {
        $response = $this->get(route('holidays.index'));

        $response->assertOk();
        $response->assertSeeText('Browse holidays your way');
        $response->assertSeeText('Popular destinations');
        $response->assertSee(route('searches.create'));
        $response->assertSee(route('searches.index'));
    }

    public function test_create_search_prefills_from_browse_query_string(): void
    {
        $response = $this->get(route('searches.create', [
            'departure_airport_code' => 'lgw-!',
            'travel_start_date' => '2026-06-01',
            'travel_end_date' => 'not-a-date',
            'feature_preferences' => ['spa_wellness', 'invalid_sludge'],
            'destination_preferences' => ['Crete'],
            'adults' => 3,
            'children' => 1,
        ]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*\bname="departure_airport_code"[^>]*\bvalue="LGW"/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*\bname="travel_start_date"[^>]*\bvalue="2026-06-01"/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*\bname="adults"[^>]*\bvalue="3"/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*\bname="children"[^>]*\bvalue="1"/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*\btype="checkbox"[^>]*\bname="feature_preferences\[\]"[^>]*\bvalue="spa_wellness"[^>]*\bchecked/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*\btype="hidden"[^>]*\bname="destination_preferences\[\]"[^>]*\bvalue="Crete"/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input\b[^>]*\bname="feature_preferences\[\]"[^>]*\bvalue="invalid_sludge"/',
            $html,
        );
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
        $show->assertSee('From MAN');
        $show->assertSee('Features');
        $show->assertSee('Kids club');
        $show->assertDontSee('Recent run activity');

        $results = $this->get(route('searches.results', $search));
        $results->assertRedirect(route('searches.show', $search));
    }

    public function test_saved_search_show_page_paginates_ranked_options(): void
    {
        Config::set('holidaysage.search_results_per_page', 2);
        $search = $this->seedSearchWithRankedOptionCount(5);

        $page1 = $this->get(route('searches.show', $search));
        $page1->assertOk();
        $page1->assertSee('Top 5 recommended options', false);
        $page1->assertSee('Showing 1–2 of 5', false);

        $page2 = $this->get(route('searches.show', ['search' => $search, 'page' => 2]));
        $page2->assertOk();
        $page2->assertSee('Showing 3–4 of 5', false);

        $page3 = $this->get(route('searches.show', ['search' => $search, 'page' => 3]));
        $page3->assertOk();
        $page3->assertSee('Showing 5–5 of 5', false);
    }

    public function test_refine_search_edit_and_update(): void
    {
        $search = $this->seedScoredSearch();

        $this->get(route('searches.edit', $search))->assertOk()->assertSee('Refine your search');

        $this->patch(route('searches.update', $search), [
            'name' => 'Updated holiday title',
            'departure_airport_code' => 'LGW',
            'travel_start_date' => null,
            'travel_end_date' => null,
            'travel_date_flexibility_days' => 0,
            'duration_min_nights' => 7,
            'duration_max_nights' => 7,
            'adults' => 2,
            'children' => 2,
            'infants' => 0,
            'budget_total' => 3500,
            'max_flight_minutes' => null,
            'max_transfer_minutes' => null,
            'provider_import_url' => null,
            'feature_preferences' => ['near_beach', 'kids_club'],
        ])->assertRedirect(route('searches.show', $search));

        $search->refresh();
        $this->assertSame('Updated holiday title', $search->name);
        $this->assertSame('LGW', $search->departure_airport_code);
        $this->assertContains('near_beach', $search->feature_preferences ?? []);
    }

    public function test_show_filters_results_by_keyword(): void
    {
        $search = $this->seedSearchWithRankedOptionCount(3);

        $this->get(route('searches.show', ['search' => $search, 'q' => 'Test Resort 2']))
            ->assertOk()
            ->assertSee('Test Resort 2', false)
            ->assertDontSee('Test Resort 1', false);
    }

    public function test_show_price_sort_orders_results(): void
    {
        $search = $this->seedSearchWithRankedOptionCount(3);

        $body = $this->get(route('searches.show', ['search' => $search, 'sort' => 'price_low']))
            ->assertOk()
            ->getContent();

        $posFirst = strpos($body, 'Test Resort 1');
        $posThird = strpos($body, 'Test Resort 3');
        $this->assertNotFalse($posFirst);
        $this->assertNotFalse($posThird);
        $this->assertLessThan($posThird, $posFirst);
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

    private function seedSearchWithRankedOptionCount(int $count): SavedHolidaySearch
    {
        $this->seed(ProviderSourceSeeder::class);
        $provider = ProviderSource::query()->where('key', 'jet2')->firstOrFail();

        $search = SavedHolidaySearch::query()->create([
            'name' => 'Paginated Search',
            'slug' => 'paginated-search-'.Str::random(4),
            'departure_airport_code' => 'MAN',
            'duration_min_nights' => 7,
            'duration_max_nights' => 7,
            'adults' => 2,
            'children' => 0,
            'status' => 'active',
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

        for ($i = 1; $i <= $count; $i++) {
            $hotel = Hotel::query()->create([
                'provider_source_id' => $provider->id,
                'provider_hotel_id' => 'H-PAG-'.$i,
                'hotel_identity_hash' => hash('sha256', 'hotel-pag-'.$i),
                'hotel_name' => 'Test Resort '.$i,
                'hotel_slug' => 'test-resort-'.$i,
                'resort_name' => 'Resort',
                'destination_name' => 'Majorca',
                'destination_country' => 'Spain',
                'has_kids_club' => false,
                'pools_count' => 1,
                'review_score' => 4.0,
                'review_count' => 10,
                'distance_to_beach_meters' => 500,
            ]);

            $package = HolidayPackage::query()->create([
                'provider_source_id' => $provider->id,
                'hotel_id' => $hotel->id,
                'provider_option_id' => 'OPT-PAG-'.$i,
                'provider_url' => 'https://www.jet2holidays.com/fake-option-'.$i,
                'airport_code' => 'MAN',
                'departure_date' => '2026-07-15',
                'return_date' => '2026-07-22',
                'nights' => 7,
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'board_type' => 'half_board',
                'price_total' => 1000 + $i,
                'price_per_person' => 500,
                'currency' => 'GBP',
                'flight_outbound_duration_minutes' => 200,
                'flight_inbound_duration_minutes' => 200,
                'transfer_minutes' => 30,
                'signature_hash' => hash('sha256', 'sig-pag-'.$i),
            ]);

            ScoredHolidayOption::query()->create([
                'saved_holiday_search_id' => $search->id,
                'saved_holiday_search_run_id' => $run->id,
                'holiday_package_id' => $package->id,
                'overall_score' => 9.0 - ($i * 0.01),
                'travel_score' => 8.0,
                'value_score' => 8.0,
                'family_fit_score' => 8.0,
                'location_score' => 8.0,
                'board_score' => 8.0,
                'price_score' => 8.0,
                'is_disqualified' => false,
                'warning_flags' => [],
                'recommendation_summary' => 'Summary '.$i,
                'recommendation_reasons' => [],
                'rank_position' => $i,
            ]);
        }

        return $search;
    }
}
