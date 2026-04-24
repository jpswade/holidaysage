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
            }
        }

        $accommodationOptions = is_array($candidate['raw_attributes']['accommodation_options'] ?? null)
            ? $candidate['raw_attributes']['accommodation_options']
            : [];
        foreach ($accommodationOptions as $opt) {
            if (! is_array($opt) || ! is_array($opt['priceOptions'] ?? null)) {
                continue;
            }
            $boardId = isset($opt['boardId']) ? (string) $opt['boardId'] : '';
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

        // Optional package variants can be emitted by future parser improvements.
        return [
            'hotel' => $hotel,
            'packages' => $packages,
        ];
    }
}
