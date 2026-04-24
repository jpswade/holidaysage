<?php

namespace App\Services\ProviderImport\DetailParsers;

use App\Contracts\ProviderDetailPageParser;
use App\Services\ProviderImport\Importers\Concerns\ExtractsEmbeddedJson;
use Illuminate\Support\Str;

class Jet2DetailPageParser implements ProviderDetailPageParser
{
    use ExtractsEmbeddedJson;

    /**
     * @param  array<string,mixed>  $candidate
     * @return array{hotel: array<string,mixed>, packages: list<array<string,mixed>>}
     */
    public function parse(array $candidate, string $html): array
    {
        $hotel = [];
        $packages = [];
        $overviewItems = $this->extractOverviewListItems($html);
        $property = is_array($candidate['raw_attributes']['property'] ?? null)
            ? $candidate['raw_attributes']['property']
            : [];

        if (is_numeric($property['tripAdvisorRating'] ?? null)) {
            $hotel['review_score'] = (float) $property['tripAdvisorRating'];
        }
        if (is_numeric($property['tripAdvisorReviewCount'] ?? null)) {
            $hotel['review_count'] = (int) $property['tripAdvisorReviewCount'];
        }
        if (is_numeric($property['mapLocation']['latitude'] ?? null)) {
            $hotel['latitude'] = (float) $property['mapLocation']['latitude'];
        }
        if (is_numeric($property['mapLocation']['longitude'] ?? null)) {
            $hotel['longitude'] = (float) $property['mapLocation']['longitude'];
        }
        if (is_numeric($property['rating'] ?? null)) {
            $hotel['star_rating'] = (int) round((float) $property['rating']);
        }
        if (is_array($property['features'] ?? null)) {
            $features = array_map(fn ($f) => Str::lower((string) $f), $property['features']);
            foreach ($features as $feature) {
                if (str_contains($feature, 'family')) {
                    $hotel['is_family_friendly'] = true;
                }
                if (str_contains($feature, 'kids club') || str_contains($feature, 'children')) {
                    $hotel['has_kids_club'] = true;
                }
                if (str_contains($feature, 'waterpark') || str_contains($feature, 'slides')) {
                    $hotel['has_waterpark'] = true;
                }
                if (str_contains($feature, 'family room')) {
                    $hotel['has_family_rooms'] = true;
                }
                if (preg_match('/^(\d+)\s*rooms?$/i', $feature, $m)) {
                    $hotel['rooms_count'] = (int) $m[1];
                }
                if (preg_match('/^(\d+)\s*blocks?$/i', $feature, $m)) {
                    $hotel['blocks_count'] = (int) $m[1];
                }
                if (preg_match('/^(\d+)\s*floors?$/i', $feature, $m)) {
                    $hotel['floors_count'] = (int) $m[1];
                }
                if (preg_match('/^(\d+)\s+restaurants?\b/i', $feature, $m)) {
                    $hotel['restaurants_count'] = (int) $m[1];
                }
                if (preg_match('/^(\d+)\s+bars?\b/i', $feature, $m)) {
                    $hotel['bars_count'] = (int) $m[1];
                }
                if (preg_match('/^(\d+)\s+(?:outdoor\s+)?pools?\b/i', $feature, $m)) {
                    $hotel['pools_count'] = (int) $m[1];
                }
                if (preg_match('/^(\d+)\s*lifts?$/i', $feature, $m)) {
                    $hotel['has_lift'] = ((int) $m[1]) > 0;
                }
            }
        }
        foreach ($overviewItems as $itemRaw) {
            $item = Str::lower($itemRaw);
            if (preg_match('/^(\d+)\s*rooms?$/i', $item, $m)) {
                $hotel['rooms_count'] = (int) $m[1];
            }
            if (preg_match('/^(\d+)\s*blocks?$/i', $item, $m)) {
                $hotel['blocks_count'] = (int) $m[1];
            }
            if (preg_match('/^(\d+)\s*floors?$/i', $item, $m)) {
                $hotel['floors_count'] = (int) $m[1];
            }
            if (preg_match('/^(\d+)\s+restaurants?\b/i', $item, $m)) {
                $hotel['restaurants_count'] = (int) $m[1];
            }
            if (preg_match('/^(\d+)\s+bars?\b/i', $item, $m)) {
                $hotel['bars_count'] = (int) $m[1];
            }
            if (preg_match('/^(\d+)\s+(?:outdoor\s+)?pools?\b/i', $item, $m)) {
                $hotel['pools_count'] = (int) $m[1];
            }
            if (preg_match('/^(\d+)\s*lifts?$/i', $item, $m)) {
                $hotel['has_lift'] = ((int) $m[1]) > 0;
            }
            if (str_contains($item, 'no lift')) {
                $hotel['has_lift'] = false;
                $hotel['accessibility_issues'] = $this->appendIssue($hotel['accessibility_issues'] ?? null, 'no_lift');
            }
            if (str_contains($item, 'steps')) {
                $hotel['accessibility_issues'] = $this->appendIssue($hotel['accessibility_issues'] ?? null, 'steps_to_rooms');
            }
            if (preg_match('/(\d+)\s*m\s+from\s+.*resort centre/i', $item, $m)) {
                $hotel['distance_to_centre_meters'] = (int) $m[1];
            } elseif (str_contains($item, 'in ') && str_contains($item, 'resort centre')) {
                $hotel['distance_to_centre_meters'] = 0;
            }
            if (preg_match('/(\d+)\s*m\s+from\s+.*beach/i', $item, $m)) {
                $hotel['distance_to_beach_meters'] = (int) $m[1];
            }
        }

        if (is_array($property['keySellingPoints'] ?? null)) {
            foreach ($property['keySellingPoints'] as $pointRaw) {
                $point = Str::lower((string) $pointRaw);
                if (str_contains($point, 'family')) {
                    $hotel['is_family_friendly'] = true;
                }
                if (str_contains($point, 'beachfront') || str_contains($point, 'seafront') || str_contains($point, 'private beach')) {
                    $hotel['distance_to_beach_meters'] = 0;
                } elseif (! isset($hotel['distance_to_beach_meters']) && str_contains($point, 'close to the beach')) {
                    // Heuristic fallback when no explicit metre distance is provided.
                    $hotel['distance_to_beach_meters'] = 500;
                }

                if (preg_match('/(\d+)\s*m\s+from\s+.*resort centre/i', $point, $m) && is_numeric($m[1])) {
                    $hotel['distance_to_centre_meters'] = (int) $m[1];
                }
                if (preg_match('/(\d+)\s+restaurants?\b/i', $point, $m) && ! isset($hotel['restaurants_count'])) {
                    $hotel['restaurants_count'] = (int) $m[1];
                }
                if (preg_match('/(\d+)\s+bars?\b/i', $point, $m) && ! isset($hotel['bars_count'])) {
                    $hotel['bars_count'] = (int) $m[1];
                }
                if (preg_match('/(\d+)\s+(?:outdoor\s+)?pools?\b/i', $point, $m) && ! isset($hotel['pools_count'])) {
                    $hotel['pools_count'] = (int) $m[1];
                }
                if (preg_match('/(\d+)\s*rooms?\b/i', $point, $m) && ! isset($hotel['rooms_count'])) {
                    $hotel['rooms_count'] = (int) $m[1];
                }
            }
        }

        if (is_numeric($candidate['raw_attributes']['distance_to_airport_km'] ?? null)) {
            $hotel['distance_to_airport_km'] = (float) $candidate['raw_attributes']['distance_to_airport_km'];
        }

        $accommodationOptions = is_array($candidate['raw_attributes']['accommodation_options'] ?? null)
            ? $candidate['raw_attributes']['accommodation_options']
            : [];
        foreach ($accommodationOptions as $opt) {
            if (! is_array($opt) || ! is_array($opt['priceOptions'] ?? null)) {
                continue;
            }
            $boardId = isset($opt['boardId']) ? (string) $opt['boardId'] : '';
            $boardLabel = isset($opt['board']) && is_string($opt['board']) ? trim($opt['board']) : null;
            foreach ($opt['priceOptions'] as $priceOption) {
                if (! is_array($priceOption)) {
                    continue;
                }
                $totalPrice = $priceOption['totalPrice'] ?? $priceOption['basePrice'] ?? null;
                if (! is_numeric($totalPrice)) {
                    continue;
                }
                $packages[] = [
                    'board_type' => $boardId,
                    'board_recommended' => $boardLabel,
                    'price_total' => (float) $totalPrice,
                    'price_per_person' => is_numeric($priceOption['pricePerPerson'] ?? null) ? (float) $priceOption['pricePerPerson'] : null,
                ];
            }
        }

        $docs = $this->extractJsonDocuments($html);
        foreach ($docs as $doc) {
            $type = strtolower((string) ($doc['@type'] ?? ''));
            if ($type !== 'hotel') {
                continue;
            }

            $reviewScore = $doc['aggregateRating']['ratingValue'] ?? null;
            $reviewCount = $doc['aggregateRating']['reviewCount'] ?? null;
            if (is_numeric($reviewScore)) {
                $hotel['review_score'] = (float) $reviewScore;
            }
            if (is_numeric($reviewCount)) {
                $hotel['review_count'] = (int) $reviewCount;
            }
            $country = $doc['address']['addressCountry'] ?? null;
            if (is_string($country) && trim($country) !== '') {
                $hotel['destination_country'] = trim($country);
            }
            $lat = $doc['geo']['latitude'] ?? null;
            $lng = $doc['geo']['longitude'] ?? null;
            if (is_numeric($lat)) {
                $hotel['latitude'] = (float) $lat;
            }
            if (is_numeric($lng)) {
                $hotel['longitude'] = (float) $lng;
            }
            break;
        }

        if (preg_match('/data-resort="([^"]+)"/i', $html, $m)) {
            $hotel['resort_name'] = html_entity_decode(trim($m[1]));
        }
        if (preg_match('/official rating:\s*([0-9]+(?:\.[0-9])?)/i', Str::lower($html), $m) && is_numeric($m[1])) {
            $hotel['star_rating'] = (int) round((float) $m[1]);
        }

        $text = Str::lower(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        if (preg_match('/(\d+)\s*m\s+from\s+(?:the\s+)?[^.]*beach/i', $text, $m) && is_numeric($m[1])) {
            $hotel['distance_to_beach_meters'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*m\s+from\s+[^.]*resort centre/i', $text, $m) && is_numeric($m[1])) {
            $hotel['distance_to_centre_meters'] = (int) $m[1];
        }
        if (str_contains($text, 'children\'s club') || str_contains($text, 'kids club')) {
            $hotel['has_kids_club'] = true;
        }
        if (str_contains($text, 'waterpark') || str_contains($text, 'splash park')) {
            $hotel['has_waterpark'] = true;
        }
        if (str_contains($text, 'family room')) {
            $hotel['has_family_rooms'] = true;
        }
        if (str_contains($text, 'no lift') || str_contains($text, 'no lifts')) {
            $hotel['has_lift'] = false;
            $hotel['accessibility_issues'] = $this->appendIssue($hotel['accessibility_issues'] ?? null, 'no_lift');
        } elseif (! isset($hotel['has_lift']) && str_contains($text, ' lift')) {
            $hotel['has_lift'] = true;
        }
        if (str_contains($text, 'ground floor')) {
            $hotel['ground_floor_available'] = true;
        }
        if (str_contains($text, 'steps')) {
            $hotel['accessibility_issues'] = $this->appendIssue($hotel['accessibility_issues'] ?? null, 'steps_to_rooms');
        }
        if (preg_match('/(\d+)\s*rooms?\b/i', $text, $m) && ! isset($hotel['rooms_count'])) {
            $hotel['rooms_count'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*blocks?\b/i', $text, $m) && ! isset($hotel['blocks_count'])) {
            $hotel['blocks_count'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*floors?\b/i', $text, $m) && ! isset($hotel['floors_count'])) {
            $hotel['floors_count'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*restaurants?\b/i', $text, $m) && ! isset($hotel['restaurants_count'])) {
            $hotel['restaurants_count'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*bars?\b/i', $text, $m) && ! isset($hotel['bars_count'])) {
            $hotel['bars_count'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*(?:outdoor\s+)?pools?\b/i', $text, $m) && ! isset($hotel['pools_count'])) {
            $hotel['pools_count'] = (int) $m[1];
        }

        $sportsCount = $this->extractSportsLeisureCount($html);
        if ($sportsCount !== null) {
            $hotel['sports_leisure_count'] = $sportsCount;
        }

        $localInfo = $this->extractLocalInfo($html);
        $localBeerPrice = $this->extractCurrencyValue((string) ($localInfo['local_beer'] ?? ''), 'local beer');
        $mealForTwoPrice = $this->extractCurrencyValue((string) ($localInfo['three_course_meal_for_two'] ?? ''), 'three-course meal for two');
        $flightInfo = $this->extractFlightInfo($html);
        $outboundFlight = $this->extractFlightWindow($flightInfo['outbound'] ?? ($candidate['raw_attributes']['outbound_flight'] ?? null));
        $inboundFlight = $this->extractFlightWindow($flightInfo['inbound'] ?? ($candidate['raw_attributes']['inbound_flight'] ?? null));
        $recommendedBoard = $this->extractRecommendedBoard($html, $accommodationOptions);

        if ($packages === []) {
            $packages[] = [];
        }
        foreach ($packages as &$package) {
            if ($localBeerPrice !== null) {
                $package['local_beer_price'] = $localBeerPrice;
            }
            if ($mealForTwoPrice !== null) {
                $package['three_course_meal_for_two_price'] = $mealForTwoPrice;
            }
            if ($outboundFlight !== null) {
                $package['outbound_flight_time_text'] = $outboundFlight;
            }
            if ($inboundFlight !== null) {
                $package['inbound_flight_time_text'] = $inboundFlight;
            }
            if (! isset($package['board_recommended']) && $recommendedBoard !== null) {
                $package['board_recommended'] = $recommendedBoard;
            }
        }
        unset($package);

        // Optional package variants can be emitted by future parser improvements.
        return [
            'hotel' => $hotel,
            'packages' => $packages,
        ];
    }

    private function extractCurrencyValue(string $text, string $label): ?float
    {
        if ($text === '') {
            return null;
        }
        $pattern = '/([£€$])\s*([0-9]+(?:\.[0-9]{1,2})?)/i';
        if (! preg_match($pattern, Str::lower($text), $m)) {
            $pattern = '/'.preg_quote($label, '/').'.{0,80}?([0-9]+(?:\.[0-9]{1,2})?)/i';
            if (! preg_match($pattern, Str::lower($text), $m)) {
                return null;
            }
        }

        $val = $m[2] ?? $m[1] ?? null;

        return is_numeric($val) ? (float) $val : null;
    }

    private function extractSportsLeisureCount(string $html): ?int
    {
        if (! preg_match('/sports\s*&\s*leisure.*?<ul[^>]*>(.*?)<\/ul>/is', $html, $m)) {
            return null;
        }
        if (! preg_match_all('/<li\b[^>]*>/i', $m[1], $items)) {
            return null;
        }

        return count($items[0]);
    }

    private function extractRecommendedBoard(string $html, array $accommodationOptions): ?string
    {
        foreach ($accommodationOptions as $opt) {
            if (is_array($opt) && is_string($opt['board'] ?? null) && trim($opt['board']) !== '') {
                return trim((string) $opt['board']);
            }
        }

        if (preg_match_all('/overview__board-type-title[^>]*>\s*([^<]+)\s*</i', $html, $m) && $m[1] !== []) {
            $last = trim((string) end($m[1]));

            return $last !== '' ? $last : null;
        }

        return null;
    }

    private function extractFlightWindow(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        return $raw;
    }

    /**
     * @return array{outbound?: string, inbound?: string}
     */
    private function extractFlightInfo(string $html): array
    {
        if (! preg_match('/data-flight-information-modal-model="([^"]+)"/i', $html, $m)) {
            return [];
        }
        $decoded = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        $json = json_decode($decoded, true);
        if (! is_array($json)) {
            return [];
        }
        $outbound = $json['outboundFlight']['departureTime'] ?? null;
        $outboundArrival = $json['outboundFlight']['arrivalTime'] ?? null;
        $inbound = $json['inboundFlight']['departureTime'] ?? null;
        $inboundArrival = $json['inboundFlight']['arrivalTime'] ?? null;

        return [
            'outbound' => is_string($outbound) && is_string($outboundArrival) ? ($outbound.'-'.$outboundArrival) : (is_string($outbound) ? $outbound : null),
            'inbound' => is_string($inbound) && is_string($inboundArrival) ? ($inbound.'-'.$inboundArrival) : (is_string($inbound) ? $inbound : null),
        ];
    }

    /**
     * @return array<string,string>
     */
    private function extractLocalInfo(string $html): array
    {
        $out = [];
        if (! preg_match_all('/<p[^>]*class="grid-item__heading"[^>]*>(.*?)<\/p>\s*<p[^>]*class="grid-item__text"[^>]*>(.*?)<\/p>/is', $html, $m, PREG_SET_ORDER)) {
            return $out;
        }
        foreach ($m as $pair) {
            $heading = trim(Str::lower(strip_tags($pair[1] ?? '')));
            $value = trim(strip_tags(html_entity_decode($pair[2] ?? '', ENT_QUOTES | ENT_HTML5)));
            if ($heading === '' || $value === '') {
                continue;
            }
            if (str_contains($heading, 'local beer')) {
                $out['local_beer'] = $value;
            }
            if (str_contains($heading, 'three-course meal for two') || str_contains($heading, 'meal for two')) {
                $out['three_course_meal_for_two'] = $value;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function extractOverviewListItems(string $html): array
    {
        if (! preg_match_all('/<span[^>]*class="overview__list-text"[^>]*>(.*?)<\/span>/is', $html, $m)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => trim(strip_tags(html_entity_decode((string) $item, ENT_QUOTES | ENT_HTML5))),
            $m[1]
        ), fn ($item) => $item !== ''));
    }

    private function appendIssue(mixed $value, string $issue): string
    {
        $issues = [];
        if (is_string($value) && trim($value) !== '') {
            $issues = array_map('trim', explode(',', $value));
        }
        if (! in_array($issue, $issues, true)) {
            $issues[] = $issue;
        }

        return implode(',', array_values(array_filter($issues, fn ($item) => $item !== '')));
    }
}
