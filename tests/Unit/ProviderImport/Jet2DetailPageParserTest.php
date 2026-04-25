<?php

namespace Tests\Unit\ProviderImport;

use App\Services\ProviderImport\DetailParsers\Jet2DetailPageParser;
use Tests\TestCase;

class Jet2DetailPageParserTest extends TestCase
{
    public function test_it_extracts_expected_fields_from_real_iberostar_fixture(): void
    {
        $parser = new Jet2DetailPageParser;
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
        $this->assertSame('08:05-12:10', $packages[0]['outbound_flight_time_text']);
        $this->assertSame('13:20-15:35', $packages[0]['inbound_flight_time_text']);
    }

    public function test_it_extracts_expected_fields_from_real_prinsotel_fixture(): void
    {
        $parser = new Jet2DetailPageParser;
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
