<?php

namespace App\ViewModels;

use App\Models\ScoredHolidayOption;
use App\Support\BoardBasisDisplay;

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
        /**
         * Primary paragraph for the card: prefer model summary, else join reasons, else a short fallback.
         */
        public readonly string $recommendationBlurb,
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
        $reasonsList = array_values(array_filter(array_map('strval', $row->recommendation_reasons ?? [])));
        $boardLabel = BoardBasisDisplay::humanLabel($package?->board_type, $package?->board_recommended);

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
            boardType: $boardLabel,
            providerUrl: $package?->provider_url,
            recommendationSummary: $row->recommendation_summary,
            recommendationBlurb: self::buildRecommendationBlurb(
                $row->recommendation_summary,
                $reasonsList,
                $review,
                $hotel?->destination_name ? (string) $hotel->destination_name : '',
                $boardLabel,
                $hotel?->hotel_name ? (string) $hotel->hotel_name : 'This hotel',
            ),
            reasons: $reasonsList,
            warnings: array_values(array_filter(array_map('strval', $row->warning_flags ?? []))),
            featureChips: $featureChips,
            review: $review,
            imageUrl: $hotel?->primaryImageUrlForDisplay(),
            isDisqualified: (bool) $row->is_disqualified,
        );
    }

    /**
     * @param  list<string>  $reasons
     */
    private static function buildRecommendationBlurb(
        ?string $summary,
        array $reasons,
        ?string $review,
        string $destinationName,
        ?string $boardType,
        string $hotelName,
    ): string {
        $summary = trim((string) $summary);
        if ($summary !== '') {
            return $summary;
        }

        $reasons = array_values(array_filter(
            array_map(trim(...), $reasons),
            fn (string $s): bool => $s !== ''
        ));
        if ($reasons !== []) {
            if (count($reasons) === 1) {
                return $reasons[0].'.';
            }
            if (count($reasons) === 2) {
                return $reasons[0].' and '.$reasons[1].'.';
            }
            $last = array_pop($reasons);

            return implode(', ', $reasons).', and '.$last.'.';
        }

        $bits = [];
        if ($review !== null && $review !== '') {
            $bits[] = 'guest ratings '.$review;
        }
        if (is_string($boardType) && $boardType !== '') {
            $bits[] = $boardType;
        }
        if ($destinationName !== '') {
            $bits[] = 'a strong setting in '.$destinationName;
        }
        if ($bits !== []) {
            return 'Why it stands out: '.self::conjunctionFromBits($bits).'.';
        }

        return 'A strong all-round match for this search: we balance price, reviews, and how well the property fits the preferences you set.';
    }

    /**
     * @param  list<string>  $bits
     */
    private static function conjunctionFromBits(array $bits): string
    {
        if (count($bits) === 0) {
            return '';
        }
        if (count($bits) === 1) {
            return $bits[0];
        }
        if (count($bits) === 2) {
            return $bits[0].' and '.$bits[1];
        }
        $last = array_pop($bits);

        return implode(', ', $bits).', and '.$last;
    }

    private static function minutesToText(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return $hours > 0 ? sprintf('%dh %02dm flight', $hours, $remainder) : $minutes.' min flight';
    }
}
