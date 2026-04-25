<?php

namespace Tests\Unit\ProviderImport;

use App\Models\Airport;
use App\Services\ProviderImport\DetailParsers\Jet2DetailPageParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Jet2DetailPageParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_expected_fields_from_real_iberostar_fixture(): void
    {
        Airport::query()->create([
            'iata_code' => 'AGP',
            'name' => 'Malaga',
            'latitude' => 36.6749,
            'longitude' => -4.4991,
        ]);

        $parser = app(Jet2DetailPageParser::class);
        $candidate = $this->candidate();
        $html = $this->fixture('jet2_detail_iberostar_waves_malaga_playa.html');
        $parsed = $parser->parse($candidate, $html);
        $hotel = $parsed['hotel'];
        $packages = $parsed['packages'];

        $this->assertSame(413, $hotel['rooms_count']);
        $this->assertSame(3, $hotel['blocks_count']);
        $this->assertSame(7, $hotel['floors_count']);
        $this->assertSame(1, $hotel['restaurants_count']);
        $this->assertSame(4, $hotel['bars_count']);
        $this->assertSame(2, $hotel['pools_count']);
        $this->assertSame(2, $hotel['sports_leisure_count']);
        $this->assertSame(48.19, $hotel['distance_to_airport_km']);
        $this->assertSame('Torrox', $hotel['resort_name']);

        $this->assertNotEmpty($packages);
        $this->assertSame('All Inclusive', $packages[0]['board_recommended']);
        $this->assertSame(2.5, $packages[0]['local_beer_price']);
        $this->assertSame(42.1, $packages[0]['three_course_meal_for_two_price']);
        $this->assertSame('07:25-11:25', $packages[0]['outbound_flight_time_text']);
        $this->assertSame('12:20-14:25', $packages[0]['inbound_flight_time_text']);

        $this->assertIsArray($hotel['images'] ?? null);
        $this->assertCount(19, $hotel['images']);
        $this->assertSame(
            'https://media.jet2.com/is/image/jet2/AGP_70178_Iberostar_Malaga_Playa_0223_08',
            $hotel['images'][0]['url']
        );
        $this->assertSame('jet2_json_ld', $hotel['images'][0]['source']);
        $this->assertSame(0, $hotel['images'][0]['position']);
    }

    public function test_it_extracts_expected_fields_from_real_prinsotel_fixture(): void
    {
        Airport::query()->create([
            'iata_code' => 'AGP',
            'name' => 'Malaga',
            'latitude' => 36.6749,
            'longitude' => -4.4991,
        ]);

        $parser = app(Jet2DetailPageParser::class);
        $candidate = $this->candidate();
        $html = $this->fixture('jet2_detail_prinsotel_alba.html');
        $parsed = $parser->parse($candidate, $html);
        $hotel = $parsed['hotel'];
        $packages = $parsed['packages'];

        $this->assertSame(226, $hotel['rooms_count']);
        $this->assertSame(10, $hotel['blocks_count']);
        $this->assertSame(2, $hotel['floors_count']);
        $this->assertSame(3, $hotel['restaurants_count']);
        $this->assertSame(3, $hotel['bars_count']);
        $this->assertSame(3, $hotel['sports_leisure_count']);
        $this->assertSame(100, $hotel['distance_to_beach_meters']);
        $this->assertSame(450, $hotel['distance_to_centre_meters']);
        $this->assertFalse($hotel['has_lift']);
        $this->assertSame('no_lift', $hotel['accessibility_issues']);

        $this->assertNotEmpty($packages);
        $this->assertSame(3.4, $packages[0]['local_beer_price']);
        $this->assertSame(48.0, $packages[0]['three_course_meal_for_two_price']);
        $this->assertSame('08:05-12:10', $packages[0]['outbound_flight_time_text']);
        $this->assertSame('13:20-15:35', $packages[0]['inbound_flight_time_text']);

        $this->assertIsArray($hotel['images'] ?? null);
        $this->assertCount(46, $hotel['images']);
        $this->assertSame(
            'https://media.jet2.com/is/image/jet2/PMI_69571_Prinsotel_Alba_0718_02',
            $hotel['images'][0]['url']
        );
        $this->assertSame('jet2_json_ld', $hotel['images'][0]['source']);
    }

    public function test_it_uses_provider_fallback_distance_when_airport_is_missing(): void
    {
        $parser = app(Jet2DetailPageParser::class);
        $candidate = $this->candidate();
        $candidate['airport_code'] = 'ZZZ';
        $candidate['raw_attributes']['distance_to_airport_km'] = 77.7;

        $parsed = $parser->parse($candidate, '<html></html>');

        $this->assertSame(77.7, $parsed['hotel']['distance_to_airport_km']);
    }

    public function test_it_uses_provider_fallback_distance_when_hotel_coordinates_are_missing(): void
    {
        Airport::query()->create([
            'iata_code' => 'AGP',
            'name' => 'Malaga',
            'latitude' => 36.6749,
            'longitude' => -4.4991,
        ]);

        $parser = app(Jet2DetailPageParser::class);
        $candidate = $this->candidate();
        $candidate['raw_attributes']['property']['mapLocation'] = [];
        $candidate['raw_attributes']['distance_to_airport_km'] = 88.8;

        $parsed = $parser->parse($candidate, '<html></html>');

        $this->assertSame(88.8, $parsed['hotel']['distance_to_airport_km']);
    }

    public function test_it_extracts_phase_two_csv_enrichment_fields(): void
    {
        $parser = app(Jet2DetailPageParser::class);
        $candidate = $this->candidate();
        $html = <<<'HTML'
<html>
<head>
  <meta property="og:description" content="Family beachfront hotel with luxury touches and a relaxing atmosphere.">
</head>
<body>
  <span class="overview__list-text">Indoor play area</span>
  <span class="overview__list-text">500m from shops</span>
  <span class="overview__list-text">1.2km from cafes and bars</span>
  <p>Average flight time 4.5 hours.</p>
  <p>Coach transfer available, transfer time around 50 minutes.</p>
  <p>Gym, spa, evening entertainment, kids disco, adults only area nearby.</p>
  <p>Located on the promenade close to the harbour and marina.</p>
  <p>Kids club ages 4-12.</p>
  <p>No lift and around 12 steps to some rooms.</p>
  <div data-modeldata='{"roomFacilities":["Air conditioning","Cot available on request"]}'></div>
</body>
</html>
HTML;

        $parsed = $parser->parse($candidate, $html);
        $hotel = $parsed['hotel'];
        $package = $parsed['packages'][0] ?? [];

        $this->assertTrue($hotel['play_area']);
        $this->assertTrue($hotel['gym']);
        $this->assertTrue($hotel['spa']);
        $this->assertTrue($hotel['evening_entertainment']);
        $this->assertTrue($hotel['kids_disco']);
        $this->assertTrue($hotel['adults_only_area']);
        $this->assertTrue($hotel['promenade']);
        $this->assertTrue($hotel['harbour']);
        $this->assertTrue($hotel['near_shops']);
        $this->assertSame(500, $hotel['distance_to_shops_meters']);
        $this->assertTrue($hotel['cafes_bars']);
        $this->assertSame(1200, $hotel['distance_to_cafes_bars_meters']);
        $this->assertSame(4, $hotel['kids_club_age_min']);
        $this->assertSame(12, $hotel['steps_count']);
        $this->assertSame('No lift; Steps present (12 steps)', $hotel['accessibility_notes']);
        $this->assertTrue($hotel['cots_available']);
        $this->assertStringContainsString('Family beachfront hotel', $hotel['introduction_snippet']);
        $this->assertStringContainsString('family', $hotel['style_keywords']);

        $this->assertSame('coach', $package['transfer_type']);
        $this->assertSame(4.5, $package['flight_time_hours_est']);
    }

    public function test_it_prefers_more_inclusive_board_recommendation(): void
    {
        $parser = app(Jet2DetailPageParser::class);
        $candidate = $this->candidate();
        $candidate['raw_attributes']['accommodation_options'] = [
            [
                'board' => 'Bed & Breakfast',
                'boardId' => '2',
                'priceOptions' => [['totalPrice' => 5000]],
            ],
            [
                'board' => 'Half Board',
                'boardId' => '3',
                'priceOptions' => [['totalPrice' => 5200]],
            ],
        ];

        $parsed = $parser->parse($candidate, '<html><body></body></html>');
        $package = $parsed['packages'][0] ?? [];

        $this->assertSame('Half Board', $package['board_recommended'] ?? null);
    }

    public function test_it_normalises_flight_time_estimate_similar_to_reference_outputs(): void
    {
        $parser = app(Jet2DetailPageParser::class);
        $candidate = $this->candidate();
        $candidate['raw_attributes']['outbound_flight'] = '08:00-11:45';
        $candidate['raw_attributes']['inbound_flight'] = '12:15-14:05';

        $parsed = $parser->parse($candidate, '<html><body></body></html>');
        $package = $parsed['packages'][0] ?? [];
        $this->assertSame(2.5, $package['flight_time_hours_est'] ?? null);

        $candidate['raw_attributes']['outbound_flight'] = '07:45-13:15';
        $candidate['raw_attributes']['inbound_flight'] = '13:35-15:20';
        $parsedLong = $parser->parse($candidate, '<html><body></body></html>');
        $packageLong = $parsedLong['packages'][0] ?? [];
        $this->assertSame(4.0, $packageLong['flight_time_hours_est'] ?? null);

        $candidate['raw_attributes']['outbound_flight'] = 'Sat 25 Jul 2026 08:00 – Sat 25 Jul 2026 11:45';
        $candidate['raw_attributes']['inbound_flight'] = 'Tue 04 Aug 2026 12:15 – Tue 04 Aug 2026 14:05';
        $parsedDated = $parser->parse($candidate, '<html><body></body></html>');
        $packageDated = $parsedDated['packages'][0] ?? [];
        $this->assertSame(2.5, $packageDated['flight_time_hours_est'] ?? null);
    }

    public function test_it_prefers_rich_raw_flight_window_over_time_only_detail_modal(): void
    {
        $parser = app(Jet2DetailPageParser::class);
        $candidate = $this->candidate();
        $candidate['raw_attributes']['outbound_flight'] = 'Sat 25 Jul 2026 08:05 – Sat 25 Jul 2026 12:10';
        $candidate['raw_attributes']['inbound_flight'] = 'Tue 04 Aug 2026 13:20 – Tue 04 Aug 2026 15:35';

        $html = <<<'HTML'
<div data-flight-information-modal-model="{&quot;outboundFlight&quot;:{&quot;departureTime&quot;:&quot;08:05&quot;,&quot;arrivalTime&quot;:&quot;12:10&quot;},&quot;inboundFlight&quot;:{&quot;departureTime&quot;:&quot;13:20&quot;,&quot;arrivalTime&quot;:&quot;15:35&quot;}}"></div>
HTML;

        $parsed = $parser->parse($candidate, $html);
        $package = $parsed['packages'][0] ?? [];

        $this->assertSame('Sat 25 Jul 2026 08:05 – Sat 25 Jul 2026 12:10', $package['outbound_flight_time_text'] ?? null);
        $this->assertSame('Tue 04 Aug 2026 13:20 – Tue 04 Aug 2026 15:35', $package['inbound_flight_time_text'] ?? null);
    }

    private function fixture(string $name): string
    {
        $path = base_path('tests/Fixtures/'.$name);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * @return array<string,mixed>
     */
    private function candidate(): array
    {
        return [
            'airport_code' => 'AGP',
            'raw_attributes' => [
                'property' => [
                    'tripAdvisorRating' => 4.4,
                    'tripAdvisorReviewCount' => 3496,
                    'mapLocation' => ['latitude' => 36.7262, 'longitude' => -3.9624],
                    'rating' => 4,
                    'features' => [],
                    'keySellingPoints' => [],
                ],
                'outbound_flight' => '08:05-12:10',
                'inbound_flight' => '13:20-15:35',
                'accommodation_options' => [
                    [
                        'board' => 'All Inclusive',
                        'boardId' => 'AI',
                        'priceOptions' => [
                            ['totalPrice' => 9390, 'pricePerPerson' => 1878],
                        ],
                    ],
                ],
            ],
        ];
    }
}
