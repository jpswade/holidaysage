<?php

namespace Tests\Unit\ProviderImport;

use App\Enums\ProviderSourceStatus;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Services\ProviderImport\Importers\Jet2LiveImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Jet2UrlContract;
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
            'provider_import_url' => Jet2UrlContract::forImporterWithMultiDepartureAirportIds(),
            'departure_airport_code' => 'BHX',
            'travel_start_date' => '2026-07-25',
            'duration_min_nights' => 10,
            'duration_max_nights' => 10,
            'adults' => 4,
            'children' => 2,
            'infants' => 0,
            'status' => 'active',
        ]);

        $importer = $this->app->make(Jet2LiveImporter::class);
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
        $this->assertSame(215, $first['flight_outbound_duration_minutes']);
        $this->assertSame(100, $first['flight_inbound_duration_minutes']);
        $this->assertNull($first['transfer_minutes']);
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
            'provider_import_url' => Jet2UrlContract::forSingleRoomWithTwoChildAges(),
            'departure_airport_code' => 'MAN',
            'travel_start_date' => '2026-07-25',
            'duration_min_nights' => 10,
            'duration_max_nights' => 10,
            'adults' => 2,
            'children' => 2,
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

        $importer = $this->app->make(Jet2LiveImporter::class);
        $method = new \ReflectionMethod($importer, 'candidatesFromApiJson');
        $method->setAccessible(true);
        $candidates = $method->invoke($importer, $payload, $search, $provider, $search->provider_import_url);

        $this->assertIsArray($candidates);
        $this->assertCount(1, $candidates);
        $this->assertSame('AGP', $candidates[0]['airport_code']);
        $this->assertSame('Sat 25 Jul 2026 08:05 – Sat 25 Jul 2026 12:10', $candidates[0]['raw_attributes']['outbound_flight'] ?? null);
        $this->assertSame('Tue 04 Aug 2026 13:20 – Tue 04 Aug 2026 15:35', $candidates[0]['raw_attributes']['inbound_flight'] ?? null);
        $this->assertSame(245, $candidates[0]['flight_outbound_duration_minutes']);
        $this->assertSame(135, $candidates[0]['flight_inbound_duration_minutes']);
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
            'provider_import_url' => Jet2UrlContract::forSingleRoomWithTwoChildAges(),
            'departure_airport_code' => 'MAN',
            'travel_start_date' => '2026-07-25',
            'duration_min_nights' => 10,
            'duration_max_nights' => 10,
            'adults' => 2,
            'children' => 2,
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

        $importer = $this->app->make(Jet2LiveImporter::class);
        $method = new \ReflectionMethod($importer, 'candidatesFromApiJson');
        $method->setAccessible(true);
        $candidates = $method->invoke($importer, $payload, $search, $provider, $search->provider_import_url);

        $this->assertCount(1, $candidates);
        $this->assertSame('Sat 25 Jul 2026 07:25 – Sat 25 Jul 2026 11:25', $candidates[0]['raw_attributes']['outbound_flight'] ?? null);
        $this->assertSame('Tue 04 Aug 2026 12:20 – Tue 04 Aug 2026 14:25', $candidates[0]['raw_attributes']['inbound_flight'] ?? null);
        $this->assertSame(240, $candidates[0]['flight_outbound_duration_minutes']);
        $this->assertSame(125, $candidates[0]['flight_inbound_duration_minutes']);
    }

    public function test_it_extracts_images_from_smartsearch_api_payload_when_available(): void
    {
        $provider = ProviderSource::query()->create([
            'key' => 'jet2',
            'name' => 'Jet2 Holidays',
            'base_url' => 'https://www.jet2holidays.com',
            'status' => ProviderSourceStatus::Active,
        ]);
        $search = SavedHolidaySearch::query()->create([
            'name' => 'Image Payload Search',
            'slug' => 'image-payload-search',
            'provider_import_url' => Jet2UrlContract::forSingleRoomWithTwoChildAges(),
            'departure_airport_code' => 'MAN',
            'travel_start_date' => '2026-07-25',
            'duration_min_nights' => 7,
            'duration_max_nights' => 7,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'status' => 'active',
        ]);

        $payload = [
            'results' => [
                [
                    'name' => 'Image Hotel',
                    'bookingUrl' => '/beach/spain/test/image-hotel',
                    'selectedPrice' => ['totalPrice' => 1234, 'pricePerPerson' => 617],
                    'images' => [
                        ['url' => 'https://media.jet2.com/is/image/jet2/image-1'],
                        ['imageUrl' => 'https://media.jet2.com/is/image/jet2/image-2'],
                    ],
                    'property' => [
                        'id' => 'image-hotel-id',
                        'heroImageUrl' => 'https://media.jet2.com/is/image/jet2/image-1',
                    ],
                ],
            ],
            'flights' => [],
        ];

        $importer = $this->app->make(Jet2LiveImporter::class);
        $method = new \ReflectionMethod($importer, 'candidatesFromApiJson');
        $method->setAccessible(true);
        $candidates = $method->invoke($importer, $payload, $search, $provider, $search->provider_import_url);

        $this->assertCount(1, $candidates);
        $images = $candidates[0]['images'] ?? null;
        $this->assertIsArray($images);
        $this->assertSame('https://media.jet2.com/is/image/jet2/image-1', $images[0]['url'] ?? null);
        $this->assertSame('https://media.jet2.com/is/image/jet2/image-2', $images[1]['url'] ?? null);
        $this->assertSame('jet2_smartsearch_api', $images[0]['source'] ?? null);
        $this->assertSame($images, $candidates[0]['raw_attributes']['images'] ?? null);
    }

    private function readJet2ApiFixture(): string
    {
        $path = Jet2UrlContract::apiResponsePath();
        $content = file_get_contents($path);
        $this->assertIsString($content);
        $this->assertNotSame('', trim($content), 'API fixture is empty: '.$path);

        return $content;
    }
}
