<?php

namespace App\ViewModels;

use App\Models\Hotel;
use App\Models\ScoredHolidayOption;
use App\Support\BoardBasisDisplay;
use Illuminate\Support\Str;

class ResultCardViewModel
{
    /**
     * @param  list<string>  $recommendationHighlights  Why it scored well (shown when hotel intro copy is used).
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
        public readonly string $recommendationBlurb,
        public readonly array $recommendationHighlights,
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

        $introduction = $hotel?->introduction_snippet !== null ? (string) $hotel->introduction_snippet : null;
        $editorialUsed = self::normaliseEditorialIntro($introduction) !== '';

        $recommendationBlurb = self::buildRecommendationBlurb(
            $introduction,
            $row->recommendation_summary,
            $reasonsList,
            $review,
            $hotel?->destination_name ? (string) $hotel->destination_name : '',
            $boardLabel,
            $hotel?->hotel_name ? (string) $hotel->hotel_name : 'This hotel',
            $hotel,
        );

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
            recommendationBlurb: $recommendationBlurb,
            recommendationHighlights: $editorialUsed
                ? self::topRecommendationHighlights(self::filterSpecificReasons($reasonsList))
                : [],
            reasons: $reasonsList,
            warnings: array_values(array_filter(array_map('strval', $row->warning_flags ?? []))),
            featureChips: $featureChips,
            review: $review,
            imageUrl: $hotel?->primaryImageUrlForDisplay(),
            isDisqualified: (bool) $row->is_disqualified,
        );
    }

    /**
     * @param  list<string>  $reasons  Already filtered to drop generic scorer padding.
     * @return list<string>
     */
    private static function topRecommendationHighlights(array $reasons): array
    {
        $out = [];
        foreach (array_slice($reasons, 0, 3) as $r) {
            $t = trim((string) $r);
            if ($t === '') {
                continue;
            }
            $out[] = Str::limit($t, 130, '…');
        }

        return $out;
    }

    /**
     * @param  list<string>  $reasons
     * @return list<string>
     */
    private static function filterSpecificReasons(array $reasons): array
    {
        return array_values(array_filter(
            $reasons,
            static fn (string $s): bool => ! self::isGenericScorerPadding($s)
        ));
    }

    private static function isGenericScorerPadding(string $line): bool
    {
        $n = strtolower(trim($line));

        return in_array($n, [
            'strong guest ratings for this property',
            'balanced option across the criteria you care about',
        ], true);
    }

    /**
     * When we have no long intro, still give a short sense of place and facilities (not review padding).
     */
    private static function hotelAtmosphereLine(?Hotel $hotel): ?string
    {
        if ($hotel === null) {
            return null;
        }

        $parts = [];
        $resort = trim((string) ($hotel->resort_name ?? ''));
        $dest = trim((string) ($hotel->destination_name ?? ''));
        if ($resort !== '' && $dest !== '') {
            $parts[] = 'Set in '.$resort.', '.$dest;
        } elseif ($dest !== '') {
            $parts[] = 'Located in '.$dest;
        }

        $sr = $hotel->star_rating;
        if ($sr !== null && $sr !== '' && is_numeric($sr) && (float) $sr > 0) {
            $parts[] = (int) round((float) $sr).'-star property';
        }

        $dBeach = $hotel->distance_to_beach_meters;
        if ($dBeach !== null && is_numeric($dBeach)) {
            $m = (int) $dBeach;
            if ($m > 0 && $m <= 500) {
                $parts[] = 'beach within about '.$m.'m';
            } elseif ($m > 500 && $m <= 2500) {
                $parts[] = 'around '.round($m / 1000, 1).'km from the beach';
            }
        }

        if ($hotel->pools_count !== null && (int) $hotel->pools_count >= 2) {
            $parts[] = (int) $hotel->pools_count.' pools on site';
        }

        if ($parts === []) {
            return null;
        }

        $sentence = $parts[0];
        if (count($parts) > 1) {
            $sentence .= '. '.implode(', ', array_slice($parts, 1));
        }

        return Str::limit($sentence.'.', 420, '…');
    }

    /**
     * Primary editorial copy: prefer the provider/hotel description (e.g. Jet2 intro text), which
     * matches the v0 “long paragraph” style. The default scorer summary is often
     * a short “Solid fit:” template — that should not win when richer hotel copy exists.
     *
     * @param  list<string>  $reasons
     */
    private static function buildRecommendationBlurb(
        ?string $introductionSnippet,
        ?string $summary,
        array $reasons,
        ?string $review,
        string $destinationName,
        ?string $boardType,
        string $hotelName,
        ?Hotel $hotel,
    ): string {
        $editorial = self::normaliseEditorialIntro($introductionSnippet);
        if ($editorial !== '') {
            return $editorial;
        }

        $summary = trim((string) $summary);
        if ($summary !== '' && ! self::isTemplatedScorerSummary($summary)) {
            return $summary;
        }

        $trimmed = array_values(array_filter(
            array_map(trim(...), $reasons),
            fn (string $s): bool => $s !== ''
        ));
        $specific = self::filterSpecificReasons($trimmed);
        if ($specific !== []) {
            if (count($specific) === 1) {
                return $specific[0].'.';
            }
            if (count($specific) === 2) {
                return $specific[0].' and '.$specific[1].'.';
            }
            $last = array_pop($specific);

            return implode(', ', $specific).', and '.$last.'.';
        }

        $hotelLine = self::hotelAtmosphereLine($hotel);
        if ($hotelLine !== null && $hotelLine !== '') {
            return $hotelLine;
        }

        $bits = [];
        if ($review !== null && $review !== '') {
            $bits[] = 'traveller reviews at '.$review;
        }
        if (is_string($boardType) && $boardType !== '') {
            $bits[] = $boardType;
        }
        if ($destinationName !== '') {
            $bits[] = 'a well-rated base in '.$destinationName;
        }
        if ($bits !== []) {
            return 'In brief: '.self::conjunctionFromBits($bits).'.';
        }

        if ($summary !== '') {
            return $summary;
        }

        return 'We score each option from your search against price, review profile, and the preferences you chose—so you can compare fairly at a glance.';
    }

    private const MAX_INTRO_LENGTH = 520;

    /**
     * Plain text from the hotel’s introduction snippet, trimmed to card-friendly length.
     */
    private static function normaliseEditorialIntro(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        $t = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        if (mb_strlen($t) < 30) {
            return '';
        }

        return Str::limit($t, self::MAX_INTRO_LENGTH, '…');
    }

    /**
     * The default scorer summary line: factual but not editorial.
     * Keep it as a last resort so recommendation_reasons and destination copy can read better first.
     */
    private static function isTemplatedScorerSummary(string $summary): bool
    {
        return str_starts_with(strtolower($summary), 'solid fit:');
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
