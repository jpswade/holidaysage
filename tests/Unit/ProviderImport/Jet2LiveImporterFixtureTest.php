<?php

namespace Tests\Unit\ProviderImport;

use App\Enums\ProviderSourceStatus;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Services\ProviderImport\Importers\Jet2LiveImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Jet2LiveImporterFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_real_jet2_smartsearch_fixture_payload(): void
    {
        $fixture = $this->readJet2ApiFixture();

        $provider = ProviderSource::query()->create([
            'key' => 'jet2',
            'name' => 'Jet2 Holidays',
            'base_url' => 'https://www.jet2holidays.com',
            'status' => ProviderSourceStatus::Active,
        ]);
        $search = SavedHolidaySearch::query()->create([
            'name' => 'Fixture Search',
            'slug' => 'fixture-search',
            'provider_import_url' => 'https://www.jet2holidays.com/search/results?airport=98_63_3&date=25-07-2026&duration=10&occupancy=r2c_r2c1_4&destination=39&sortorder=1&page=1&boardbasis=5_2_3',
            'departure_airport_code' => 'BHX',
            'travel_start_date' => '2026-07-25',
            'duration_min_nights' => 10,
            'duration_max_nights' => 10,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'status' => 'active',
        ]);

        $importer = new Jet2LiveImporter;
        $decoded = json_decode($fixture, true);
        $this->assertIsArray($decoded);
        $method = new \ReflectionMethod($importer, 'candidatesFromApiJson');
        $method->setAccessible(true);
        $candidates = $method->invoke($importer, $decoded, $search, $provider, $search->provider_import_url);

        $this->assertIsArray($candidates);
        $this->assertGreaterThan(0, count($candidates));
        $first = $candidates[0];

        $this->assertArrayHasKey('provider_url', $first);
        $this->assertArrayHasKey('airport_code', $first);
        $this->assertArrayHasKey('raw_attributes', $first);
        $this->assertSame('IBZ', $first['airport_code']);
        $this->assertSame('Sat 25 Jul 2026 08:25 – Sat 25 Jul 2026 12:00', $first['raw_attributes']['outbound_flight'] ?? null);
        $this->assertSame('Tue 04 Aug 2026 10:45 – Tue 04 Aug 2026 12:25', $first['raw_attributes']['inbound_flight'] ?? null);
        $this->assertSame('jet2_smartsearch_api', $first['raw_attributes']['source'] ?? null);
        $this->assertTrue(str_starts_with((string) $first['provider_url'], '/beach/'));
    }

    public function test_it_falls_back_to_available_flight_when_selected_flight_id_missing(): void
    {
        $provider = ProviderSource::query()->create([
            'key' => 'jet2',
            'name' => 'Jet2 Holidays',
            'base_url' => 'https://www.jet2holidays.com',
            'status' => ProviderSourceStatus::Active,
        ]);
        $search = SavedHolidaySearch::query()->create([
            'name' => 'Fallback Flight Search',
            'slug' => 'fallback-flight-search',
            'provider_import_url' => 'https://www.jet2holidays.com/search/results?airport=98&date=25-07-2026&duration=10&occupancy=r2c1_4&destination=39',
            'departure_airport_code' => 'MAN',
            'travel_start_date' => '2026-07-25',
            'duration_min_nights' => 10,
            'duration_max_nights' => 10,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'status' => 'active',
        ]);
        $payload = [
            'results' => [
                [
                    'name' => 'Fallback Hotel',
                    'bookingUrl' => '/beach/spain/test/fallback-hotel',
                    'selectedFlightId' => 9999,
                    'accommodationOptions' => [
                        [
                            'board' => 'All Inclusive',
                            'boardId' => 'AI',
                            'priceOptions' => [
                                ['flightId' => 7, 'totalPrice' => 5000, 'pricePerPerson' => 1000],
                            ],
                        ],
                    ],
                    'property' => [
                        'id' => 'fallback-hotel-id',
                    ],
                ],
            ],
            'flights' => [
                [
                    'flightId' => 7,
                    'outbound' => [
                        'arrivalAirportCode' => 'AGP',
                        'departureDateTimeLocal' => '2026-07-25T08:05:00',
                        'arrivalDateTimeLocal' => '2026-07-25T12:10:00',
                    ],
                    'inbound' => [
                        'departureDateTimeLocal' => '2026-08-04T13:20:00',
                        'arrivalDateTimeLocal' => '2026-08-04T15:35:00',
                    ],
                ],
            ],
        ];

        $importer = new Jet2LiveImporter;
        $method = new \ReflectionMethod($importer, 'candidatesFromApiJson');
        $method->setAccessible(true);
        $candidates = $method->invoke($importer, $payload, $search, $provider, $search->provider_import_url);

        $this->assertIsArray($candidates);
        $this->assertCount(1, $candidates);
        $this->assertSame('AGP', $candidates[0]['airport_code']);
        $this->assertSame('Sat 25 Jul 2026 08:05 – Sat 25 Jul 2026 12:10', $candidates[0]['raw_attributes']['outbound_flight'] ?? null);
        $this->assertSame('Tue 04 Aug 2026 13:20 – Tue 04 Aug 2026 15:35', $candidates[0]['raw_attributes']['inbound_flight'] ?? null);
    }

    public function test_it_prefers_selected_flight_id_over_selected_price_flight_id(): void
    {
        $provider = ProviderSource::query()->create([
            'key' => 'jet2',
            'name' => 'Jet2 Holidays',
            'base_url' => 'https://www.jet2holidays.com',
            'status' => ProviderSourceStatus::Active,
        ]);
        $search = SavedHolidaySearch::query()->create([
            'name' => 'Selected Price Flight Search',
            'slug' => 'selected-price-flight-search',
            'provider_import_url' => 'https://www.jet2holidays.com/search/results?airport=98&date=25-07-2026&duration=10&occupancy=r2c1_4&destination=39',
            'departure_airport_code' => 'MAN',
            'travel_start_date' => '2026-07-25',
            'duration_min_nights' => 10,
            'duration_max_nights' => 10,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'status' => 'active',
        ]);
        $payload = [
            'results' => [
                [
                    'name' => 'Selected Price Hotel',
                    'bookingUrl' => '/beach/spain/test/selected-price-hotel',
                    'selectedFlightId' => 9,
                    'selectedPrice' => [
                        'flightId' => 7,
                        'totalPrice' => 5000,
                        'pricePerPerson' => 1000,
                    ],
                    'property' => [
                        'id' => 'selected-price-hotel-id',
                    ],
                ],
            ],
            'flights' => [
                [
                    'flightId' => 9,
                    'outbound' => [
                        'arrivalAirportCode' => 'AGP',
                        'departureDateTimeLocal' => '2026-07-25T07:25:00',
                        'arrivalDateTimeLocal' => '2026-07-25T11:25:00',
                    ],
                    'inbound' => [
                        'departureDateTimeLocal' => '2026-08-04T12:20:00',
                        'arrivalDateTimeLocal' => '2026-08-04T14:25:00',
                    ],
                ],
                [
                    'flightId' => 7,
                    'outbound' => [
                        'arrivalAirportCode' => 'AGP',
                        'departureDateTimeLocal' => '2026-07-25T08:05:00',
                        'arrivalDateTimeLocal' => '2026-07-25T12:10:00',
                    ],
                    'inbound' => [
                        'departureDateTimeLocal' => '2026-08-04T13:20:00',
                        'arrivalDateTimeLocal' => '2026-08-04T15:35:00',
                    ],
                ],
            ],
        ];

        $importer = new Jet2LiveImporter;
        $method = new \ReflectionMethod($importer, 'candidatesFromApiJson');
        $method->setAccessible(true);
        $candidates = $method->invoke($importer, $payload, $search, $provider, $search->provider_import_url);

        $this->assertCount(1, $candidates);
        $this->assertSame('Sat 25 Jul 2026 07:25 – Sat 25 Jul 2026 11:25', $candidates[0]['raw_attributes']['outbound_flight'] ?? null);
        $this->assertSame('Tue 04 Aug 2026 12:20 – Tue 04 Aug 2026 14:25', $candidates[0]['raw_attributes']['inbound_flight'] ?? null);
    }

    private function readJet2ApiFixture(): string
    {
        $paths = [
            base_path('tests/Fixtures/jet2_search_api_airport98_page1.json'),
            '/Users/wade/Sites/beachin/tests/fixtures/jet2_search_api_airport98_page1.json',
        ];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $content = file_get_contents($path);
                if (is_string($content) && trim($content) !== '') {
                    return $content;
                }
            }
        }

        $this->fail('Jet2 API fixture not found in known fixture paths.');
    }
}
