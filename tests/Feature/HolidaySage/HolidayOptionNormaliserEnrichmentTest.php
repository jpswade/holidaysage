<?php

namespace Tests\Feature\HolidaySage;

use App\Enums\ProviderSourceStatus;
use App\Models\ProviderSource;
use App\Services\Normalisation\HolidayOptionNormaliser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayOptionNormaliserEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_routes_phase_one_fields_to_hotel_and_package_models(): void
    {
        $provider = ProviderSource::query()->create([
            'key' => 'jet2',
            'name' => 'Jet2 Holidays',
            'base_url' => 'https://www.jet2holidays.com',
            'status' => ProviderSourceStatus::Active,
        ]);

        $normaliser = app(HolidayOptionNormaliser::class);
        $payload = [
            'provider_hotel_id' => 'hotel-123',
            'provider_option_id' => 'option-123',
            'provider_url' => 'https://www.jet2holidays.com/beach/spain/test/hotel',
            'hotel_name' => 'Hotel Test',
            'hotel_slug' => 'hotel-test',
            'destination_name' => 'Costa Del Sol',
            'destination_country' => 'Spain',
            'airport_code' => 'MAN',
            'departure_date' => '2026-07-25',
            'return_date' => '2026-08-04',
            'nights' => 10,
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'board_type' => 'AI',
            'board_recommended' => 'All Inclusive',
            'price_total' => 9390.00,
            'price_per_person' => 1878.00,
            'currency' => 'GBP',
            'distance_to_airport_km' => 47.51,
            'rooms_count' => 413,
            'blocks_count' => 3,
            'floors_count' => 7,
            'restaurants_count' => 4,
            'bars_count' => 2,
            'pools_count' => 2,
            'sports_leisure_count' => 2,
            'has_lift' => true,
            'ground_floor_available' => true,
            'accessibility_issues' => 'steps_to_rooms',
            'local_beer_price' => 2.50,
            'three_course_meal_for_two_price' => 42.10,
            'outbound_flight_time_text' => '08:05-12:10',
            'inbound_flight_time_text' => '13:20-15:35',
            'raw_attributes' => [
                'property' => ['id' => 'hotel-123'],
                'accommodation_options' => [['board' => 'All Inclusive']],
                'keySellingPoints' => ['Beachfront'],
            ],
        ];

        $normalised = $normaliser->normaliseAndSign($payload, $provider);
        $package = $normaliser->upsert($provider, $normalised);
        $hotel = $package->hotel()->firstOrFail();

        $this->assertSame(413, $hotel->rooms_count);
        $this->assertSame(3, $hotel->blocks_count);
        $this->assertSame(7, $hotel->floors_count);
        $this->assertSame(4, $hotel->restaurants_count);
        $this->assertSame(2, $hotel->bars_count);
        $this->assertSame(2, $hotel->pools_count);
        $this->assertSame(2, $hotel->sports_leisure_count);
        $this->assertSame('47.51', (string) $hotel->distance_to_airport_km);
        $this->assertTrue($hotel->has_lift);
        $this->assertTrue($hotel->ground_floor_available);
        $this->assertSame('steps_to_rooms', $hotel->accessibility_issues);
        $this->assertIsArray($hotel->raw_attributes);
        $this->assertArrayHasKey('hotel_extra', $hotel->raw_attributes);

        $this->assertSame('All Inclusive', $package->board_recommended);
        $this->assertSame('2.50', (string) $package->local_beer_price);
        $this->assertSame('42.10', (string) $package->three_course_meal_for_two_price);
        $this->assertSame('08:05-12:10', $package->outbound_flight_time_text);
        $this->assertSame('13:20-15:35', $package->inbound_flight_time_text);
        $this->assertIsArray($package->raw_attributes);
        $this->assertArrayHasKey('package_extra', $package->raw_attributes);
    }
}
