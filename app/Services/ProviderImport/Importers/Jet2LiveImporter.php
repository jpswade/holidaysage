<?php

namespace App\Services\ProviderImport\Importers;

use App\Contracts\ProviderHttpImporter;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Services\ProviderImport\Importers\Concerns\ExtractsEmbeddedJson;
use App\Services\ProviderImport\ProviderImportResult;
use Carbon\Carbon;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Jet2LiveImporter implements ProviderHttpImporter
{
    use ExtractsEmbeddedJson;

    public function providerKey(): string
    {
        return 'jet2';
    }

    public function import(string $url, SavedHolidaySearch $search, ProviderSource $provider): ProviderImportResult
    {
        $apiUrl = $this->buildSmartSearchApiUrl($url);
        if ($apiUrl !== null) {
            [$status, $body] = $this->fetchViaNativeHttp($apiUrl, true);
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException('Jet2 API HTTP '.$status.' for '.$apiUrl);
            }
            $decoded = json_decode($body, true);
            if (! is_array($decoded)) {
                throw new \RuntimeException('Jet2 API returned invalid JSON payload.');
            }
            $candidates = $this->candidatesFromApiJson($decoded, $search, $provider, $url);
            if ($candidates === []) {
                throw new \RuntimeException('Jet2 API payload did not contain holiday candidates.');
            }

            return new ProviderImportResult(
                responseStatus: $status,
                rawBody: $body,
                candidates: $candidates,
            );
        }

        [$status, $body] = $this->fetchViaNativeHttp($url, false);
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Jet2 HTTP '.$status.' for '.$url);
        }

        $candidates = $this->candidatesFromBody($body, $search, $provider, $url);
        if ($candidates === []) {
            throw new \RuntimeException('Jet2 payload did not contain parsable holiday candidates.');
        }

        return new ProviderImportResult(
            responseStatus: $status,
            rawBody: $body,
            candidates: $candidates,
        );
    }

    /**
     * @return array{0:int,1:string}
     */
    private function fetchViaNativeHttp(string $url, bool $isApi): array
    {
        $response = $this->requestWithBrowserHeaders($url, $isApi);

        return [$response->status(), (string) $response->body()];
    }

    private function requestWithBrowserHeaders(string $url, bool $isApi): Response
    {
        $handler = HandlerStack::create(new StreamHandler);
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
            'Accept-Language' => 'en-GB,en-US;q=0.9,en;q=0.8,pt;q=0.7',
            'Cache-Control' => 'max-age=0',
            'DNT' => '1',
            'Sec-Ch-Ua' => '"Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"macOS"',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
        ];
        $headers['Accept'] = $isApi
            ? 'application/json, text/javascript, */*; q=0.01'
            : 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7';

        return Http::retry([300, 1000, 2000], throw: false)
            ->withHeaders($headers)
            ->connectTimeout(10)
            ->timeout(20)
            ->withOptions([
                'handler' => $handler,
            ])
            ->get($url);
    }

    private function buildSmartSearchApiUrl(string $searchResultsUrl): ?string
    {
        $parts = parse_url($searchResultsUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if (! str_contains($host, 'jet2holidays.com') || ! str_contains($path, '/search/results')) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $q);
        $airport = (string) ($q['airport'] ?? '');
        $destination = (string) ($q['destination'] ?? '');
        if ($airport === '' || $destination === '') {
            return null;
        }

        $date = $this->ddmmyyyyToIso((string) ($q['date'] ?? ''));
        $duration = (string) ($q['duration'] ?? '10');
        $occupancy = $this->occupancyToApi((string) ($q['occupancy'] ?? 'r2c_r2c1_4'));
        $page = (string) ($q['page'] ?? '1');
        $sortorder = (string) ($q['sortorder'] ?? '1');
        $boardbasis = str_replace('_', '-', (string) ($q['boardbasis'] ?? '5_2_3'));
        $facility = str_replace('_', '-', (string) ($q['facility'] ?? ''));

        $filters = ['boardbasis_'.$boardbasis, 'starrating_4'];
        if ($facility !== '') {
            $filters[] = 'facility_'.$facility;
        }
        $filters[] = 'inboundflighttimes_2-3';
        $filters[] = 'outboundflighttimes_2-3';

        $params = http_build_query([
            'departureAirportIds' => $airport,
            'destinationAreaIds' => $destination,
            'departureDate' => $date ?: '2026-07-25',
            'durations' => $duration,
            'occupancies' => $occupancy,
            'pageNumber' => $page,
            'pageSize' => '24',
            'sortOrder' => $sortorder,
            'filters' => implode('!', $filters),
            'holidayTypeId' => '0',
            'flexibility' => '7',
            'minPrice' => '',
            'includePriceBreakDown' => 'false',
            'applyDiscount' => 'true',
        ]);

        return 'https://www.jet2holidays.com/api/jet2/smartsearch/search?'.$params;
    }

    private function ddmmyyyyToIso(string $d): string
    {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $d, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        return $d;
    }

    private function occupancyToApi(string $s): string
    {
        if (! str_contains($s, 'r')) {
            return '2!2_1-4';
        }
        $rooms = array_filter(explode('r', $s));
        $out = [];
        foreach ($rooms as $room) {
            if (preg_match('/^(\d+)c(?:(\d+)_(\d+))?$/', $room, $m)) {
                $out[] = isset($m[2]) && isset($m[3]) ? ($m[1].'_'.$m[2].'-'.$m[3]) : $m[1];
            }
        }

        return $out === [] ? '2!2_1-4' : implode('!', $out);
    }

    /**
     * @param  array<string,mixed>  $data
     * @return list<array<string,mixed>>
     */
    private function candidatesFromApiJson(array $data, SavedHolidaySearch $search, ProviderSource $provider, string $url): array
    {
        $rows = [];
        foreach (($data['results'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $property = isset($item['property']) && is_array($item['property']) ? $item['property'] : [];
            $name = (string) ($item['name'] ?? $property['name'] ?? '');
            $itemUrl = (string) ($item['bookingUrl'] ?? $item['url'] ?? $property['url'] ?? $url);
            if ($name === '' || $itemUrl === '') {
                continue;
            }

            $price = null;
            if (isset($item['selectedPrice']) && is_array($item['selectedPrice'])) {
                $price = $item['selectedPrice']['totalPrice'] ?? null;
            }
            if ($price === null && isset($item['priceFrom'])) {
                $price = $item['priceFrom'];
            }
            if ($price === null && isset($item['price']) && is_array($item['price'])) {
                $price = $item['price']['totalPrice'] ?? $item['price']['fromPrice'] ?? null;
            }

            $dep = $search->travel_start_date ? $search->travel_start_date->toDateString() : now()->addMonths(2)->toDateString();
            $nights = max(1, (int) ($item['duration'] ?? $search->duration_min_nights));
            $ret = Carbon::parse($dep)->addDays($nights)->toDateString();
            $slug = Str::slug($name) ?: Str::random(12);

            $rows[] = [
                'provider_option_id' => 'jet2-'.substr(sha1($itemUrl.'|'.$name), 0, 12),
                'provider_hotel_id' => (string) ($item['hotelId'] ?? $property['id'] ?? ''),
                'provider_url' => $itemUrl,
                'hotel_name' => $name,
                'hotel_slug' => $slug,
                'resort_name' => (string) ($item['resortName'] ?? $property['resort'] ?? ''),
                'destination_name' => (string) ($item['destinationName'] ?? $property['area'] ?? ($search->destination_preferences[0] ?? 'Unknown destination')),
                'destination_country' => $this->resolveDestinationCountry(
                    $item['destinationCountry'] ?? $property['country'] ?? null,
                    $itemUrl
                ),
                'airport_code' => $search->departure_airport_code,
                'departure_date' => $dep,
                'return_date' => $ret,
                'nights' => $nights,
                'adults' => (int) $search->adults,
                'children' => (int) $search->children,
                'infants' => (int) $search->infants,
                'board_type' => null,
                'price_total' => is_numeric($price) ? (float) $price : 0.0,
                'price_per_person' => null,
                'currency' => 'GBP',
                'flight_outbound_duration_minutes' => null,
                'flight_inbound_duration_minutes' => null,
                'transfer_minutes' => null,
                'distance_to_beach_meters' => null,
                'distance_to_centre_meters' => null,
                'star_rating' => isset($item['starRating']) && is_numeric($item['starRating']) ? (int) $item['starRating'] : (isset($property['rating']) && is_numeric($property['rating']) ? (int) $property['rating'] : null),
                'review_score' => isset($item['reviewScore']) && is_numeric($item['reviewScore']) ? (float) $item['reviewScore'] : null,
                'review_count' => isset($item['reviewCount']) && is_numeric($item['reviewCount']) ? (int) $item['reviewCount'] : null,
                'is_family_friendly' => (bool) ($search->children > 0),
                'has_kids_club' => false,
                'has_waterpark' => false,
                'has_family_rooms' => false,
                'latitude' => null,
                'longitude' => null,
                'raw_attributes' => [
                    'provider' => $provider->key,
                    'source' => 'jet2_smartsearch_api',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function candidatesFromBody(string $body, SavedHolidaySearch $search, ProviderSource $provider, string $url): array
    {
        $docs = $this->extractJsonDocuments($body);
        $candidates = [];

        foreach ($docs as $doc) {
            $name = $doc['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }
            $reviewScore = $doc['aggregateRating']['ratingValue'] ?? null;
            $reviewCount = $doc['aggregateRating']['reviewCount'] ?? null;
            $price = $doc['offers']['price'] ?? null;
            $currency = $doc['offers']['priceCurrency'] ?? 'GBP';

            $dep = $search->travel_start_date ? $search->travel_start_date->toDateString() : now()->addMonths(2)->toDateString();
            $nights = max(1, (int) $search->duration_min_nights);
            $ret = Carbon::parse($dep)->addDays($nights)->toDateString();

            $slug = Str::slug($name) ?: Str::random(12);
            $candidates[] = [
                'provider_option_id' => 'jet2-'.substr(sha1($url.'|'.$name), 0, 12),
                'provider_hotel_id' => null,
                'provider_url' => is_string($doc['url'] ?? null) ? $doc['url'] : $url,
                'hotel_name' => $name,
                'hotel_slug' => $slug,
                'resort_name' => null,
                'destination_name' => $search->destination_preferences[0] ?? 'Unknown destination',
                'destination_country' => $this->resolveDestinationCountry(
                    $doc['address']['addressCountry'] ?? null,
                    is_string($doc['url'] ?? null) ? $doc['url'] : $url
                ),
                'airport_code' => $search->departure_airport_code,
                'departure_date' => $dep,
                'return_date' => $ret,
                'nights' => $nights,
                'adults' => (int) $search->adults,
                'children' => (int) $search->children,
                'infants' => (int) $search->infants,
                'board_type' => null,
                'price_total' => is_numeric($price) ? (float) $price : 0.0,
                'price_per_person' => null,
                'currency' => is_string($currency) ? strtoupper($currency) : 'GBP',
                'flight_outbound_duration_minutes' => null,
                'flight_inbound_duration_minutes' => null,
                'transfer_minutes' => null,
                'distance_to_beach_meters' => null,
                'distance_to_centre_meters' => null,
                'star_rating' => null,
                'review_score' => is_numeric($reviewScore) ? (float) $reviewScore : null,
                'review_count' => is_numeric($reviewCount) ? (int) $reviewCount : null,
                'is_family_friendly' => (bool) ($search->children > 0),
                'has_kids_club' => false,
                'has_waterpark' => false,
                'has_family_rooms' => false,
                'latitude' => null,
                'longitude' => null,
                'raw_attributes' => [
                    'provider' => $provider->key,
                    'source' => 'live_http_ld_json',
                ],
            ];
        }

        return $candidates;
    }

    private function resolveDestinationCountry(mixed $rawCountry, string $providerUrl): ?string
    {
        if (is_string($rawCountry)) {
            $rawCountry = trim($rawCountry);
            if ($rawCountry !== '' && strcasecmp($rawCountry, 'unknown') !== 0) {
                return $rawCountry;
            }
        }

        $path = parse_url($providerUrl, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        if (count($parts) >= 2 && strtolower($parts[0]) === 'beach') {
            $segment = strtolower($parts[1]);

            return match ($segment) {
                'spain' => 'Spain',
                'balearics' => 'Spain',
                'canary-islands' => 'Spain',
                'malta' => 'Malta',
                'croatia' => 'Croatia',
                'greece' => 'Greece',
                default => Str::of($segment)->replace('-', ' ')->title()->value(),
            };
        }

        return null;
    }
}
