<?php

namespace App\Services\ProviderImport\DetailParsers;

use App\Contracts\ProviderDetailPageParser;
use App\Services\Airports\AirportLookupService;
use App\Services\ProviderImport\Importers\Concerns\ExtractsEmbeddedJson;
use Illuminate\Support\Str;

class Jet2DetailPageParser implements ProviderDetailPageParser
{
    use ExtractsEmbeddedJson;

    private const BOARD_LABELS = [
        '1' => 'Room Only',
        '2' => 'Bed & Breakfast',
        '3' => 'Half Board',
        '4' => 'Full Board',
        '5' => 'All Inclusive',
        'AI' => 'All Inclusive',
        'FB' => 'Full Board',
        'HB' => 'Half Board',
        'BB' => 'Bed & Breakfast',
        'SC' => 'Self Catering',
        'RO' => 'Room Only',
    ];

    /** @var list<string> */
    private const BOARD_PRIORITY = ['AI', 'FB', 'HB', 'BB', 'SC', 'RO'];

    public function __construct(
        private readonly AirportLookupService $airportLookup,
    ) {}

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
        $airportDistance = $this->deriveAirportDistanceKm(
            strtoupper((string) ($candidate['airport_code'] ?? '')),
            isset($hotel['latitude']) ? (float) $hotel['latitude'] : null,
            isset($hotel['longitude']) ? (float) $hotel['longitude'] : null,
            is_numeric($candidate['raw_attributes']['distance_to_airport_km'] ?? null) ? (float) $candidate['raw_attributes']['distance_to_airport_km'] : null
        );
        if ($airportDistance !== null) {
            $hotel['distance_to_airport_km'] = $airportDistance;
        }
        if (is_numeric($property['rating'] ?? null)) {
            $hotel['star_rating'] = (int) round((float) $property['rating']);
        }
        if (is_array($property['features'] ?? null)) {
            $features = array_map(fn ($f) => Str::lower((string) $f), $property['features']);
            foreach ($features as $feature) {
                $this->applyAmenitySignals($hotel, $feature);
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
            $this->applyAmenitySignals($hotel, $item);
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
            if (preg_match('/(\d+)\s*(km|m)\s+from\s+.*shops?/i', $item, $m)) {
                $hotel['near_shops'] = true;
                $hotel['distance_to_shops_meters'] = strtolower((string) $m[2]) === 'km'
                    ? (int) round(((float) $m[1]) * 1000)
                    : (int) $m[1];
            }
            if (preg_match('/(\d+)\s*(km|m)\s+from\s+.*(?:bars?|cafes?|restaurants?)/i', $item, $m)) {
                $hotel['cafes_bars'] = true;
                $hotel['distance_to_cafes_bars_meters'] = strtolower((string) $m[2]) === 'km'
                    ? (int) round(((float) $m[1]) * 1000)
                    : (int) $m[1];
            }
            if (preg_match('/(\d{1,2})\s*-\s*(\d{1,2})\s*yrs?/i', $item, $m)) {
                $minAge = (int) min((int) $m[1], (int) $m[2]);
                if ($minAge >= 2 && $minAge <= 17) {
                    $hotel['kids_club_age_min'] = $minAge;
                }
            }
        }
        if (($hotel['has_lift'] ?? null) === false && (($hotel['floors_count'] ?? null) !== null) && (int) $hotel['floors_count'] >= 3) {
            $hotel['accessibility_issues'] = $this->appendIssue($hotel['accessibility_issues'] ?? null, 'multi_storey_no_lift');
        }

        if (is_array($property['keySellingPoints'] ?? null)) {
            foreach ($property['keySellingPoints'] as $pointRaw) {
                $point = Str::lower((string) $pointRaw);
                $this->applyAmenitySignals($hotel, $point);
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
                $shopsDistance = $this->extractDistanceMetersFromText($point, ['shop', 'shops']);
                if ($shopsDistance !== null) {
                    $hotel['near_shops'] = true;
                    $hotel['distance_to_shops_meters'] = $shopsDistance;
                }
                $cafesDistance = $this->extractDistanceMetersFromText($point, ['cafe', 'cafes', 'bar', 'bars']);
                if ($cafesDistance !== null) {
                    $hotel['cafes_bars'] = true;
                    $hotel['distance_to_cafes_bars_meters'] = $cafesDistance;
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

        $jsonLdImageUrls = [];
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
            $jsonLdImageUrls = $this->extractHotelImageUrlsFromJsonLd($doc);
            break;
        }

        $galleryImageUrls = $this->extractJet2GalleryDataLazyUrls($html);
        $imageMetadata = $this->buildHotelImageMetadataList($jsonLdImageUrls, $galleryImageUrls);
        if ($imageMetadata !== null) {
            $hotel['images'] = $imageMetadata;
        }

        if (preg_match('/data-resort="([^"]+)"/i', $html, $m)) {
            $hotel['resort_name'] = html_entity_decode(trim($m[1]));
        }
        if (preg_match('/official rating:\s*([0-9]+(?:\.[0-9])?)/i', Str::lower($html), $m) && is_numeric($m[1])) {
            $hotel['star_rating'] = (int) round((float) $m[1]);
        }

        $rawLower = Str::lower(html_entity_decode($html, ENT_QUOTES | ENT_HTML5));
        $text = Str::lower(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        $this->applyAmenitySignals($hotel, $text);
        if (preg_match('/mini\\W*discos?|kids\\W*disco/i', $rawLower)) {
            $hotel['kids_disco'] = true;
        }
        if (preg_match('/\\bpromenade\\b/i', $rawLower)) {
            $hotel['promenade'] = true;
        }
        if (preg_match('/\\bharbour\\b|\\bmarina\\b/i', $rawLower)) {
            $hotel['harbour'] = true;
        }
        if (preg_match('/\\bnear\\s+shops\\b|\\bfrom\\s+shops\\b/i', $rawLower)) {
            $hotel['near_shops'] = true;
        }
        if (! isset($hotel['distance_to_shops_meters']) && preg_match('/(\d{1,4}(?:\\.\\d+)?)\\s*(km|m)\\s+from\\s+[^<]{0,80}shops?/i', $rawLower, $m)) {
            $hotel['distance_to_shops_meters'] = strtolower((string) $m[2]) === 'km'
                ? (int) round(((float) $m[1]) * 1000)
                : (int) round((float) $m[1]);
            $hotel['near_shops'] = true;
        }
        if (! isset($hotel['distance_to_cafes_bars_meters']) && preg_match('/(\d{1,4}(?:\\.\\d+)?)\\s*(km|m)\\s+from\\s+[^<]{0,80}(?:bars?|cafes?|restaurants?)/i', $rawLower, $m)) {
            $hotel['distance_to_cafes_bars_meters'] = strtolower((string) $m[2]) === 'km'
                ? (int) round(((float) $m[1]) * 1000)
                : (int) round((float) $m[1]);
            $hotel['cafes_bars'] = true;
        }
        $introSnippet = $this->extractIntroductionSnippet($html);
        if ($introSnippet !== null) {
            $hotel['introduction_snippet'] = $introSnippet;
        }
        $styleKeywords = $this->extractStyleKeywords($text);
        if ($styleKeywords !== null) {
            $hotel['style_keywords'] = $styleKeywords;
        }
        if (preg_match('/(\d+)\s*m\s+from\s+(?:the\s+)?[^.]*beach/i', $text, $m) && is_numeric($m[1])) {
            $hotel['distance_to_beach_meters'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*m\s+from\s+[^.]*resort centre/i', $text, $m) && is_numeric($m[1])) {
            $hotel['distance_to_centre_meters'] = (int) $m[1];
        }
        if (str_contains($text, 'children\'s club') || str_contains($text, 'kids club')) {
            $hotel['has_kids_club'] = true;
            if (preg_match('/(?:kids|children)[^0-9]{0,40}(\d{1,2})\s*(?:\+|to|-)\s*(\d{1,2})/i', $text, $kidsAge)) {
                $hotel['kids_club_age_min'] = (int) min((int) $kidsAge[1], (int) $kidsAge[2]);
            }
        }
        if (str_contains($text, 'waterpark') || str_contains($text, 'splash park')) {
            $hotel['has_waterpark'] = true;
        }
        if (str_contains($text, 'play area')) {
            $hotel['play_area'] = true;
        }
        if (str_contains($text, 'family room')) {
            $hotel['has_family_rooms'] = true;
        }
        if (str_contains($text, 'gym') || str_contains($text, 'fitness')) {
            $hotel['gym'] = true;
        }
        if (preg_match('/\bspa\b/i', $text)) {
            $hotel['spa'] = true;
        }
        if (str_contains($text, 'evening entertainment') || str_contains($text, 'live entertainment')) {
            $hotel['evening_entertainment'] = true;
        }
        if (preg_match('/kids\\W*disco/i', $text)
            || preg_match('/mini\\W*discos?/i', $text)) {
            $hotel['kids_disco'] = true;
        }
        if (str_contains($text, 'adults only')) {
            $hotel['adults_only_area'] = true;
        }
        if (str_contains($text, 'promenade')) {
            $hotel['promenade'] = true;
        }
        if (str_contains($text, 'harbour') || str_contains($text, 'marina')) {
            $hotel['harbour'] = true;
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
            if (preg_match('/(?:around|about|approx(?:imately)?\.?\s*)?(\d+)\s*steps?/i', $text, $steps)) {
                $hotel['steps_count'] = (int) $steps[1];
            }
        }
        if (! isset($hotel['steps_count']) && preg_match('/(?:around|about|approx(?:imately)?\.?\s*)?(\d+)\s*steps?/i', $rawLower, $steps)) {
            $hotel['steps_count'] = (int) $steps[1];
        }
        if (($hotel['accessibility_issues'] ?? null) !== null || ($hotel['steps_count'] ?? null) !== null) {
            $notes = [];
            if (($hotel['has_lift'] ?? null) === false) {
                $notes[] = 'No lift';
            }
            if (($hotel['steps_count'] ?? null) !== null) {
                $notes[] = 'Steps present ('.$hotel['steps_count'].' steps)';
            } elseif (str_contains((string) ($hotel['accessibility_issues'] ?? ''), 'steps')) {
                $notes[] = 'Steps present';
            }
            if ($notes !== []) {
                $hotel['accessibility_notes'] = implode('; ', $notes);
            }
        }
        if ($this->extractCotsAvailable($html)) {
            $hotel['cots_available'] = true;
        }
        $shopsDistance = $this->extractDistanceMetersFromText($text, ['shop', 'shops']);
        if ($shopsDistance !== null) {
            $hotel['near_shops'] = true;
            $hotel['distance_to_shops_meters'] = $shopsDistance;
        } elseif (str_contains($text, 'near shops') || str_contains($text, 'close to shops')) {
            $hotel['near_shops'] = true;
        }
        $cafesDistance = $this->extractDistanceMetersFromText($text, ['cafe', 'cafes', 'bar', 'bars']);
        if ($cafesDistance !== null) {
            $hotel['cafes_bars'] = true;
            $hotel['distance_to_cafes_bars_meters'] = $cafesDistance;
        } elseif (str_contains($text, 'cafes and bars') || str_contains($text, 'bars and restaurants')) {
            $hotel['cafes_bars'] = true;
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
        $restaurantBarCounts = $this->extractRestaurantAndBarCounts($html);
        if (($hotel['restaurants_count'] ?? null) === null && $restaurantBarCounts['restaurants'] !== null) {
            $hotel['restaurants_count'] = $restaurantBarCounts['restaurants'];
        }
        if (($hotel['bars_count'] ?? null) === null && $restaurantBarCounts['bars'] !== null) {
            $hotel['bars_count'] = $restaurantBarCounts['bars'];
        }

        $localInfo = $this->extractLocalInfo($html);
        $localBeerPrice = $this->extractCurrencyValue((string) ($localInfo['local_beer'] ?? ''), 'local beer');
        $mealForTwoPrice = $this->extractCurrencyValue((string) ($localInfo['three_course_meal_for_two'] ?? ''), 'three-course meal for two');
        $flightInfo = $this->extractFlightInfo($html);
        $outboundFlight = $this->selectCanonicalFlightWindow(
            $this->extractFlightWindow($flightInfo['outbound'] ?? null),
            $this->extractFlightWindow($candidate['raw_attributes']['outbound_flight'] ?? null)
        );
        $inboundFlight = $this->selectCanonicalFlightWindow(
            $this->extractFlightWindow($flightInfo['inbound'] ?? null),
            $this->extractFlightWindow($candidate['raw_attributes']['inbound_flight'] ?? null)
        );
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
            if ($recommendedBoard !== null) {
                $package['board_recommended'] = $recommendedBoard;
            }
            if (! isset($package['transfer_type'])) {
                $package['transfer_type'] = $this->extractTransferType($text) ?? 'coach';
            }
            if (! isset($package['flight_time_hours_est'])) {
                $package['flight_time_hours_est'] = $this->estimateFlightHours(
                    $package['outbound_flight_time_text'] ?? null,
                    $package['inbound_flight_time_text'] ?? null,
                    $text
                );
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
        $sections = $this->extractAccordionSections($html);
        foreach ($sections as $title => $items) {
            $title = Str::lower($title);
            if (! str_contains($title, 'sports') || ! str_contains($title, 'leisure')) {
                continue;
            }
            $count = count(array_filter($items, fn ($item) => trim($item) !== ''));

            return $count > 0 ? $count : null;
        }

        if (! preg_match_all('/<div[^>]*class="accordion"[^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $blocks)) {
            return null;
        }
        foreach ($blocks[1] as $block) {
            $title = Str::lower(strip_tags((string) $block));
            if (! str_contains($title, 'sports') || ! str_contains($title, 'leisure')) {
                continue;
            }
            if (preg_match('/<ul[^>]*class="accordion__list"[^>]*>(.*?)<\/ul>/is', $block, $list)
                && preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $list[1], $items)) {
                $count = 0;
                foreach ($items[1] as $item) {
                    if (trim(strip_tags((string) $item)) !== '') {
                        $count++;
                    }
                }

                return $count > 0 ? $count : null;
            }
        }

        return null;
    }

    /**
     * @return array{restaurants: ?int, bars: ?int}
     */
    private function extractRestaurantAndBarCounts(string $html): array
    {
        $sections = $this->extractAccordionSections($html);
        foreach ($sections as $title => $items) {
            $title = Str::lower($title);
            if (! str_contains($title, 'restaurant') || ! str_contains($title, 'bar')) {
                continue;
            }
            $restaurants = 0;
            $bars = 0;
            foreach ($items as $item) {
                $item = Str::lower(trim($item));
                if ($item === '') {
                    continue;
                }
                if (str_contains($item, 'bar') || str_contains($item, 'lounge')) {
                    $bars++;
                } else {
                    $restaurants++;
                }
            }

            return [
                'restaurants' => $restaurants > 0 ? $restaurants : null,
                'bars' => $bars > 0 ? $bars : null,
            ];
        }

        if (! preg_match_all('/<div[^>]*class="accordion"[^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $blocks)) {
            return ['restaurants' => null, 'bars' => null];
        }
        foreach ($blocks[1] as $block) {
            $title = Str::lower(strip_tags((string) $block));
            if (! str_contains($title, 'restaurant') || ! str_contains($title, 'bar')) {
                continue;
            }
            if (! preg_match('/<ul[^>]*class="accordion__list"[^>]*>(.*?)<\/ul>/is', $block, $list)
                || ! preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $list[1], $items)) {
                continue;
            }
            $restaurants = 0;
            $bars = 0;
            foreach ($items[1] as $itemHtml) {
                $item = Str::lower(trim(strip_tags((string) $itemHtml)));
                if ($item === '') {
                    continue;
                }
                if (str_contains($item, 'bar') || str_contains($item, 'lounge')) {
                    $bars++;

                    continue;
                }
                $restaurants++;
            }

            return [
                'restaurants' => $restaurants > 0 ? $restaurants : null,
                'bars' => $bars > 0 ? $bars : null,
            ];
        }

        return ['restaurants' => null, 'bars' => null];
    }

    private function extractRecommendedBoard(string $html, array $accommodationOptions): ?string
    {
        $codes = [];
        foreach ($accommodationOptions as $opt) {
            if (! is_array($opt)) {
                continue;
            }
            if (is_string($opt['boardId'] ?? null)) {
                $code = $this->normaliseBoardCode((string) $opt['boardId']);
                if ($code !== null) {
                    $codes[] = $code;
                }
            }
            if (is_string($opt['board'] ?? null) && trim((string) $opt['board']) !== '') {
                $code = $this->normaliseBoardCode((string) $opt['board']);
                if ($code !== null) {
                    $codes[] = $code;
                }
            }
        }
        if (preg_match_all('/overview__board-type-title[^>]*>\s*([^<]+)\s*</i', $html, $m) && $m[1] !== []) {
            $titleCodes = [];
            foreach ($m[1] as $title) {
                $code = $this->normaliseBoardCode((string) $title);
                if ($code !== null) {
                    $titleCodes[] = $code;
                }
            }
            foreach (self::BOARD_PRIORITY as $code) {
                if (in_array($code, $titleCodes, true)) {
                    return self::BOARD_LABELS[$code];
                }
            }
            $last = trim((string) end($m[1]));

            return $last !== '' ? $last : null;
        }
        foreach (self::BOARD_PRIORITY as $code) {
            if (in_array($code, $codes, true)) {
                return self::BOARD_LABELS[$code];
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function extractAccordionSections(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }
        $doc = new \DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML($html);
        libxml_clear_errors();
        if (! $loaded) {
            return [];
        }
        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " accordion ")]');
        if ($nodes === false) {
            return [];
        }
        $sections = [];
        foreach ($nodes as $node) {
            $heading = $xpath->query('.//h3[contains(concat(" ", normalize-space(@class), " "), " accordion__heading ")]', $node);
            if ($heading === false || $heading->length === 0) {
                continue;
            }
            $title = trim((string) $heading->item(0)?->textContent);
            if ($title === '') {
                continue;
            }
            $items = [];
            $lis = $xpath->query('.//ul[contains(concat(" ", normalize-space(@class), " "), " accordion__list ")]/li', $node);
            if ($lis !== false) {
                foreach ($lis as $li) {
                    $text = trim((string) $li->textContent);
                    if ($text !== '') {
                        $items[] = preg_replace('/\s+/', ' ', $text) ?? $text;
                    }
                }
            }
            $sections[$title] = $items;
        }

        return $sections;
    }

    private function normaliseBoardCode(string $value): ?string
    {
        $v = Str::upper(trim($value));
        if ($v === '') {
            return null;
        }

        return match (true) {
            isset(self::BOARD_LABELS[$v]) => $v,
            str_contains($v, 'ALL') || $v === 'AI' || $v === 'AL' => 'AI',
            str_contains($v, 'FULL') || $v === 'FB' => 'FB',
            str_contains($v, 'HALF') || $v === 'HB' => 'HB',
            str_contains($v, 'BREAKFAST') || $v === 'BB' => 'BB',
            str_contains($v, 'SELF') || $v === 'SC' => 'SC',
            str_contains($v, 'ROOM ONLY') || $v === 'RO' => 'RO',
            default => null,
        };
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

    private function selectCanonicalFlightWindow(?string $detailWindow, ?string $rawWindow): ?string
    {
        if ($detailWindow === null) {
            return $rawWindow;
        }
        if ($rawWindow === null) {
            return $detailWindow;
        }

        $detailIsTimeOnly = (bool) preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $detailWindow);
        $rawHasDateContext = mb_strlen($rawWindow) > 30;

        // Prefer immutable API flight windows when detail modal only exposes times.
        if ($detailIsTimeOnly && $rawHasDateContext) {
            return $rawWindow;
        }

        return $detailWindow;
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

    private function deriveAirportDistanceKm(string $airportCode, ?float $hotelLat, ?float $hotelLng, ?float $fallback): ?float
    {
        if ($hotelLat === null || $hotelLng === null) {
            return $fallback;
        }
        $airport = $this->airportLookup->coordinatesForIata($airportCode);
        if ($airport === null) {
            return $fallback;
        }

        $km = $this->haversineKm($hotelLat, $hotelLng, $airport['lat'], $airport['lng']);

        return round($km, 2);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $r * $c;
    }

    /**
     * @param  list<string>  $keywords
     */
    private function extractDistanceMetersFromText(string $text, array $keywords): ?int
    {
        $keywordPattern = implode('|', array_map(static fn ($k) => preg_quote($k, '/'), $keywords));
        if (preg_match('/(\d{1,4}(?:\.\d+)?)\s*(km|m)\s+from\s+[^.]{0,60}?(?:'.$keywordPattern.')/i', $text, $m)) {
            $value = (float) $m[1];
            $unit = strtolower((string) $m[2]);

            return $unit === 'km' ? (int) round($value * 1000) : (int) round($value);
        }

        return null;
    }

    private function extractTransferType(string $text): ?string
    {
        if (str_contains($text, 'private transfer')) {
            return 'private';
        }
        if (str_contains($text, 'shared transfer')) {
            return 'shared';
        }
        if (str_contains($text, 'coach transfer') || str_contains($text, 'transfer time')) {
            return 'coach';
        }

        return null;
    }

    private function estimateFlightHours(mixed $outboundWindow, mixed $inboundWindow, string $text): ?float
    {
        if (preg_match('/average flight time[^0-9]{0,10}(\d+(?:\.\d+)?)\s*hours?/i', $text, $m) && is_numeric($m[1])) {
            return round((float) $m[1], 2);
        }

        $durations = [];
        foreach ([$outboundWindow, $inboundWindow] as $window) {
            if (! is_string($window) || trim($window) === '') {
                continue;
            }
            $m = [];
            if (! preg_match('/(\d{2}):(\d{2})\s*[–-]\s*(?:[A-Za-z]{3}\s+\d{2}\s+[A-Za-z]{3}\s+\d{4}\s+)?(\d{2}):(\d{2})$/u', $window, $m)) {
                continue;
            }
            $start = ((int) $m[1] * 60) + (int) $m[2];
            $end = ((int) $m[3] * 60) + (int) $m[4];
            if ($end < $start) {
                $end += 24 * 60;
            }
            $durations[] = $end - $start;
        }
        if ($durations === []) {
            return null;
        }

        $hours = (array_sum($durations) / count($durations)) / 60;
        if ($hours >= 3.5) {
            return (float) round($hours, 0);
        }

        return round(floor($hours * 2) / 2, 2);
    }

    private function extractIntroductionSnippet(string $html): ?string
    {
        if (preg_match('/<meta[^>]+property="og:description"[^>]+content="([^"]+)"/i', $html, $m)) {
            $text = trim(html_entity_decode((string) $m[1], ENT_QUOTES | ENT_HTML5));
            if ($text !== '') {
                return Str::limit($text, 500, '');
            }
        }
        if (preg_match('/<p[^>]*class="[^"]*overview__introduction[^"]*"[^>]*>(.*?)<\/p>/is', $html, $m)) {
            $text = trim(strip_tags(html_entity_decode((string) $m[1], ENT_QUOTES | ENT_HTML5)));
            if ($text !== '') {
                return Str::limit($text, 500, '');
            }
        }
        if (preg_match('/<span[^>]*class="overview__list-text"[^>]*>(.*?)<\/span>/is', $html, $m)) {
            $text = trim(strip_tags(html_entity_decode((string) $m[1], ENT_QUOTES | ENT_HTML5)));
            if ($text !== '') {
                return Str::limit($text, 500, '');
            }
        }

        return null;
    }

    private function extractStyleKeywords(string $text): ?string
    {
        $map = [
            'family' => ['family', 'kids', 'children'],
            'luxury' => ['luxury', 'premium', 'upmarket', 'boutique'],
            'beach' => ['beachfront', 'seafront', 'beach'],
            'quiet' => ['peaceful', 'quiet', 'relaxing'],
            'lively' => ['lively', 'nightlife', 'entertainment'],
        ];
        $out = [];
        foreach ($map as $label => $terms) {
            foreach ($terms as $term) {
                if (str_contains($text, $term)) {
                    $out[] = $label;
                    break;
                }
            }
        }
        $out = array_values(array_unique($out));

        return $out === [] ? null : implode('; ', $out);
    }

    private function extractCotsAvailable(string $html): bool
    {
        if (! preg_match_all('/data-modeldata=(["\'])(.*?)\1/i', $html, $matches) || $matches[2] === []) {
            return false;
        }
        foreach ($matches[2] as $encoded) {
            $decoded = html_entity_decode((string) $encoded, ENT_QUOTES | ENT_HTML5);
            $json = json_decode($decoded, true);
            if (! is_array($json)) {
                continue;
            }
            $roomFacilities = $json['roomFacilities'] ?? [];
            if (is_array($roomFacilities)) {
                foreach ($roomFacilities as $facility) {
                    if (is_string($facility) && preg_match('/\\bcots?\\b/i', $facility)) {
                        return true;
                    }
                }
            }
            foreach (['description', 'additionalText'] as $field) {
                if (is_string($json[$field] ?? null) && preg_match('/\\bcots?\\b.{0,40}\\b(available|request|provided|hire|free)\\b/i', (string) $json[$field])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string,mixed>  $hotel
     */
    private function applyAmenitySignals(array &$hotel, string $text): void
    {
        if (str_contains($text, 'play area')) {
            $hotel['play_area'] = true;
        }
        if (str_contains($text, 'gym') || str_contains($text, 'fitness')) {
            $hotel['gym'] = true;
        }
        if (preg_match('/\bspa\b/i', $text)) {
            $hotel['spa'] = true;
        }
        if (str_contains($text, 'evening entertainment') || str_contains($text, 'live entertainment')) {
            $hotel['evening_entertainment'] = true;
        }
        if (str_contains($text, 'kids disco') || str_contains($text, "kids' disco") || str_contains($text, 'mini disco')) {
            $hotel['kids_disco'] = true;
        }
        if (str_contains($text, 'adults only')) {
            $hotel['adults_only_area'] = true;
        }
        if (str_contains($text, 'promenade')) {
            $hotel['promenade'] = true;
        }
        if (str_contains($text, 'harbour') || str_contains($text, 'marina')) {
            $hotel['harbour'] = true;
        }
        if (str_contains($text, 'near shops') || str_contains($text, 'close to shops')) {
            $hotel['near_shops'] = true;
        }
        if (str_contains($text, 'cafes and bars') || str_contains($text, 'bars and restaurants')) {
            $hotel['cafes_bars'] = true;
        }
        if (preg_match('/(?:kids|children)[^0-9]{0,40}(\d{1,2})\s*-\s*(\d{1,2})\s*yrs?/i', $text, $kidsAge)) {
            $minAge = (int) min((int) $kidsAge[1], (int) $kidsAge[2]);
            if ($minAge >= 2 && $minAge <= 17) {
                $hotel['kids_club_age_min'] = $minAge;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return list<string>
     */
    private function extractHotelImageUrlsFromJsonLd(array $doc): array
    {
        if (! isset($doc['image'])) {
            return [];
        }
        $raw = $doc['image'];
        if (is_string($raw) && filter_var($raw, FILTER_VALIDATE_URL)) {
            return [$raw];
        }
        if (! is_array($raw)) {
            return [];
        }
        if (isset($raw['url']) && is_string($raw['url']) && filter_var($raw['url'], FILTER_VALIDATE_URL)) {
            return [$raw['url']];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
                $out[] = $item;
            } elseif (is_array($item) && isset($item['url']) && is_string($item['url']) && filter_var($item['url'], FILTER_VALIDATE_URL)) {
                $out[] = $item['url'];
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function extractJet2GalleryDataLazyUrls(string $html): array
    {
        if (! preg_match_all('/<img\b[^>]*?class="[^"]*image-galleryV2__fullimage[^"]*"[^>]*>/i', $html, $tags, PREG_SET_ORDER)) {
            return [];
        }
        $urls = [];
        $seen = [];
        foreach ($tags as $tagMatch) {
            $tag = $tagMatch[0];
            if (! preg_match('/\bdata-lazy="([^"]+)"/i', $tag, $m)) {
                continue;
            }
            $u = $m[1];
            if (! is_string($u) || ! filter_var($u, FILTER_VALIDATE_URL) || isset($seen[$u])) {
                continue;
            }
            $seen[$u] = true;
            $urls[] = $u;
        }

        return $urls;
    }

    /**
     * @param  list<string>  $jsonLdUrls
     * @param  list<string>  $galleryUrls
     * @return list<array{url: string, source: string, position: int}>|null
     */
    private function buildHotelImageMetadataList(array $jsonLdUrls, array $galleryUrls): ?array
    {
        $seen = [];
        $out = [];
        $pos = 0;
        foreach ($jsonLdUrls as $u) {
            if (isset($seen[$u])) {
                continue;
            }
            $seen[$u] = true;
            $out[] = ['url' => $u, 'source' => 'jet2_json_ld', 'position' => $pos++];
        }
        foreach ($galleryUrls as $u) {
            if (isset($seen[$u])) {
                continue;
            }
            $seen[$u] = true;
            $out[] = ['url' => $u, 'source' => 'jet2_gallery', 'position' => $pos++];
        }

        return $out === [] ? null : $out;
    }
}
