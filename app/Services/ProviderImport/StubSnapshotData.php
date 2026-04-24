<?php

namespace App\Services\ProviderImport;

use Carbon\Carbon;

/**
 * Deterministic sample candidates when live HTTP is disabled.
 *
 * @return array<string, mixed>
 */
final class StubSnapshotData
{
    public static function forProviderKey(string $key): array
    {
        return match ($key) {
            'jet2' => self::jet2(),
            'tui' => self::tui(),
            default => self::jet2(),
        };
    }

    /**
     * @return array{ candidates: list<array<string, mixed>> }
     */
    private static function jet2(): array
    {
        return [
            'candidates' => [
                self::baseCandidate(
                    'jet2',
                    'J2-OPT-1001',
                    'H-J2-1',
                    'https://www.jet2holidays.com/hotel/sunrise-ayia-napa',
                    'Sunrise Beach Hotel',
                    'sunrise-beach-hotel-ayia-napa',
                ),
            ],
        ];
    }

    /**
     * @return array{ candidates: list<array<string, mixed>> }
     */
    private static function tui(): array
    {
        return [
            'candidates' => [
                self::baseCandidate(
                    'tui',
                    'TUI-OPT-2002',
                    'H-TUI-1',
                    'https://www.tui.co.uk/holiday/majorca/dream-bay',
                    'Dream Bay Resort',
                    'dream-bay-resort',
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function baseCandidate(
        string $providerKey,
        string $optionId,
        string $hotelId,
        string $url,
        string $hotelName,
        string $slug
    ): array {
        $dep = Carbon::now()->addMonths(2)->startOfMonth()->addDays(7);
        $ret = (clone $dep)->addDays(7);

        return [
            'provider_option_id' => $optionId,
            'provider_hotel_id' => $hotelId,
            'provider_url' => $url,
            'hotel_name' => $hotelName,
            'hotel_slug' => $slug,
            'resort_name' => 'Seaside',
            'destination_name' => 'Test Destination',
            'destination_country' => 'XX',
            'airport_code' => 'MAN',
            'departure_date' => $dep->toDateString(),
            'return_date' => $ret->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'board_type' => 'all_inclusive',
            'price_total' => 2499.00,
            'price_per_person' => 1249.50,
            'currency' => 'GBP',
            'flight_outbound_duration_minutes' => 180,
            'flight_inbound_duration_minutes' => 190,
            'transfer_minutes' => 45,
            'distance_to_beach_meters' => 200,
            'distance_to_centre_meters' => 800,
            'star_rating' => 4,
            'review_score' => 4.3,
            'review_count' => 128,
            'is_family_friendly' => true,
            'has_kids_club' => true,
            'has_waterpark' => false,
            'has_family_rooms' => true,
            'latitude' => 35.0,
            'longitude' => 33.0,
            'raw_attributes' => [
                'stub' => $providerKey,
            ],
        ];
    }
}
