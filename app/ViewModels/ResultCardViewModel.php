<?php

namespace App\ViewModels;

use App\Models\ScoredHolidayOption;

class ResultCardViewModel
{
    /**
     * @param  list<string>  $reasons
     * @param  list<string>  $warnings
     * @param  list<string>  $featureChips
     */
    public function __construct(
        public readonly int $id,
        public readonly ?int $rank,
        public readonly string $providerName,
        public readonly string $hotelName,
        public readonly string $destinationName,
        public readonly float $overallScore,
        public readonly string $priceTotal,
        public readonly ?string $pricePerPerson,
        public readonly string $nights,
        public readonly ?string $flightOutbound,
        public readonly ?string $transfer,
        public readonly ?string $boardType,
        public readonly ?string $providerUrl,
        public readonly ?string $recommendationSummary,
        public readonly array $reasons,
        public readonly array $warnings,
        public readonly array $featureChips,
        public readonly ?string $review,
        public readonly ?string $imageUrl,
        public readonly bool $isDisqualified,
    ) {}

    public static function fromModel(ScoredHolidayOption $row): self
    {
        $package = $row->holidayPackage;
        $hotel = $package?->hotel;
        $provider = $package?->providerSource;

        $featureChips = [];
        if ($hotel?->has_kids_club) {
            $featureChips[] = 'Kids club';
        }
        if ($hotel?->has_waterpark) {
            $featureChips[] = 'Waterpark';
        }
        if ($hotel?->distance_to_beach_meters) {
            $featureChips[] = (int) $hotel->distance_to_beach_meters.'m to beach';
        }
        if ($hotel?->pools_count) {
            $featureChips[] = (int) $hotel->pools_count.' pools';
        }

        $review = null;
        if ($hotel?->review_score !== null) {
            $review = number_format((float) $hotel->review_score, 1).'/5';
            if ($hotel->review_count !== null) {
                $review .= ' ('.number_format((int) $hotel->review_count).' reviews)';
            }
        }

        $priceTotal = $package ? '£'.number_format((float) $package->price_total, 0) : 'Price unavailable';
        $pricePerPerson = $package?->price_per_person !== null ? '£'.number_format((float) $package->price_per_person, 0).' per person' : null;

        return new self(
            id: $row->id,
            rank: $row->rank_position !== null ? (int) $row->rank_position : null,
            providerName: $provider?->name ?? 'Unknown provider',
            hotelName: $hotel?->hotel_name ?? 'Unknown hotel',
            destinationName: $hotel?->destination_name ?? 'Unknown destination',
            overallScore: (float) $row->overall_score,
            priceTotal: $priceTotal,
            pricePerPerson: $pricePerPerson,
            nights: $package ? (int) $package->nights.' nights' : 'Nights unavailable',
            flightOutbound: $package?->flight_outbound_duration_minutes ? self::minutesToText((int) $package->flight_outbound_duration_minutes) : null,
            transfer: $package?->transfer_minutes ? (int) $package->transfer_minutes.' min transfer' : null,
            boardType: $package?->board_type ? ucwords(str_replace('_', ' ', (string) $package->board_type)) : null,
            providerUrl: $package?->provider_url,
            recommendationSummary: $row->recommendation_summary,
            reasons: array_values(array_filter(array_map('strval', $row->recommendation_reasons ?? []))),
            warnings: array_values(array_filter(array_map('strval', $row->warning_flags ?? []))),
            featureChips: $featureChips,
            review: $review,
            imageUrl: self::extractImageUrl($package?->raw_attributes, $hotel?->raw_attributes),
            isDisqualified: (bool) $row->is_disqualified,
        );
    }

    private static function extractImageUrl(?array $packageRaw, ?array $hotelRaw): ?string
    {
        $paths = [
            ['hotel_extra', 'property', 'image'],
            ['hotel_extra', 'property', 'images', 0],
            ['hotel_extra', 'property', 'heroImage'],
            ['package_extra', 'property', 'image'],
            ['package_extra', 'property', 'images', 0],
            // Legacy path for rows saved before hotel/package raw wrapping.
            ['property', 'image'],
        ];

        foreach ($paths as $path) {
            $hotelValue = self::valueAtPath($hotelRaw, $path);
            if (is_string($hotelValue) && self::looksLikeImageUrl($hotelValue)) {
                return $hotelValue;
            }

            $packageValue = self::valueAtPath($packageRaw, $path);
            if (is_string($packageValue) && self::looksLikeImageUrl($packageValue)) {
                return $packageValue;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  list<string|int>  $path
     */
    private static function valueAtPath(?array $payload, array $path): mixed
    {
        $current = $payload;

        foreach ($path as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    private static function looksLikeImageUrl(string $value): bool
    {
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        if ((bool) preg_match('/\.(jpg|jpeg|png|webp|gif)(\?|$)/i', $value)) {
            return true;
        }

        $host = parse_url($value, PHP_URL_HOST);
        $path = parse_url($value, PHP_URL_PATH);

        if (! is_string($host) || ! is_string($path)) {
            return false;
        }

        return str_contains($host, 'media.jet2.com') && str_starts_with($path, '/is/image/');
    }

    private static function minutesToText(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return $hours > 0 ? sprintf('%dh %02dm flight', $hours, $remainder) : $minutes.' min flight';
    }
}
