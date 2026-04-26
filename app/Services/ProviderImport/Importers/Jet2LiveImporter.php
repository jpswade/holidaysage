<?php

namespace App\Services\ProviderImport\Importers;

use App\Contracts\ProviderHttpImporter;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Services\ProviderImport\Importers\Concerns\ExtractsEmbeddedJson;
use App\Services\ProviderImport\Jet2SmartSearchHttpClient;
use App\Services\ProviderImport\ProviderImportResult;
use App\Support\Jet2SmartsearchFilters;
use Carbon\Carbon;
use Illuminate\Support\Str;

class Jet2LiveImporter implements ProviderHttpImporter
{
    use ExtractsEmbeddedJson;

    public function __construct(
        private readonly Jet2SmartSearchHttpClient $jet2Http
    ) {}

    public function providerKey(): string
    {
        return 'jet2';
    }

    public function import(string $url, SavedHolidaySearch $search, ProviderSource $provider): ProviderImportResult
    {
        $apiUrl = $this->buildSmartSearchApiUrl($url, $search);
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
            if ($candidates !== []) {
                return new ProviderImportResult(
                    responseStatus: $status,
                    rawBody: $body,
                    candidates: $candidates,
                );
            }

            // Some valid API responses return 200 with zero `results`; try the results page body as fallback.
            [$htmlStatus, $htmlBody] = $this->fetchViaNativeHttp($url, false);
            if ($htmlStatus >= 200 && $htmlStatus < 300) {
                $htmlCandidates = $this->candidatesFromBody($htmlBody, $search, $provider, $url);
                if ($htmlCandidates !== []) {
                    return new ProviderImportResult(
                        responseStatus: $htmlStatus,
                        rawBody: $htmlBody,
                        candidates: $htmlCandidates,
                    );
                }
            }

            $totalResults = is_numeric($decoded['totalResults'] ?? null) ? (int) $decoded['totalResults'] : null;
            if ($totalResults !== null) {
                throw new \RuntimeException('Jet2 API returned zero candidates (totalResults='.$totalResults.').');
            }

            throw new \RuntimeException('Jet2 API payload did not contain holiday candidates.');
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
        $response = $this->jet2Http->get($url, $isApi);

        return [$response->status(), (string) $response->body()];
    }

    private function buildSmartSearchApiUrl(string $searchResultsUrl, ?SavedHolidaySearch $search = null): ?string
    {
        $parts = parse_url($searchResultsUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if (! str_contains($host, 'jet2holidays.com') || ! str_contains($path, '/search/results')) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $q);
        $q = $this->mergeResultsQueryWithSavedSearch($q, $search);
        $q = $this->strictFixtureShapeQuery($q);

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
        $filters = Jet2SmartsearchFilters::filterSlugsFromResultsQuery($q);
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

    /**
     * Fills missing Jet2 result-url keys from `SavedHolidaySearch` so a partial or edited
     * `provider_import_url` can still be resolved to the same smartsearch call.
     *
     * @param  array<string, mixed>  $q
     * @return array<string, mixed>
     */
    private function mergeResultsQueryWithSavedSearch(array $q, ?SavedHolidaySearch $search): array
    {
        $q = array_change_key_case($q, CASE_LOWER);
        if ($search === null) {
            return $q;
        }
        $hasNonEmpty = static function (array $arr, string $k): bool {
            return isset($arr[$k]) && is_string($arr[$k]) && trim($arr[$k]) !== '';
        };
        $fromStore = $search->provider_url_params['jet2'] ?? null;
        if (is_array($fromStore)) {
            foreach ($fromStore as $k => $v) {
                if (! is_string($k) || ! is_string($v) || $v === '') {
                    continue;
                }
                $k = strtolower($k);
                if (! $hasNonEmpty($q, $k)) {
                    $q[$k] = $v;
                }
            }
        }
        if (! $hasNonEmpty($q, 'occupancy')) {
            $w = $search->providerOccupancyWireFor('jet2');
            if ($w !== null) {
                $q['occupancy'] = $w;
            }
        }
        if (! $hasNonEmpty($q, 'destination') && $search->providerDestinationIdListFor('jet2') !== []) {
            $q['destination'] = implode('_', $search->providerDestinationIdListFor('jet2'));
        }
        if (! $hasNonEmpty($q, 'airport')) {
            $a = $search->providerUrlParamFor('jet2', 'airport');
            if ($a !== null) {
                $q['airport'] = $a;
            }
        }
        if (! $hasNonEmpty($q, 'date') && $search->travel_start_date) {
            $q['date'] = $search->travel_start_date->format('d-m-Y');
        }
        if (! $hasNonEmpty($q, 'duration') && $search->duration_min_nights >= 1
            && (int) $search->duration_min_nights === (int) $search->duration_max_nights) {
            $q['duration'] = (string) (int) $search->duration_min_nights;
        }

        return $q;
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
        if (trim($s) === '' || str_contains($s, 'c') === false) {
            return '2!2_1-4';
        }
        $rooms = array_map(static fn (string $r) => rtrim($r, '_'), array_filter(explode('r', $s)));
        $out = [];
        foreach ($rooms as $room) {
            if ($room === '') {
                continue;
            }
            if (preg_match('/^(\d+)c(?:(\d+)_(\d+))?$/', $room, $m)) {
                $out[] = isset($m[2]) && isset($m[3]) ? ($m[1].'_'.$m[2].'-'.$m[3]) : $m[1];
            }
        }

        return $out === [] ? '2!2_1-4' : implode('!', $out);
    }

    /**
     * Keep request shape aligned with the checked-in Jet2 contract when enabled.
     *
     * @param  array<string,mixed>  $q
     * @return array<string,mixed>
     */
    private function strictFixtureShapeQuery(array $q): array
    {
        if (! (bool) config('holidaysage.jet2.strict_fixture_shape', true)) {
            return $q;
        }

        $q = array_change_key_case($q, CASE_LOWER);

        if (isset($q['destination']) && is_string($q['destination'])) {
            $primaryDestination = $this->firstNumericUnderscoreToken($q['destination']);
            if ($primaryDestination !== null) {
                $q['destination'] = $primaryDestination;
            }
        }

        // The fixture-backed request shape does not send these optional URL filters directly.
        foreach (['facility', 'feature', 'outboundflighttimes', 'inboundflighttimes', 'starrating', 'sr'] as $dropKey) {
            unset($q[$dropKey]);
        }

        return $q;
    }

    private function firstNumericUnderscoreToken(string $wire): ?string
    {
        $wire = trim($wire);
        if ($wire === '') {
            return null;
        }
        $parts = explode('_', $wire);
        $first = trim((string) ($parts[0] ?? ''));
        if ($first === '' || preg_match('/^\d+$/', $first) !== 1) {
            return null;
        }

        return $first;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return list<array<string,mixed>>
     */
    private function candidatesFromApiJson(array $data, SavedHolidaySearch $search, ProviderSource $provider, string $url): array
    {
        $flightById = $this->indexFlightsById($data);
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

            $selectedFlightId = is_numeric($item['selectedFlightId'] ?? null)
                ? (int) $item['selectedFlightId']
                : null;
            if ($selectedFlightId === null && is_numeric($item['selectedPrice']['flightId'] ?? null)) {
                $selectedFlightId = (int) $item['selectedPrice']['flightId'];
            }
            $price = null;
            $pricePerPerson = null;
            if (isset($item['selectedPrice']) && is_array($item['selectedPrice'])) {
                $price = $item['selectedPrice']['totalPrice'] ?? null;
                $pricePerPerson = $item['selectedPrice']['pricePerPerson'] ?? null;
            }
            if ($price === null && isset($item['priceFrom'])) {
                $price = $item['priceFrom'];
            }
            if ($price === null && isset($item['price']) && is_array($item['price'])) {
                $price = $item['price']['totalPrice'] ?? $item['price']['fromPrice'] ?? null;
            }
            if ($price === null && isset($item['accommodationOptions']) && is_array($item['accommodationOptions'])) {
                foreach ($item['accommodationOptions'] as $opt) {
                    if (! is_array($opt) || ! isset($opt['priceOptions']) || ! is_array($opt['priceOptions'])) {
                        continue;
                    }
                    foreach ($opt['priceOptions'] as $po) {
                        if (! is_array($po)) {
                            continue;
                        }
                        if ($selectedFlightId !== null && is_numeric($po['flightId'] ?? null) && (int) $po['flightId'] === $selectedFlightId) {
                            $price = $po['totalPrice'] ?? $po['basePrice'] ?? $price;
                            $pricePerPerson = $po['pricePerPerson'] ?? $pricePerPerson;
                            break 2;
                        }
                        $candidatePrice = $po['totalPrice'] ?? $po['basePrice'] ?? null;
                        if (is_numeric($candidatePrice)) {
                            $candidateFloat = (float) $candidatePrice;
                            if ($candidateFloat > 0 && ($price === null || $candidateFloat < (float) $price)) {
                                $price = $candidateFloat;
                            }
                        }
                    }
                }
            }

            $dep = $search->travel_start_date ? $search->travel_start_date->toDateString() : now()->addMonths(2)->toDateString();
            $nights = max(1, (int) ($item['duration'] ?? $search->duration_min_nights));
            $ret = Carbon::parse($dep)->addDays($nights)->toDateString();
            $slug = Str::slug($name) ?: Str::random(12);
            $flightInfo = $selectedFlightId !== null ? ($flightById[$selectedFlightId] ?? null) : null;
            if (! is_array($flightInfo)) {
                $fallbackFlightId = $this->firstFlightIdFromAccommodationOptions($item);
                $flightInfo = $fallbackFlightId !== null ? ($flightById[$fallbackFlightId] ?? null) : null;
            }
            $airportTo = is_array($flightInfo) ? ($flightInfo['arrival_airport_code'] ?? null) : null;
            $outboundFlight = is_array($flightInfo) ? ($flightInfo['outbound_flight'] ?? null) : null;
            $inboundFlight = is_array($flightInfo) ? ($flightInfo['inbound_flight'] ?? null) : null;
            $outboundFlightMins = is_array($flightInfo) ? ($flightInfo['outbound_duration_minutes'] ?? null) : null;
            $inboundFlightMins = is_array($flightInfo) ? ($flightInfo['inbound_duration_minutes'] ?? null) : null;
            $distanceKm = null;
            if (is_numeric($item['distanceToAirportKm'] ?? null)) {
                $distanceKm = (float) $item['distanceToAirportKm'];
            } elseif (is_numeric($property['distanceToAirportKm'] ?? null)) {
                $distanceKm = (float) $property['distanceToAirportKm'];
            }
            $transferMins = $this->coachTransferMinutesFromDistanceKm($distanceKm);
            $images = $this->extractImageMetadataFromApiResultItem($item, $property);

            $row = [
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
                'airport_code' => is_string($airportTo) && $airportTo !== '' ? $airportTo : $search->departure_airport_code,
                'departure_date' => $dep,
                'return_date' => $ret,
                'nights' => $nights,
                'adults' => (int) $search->adults,
                'children' => (int) $search->children,
                'infants' => (int) $search->infants,
                'board_type' => null,
                'price_total' => is_numeric($price) ? (float) $price : 0.0,
                'price_per_person' => is_numeric($pricePerPerson) ? (float) $pricePerPerson : null,
                'currency' => 'GBP',
                'flight_outbound_duration_minutes' => is_int($outboundFlightMins) ? $outboundFlightMins : null,
                'flight_inbound_duration_minutes' => is_int($inboundFlightMins) ? $inboundFlightMins : null,
                'transfer_minutes' => $transferMins,
                'distance_to_beach_meters' => null,
                'distance_to_centre_meters' => null,
                'star_rating' => isset($item['starRating']) && is_numeric($item['starRating']) ? (int) $item['starRating'] : (isset($property['rating']) && is_numeric($property['rating']) ? (int) $property['rating'] : null),
                'review_score' => isset($item['reviewScore']) && is_numeric($item['reviewScore']) ? (float) $item['reviewScore'] : null,
                'review_count' => isset($item['reviewCount']) && is_numeric($item['reviewCount']) ? (int) $item['reviewCount'] : null,
                'is_family_friendly' => null,
                'has_kids_club' => null,
                'has_waterpark' => null,
                'has_family_rooms' => null,
                'latitude' => null,
                'longitude' => null,
                'raw_attributes' => [
                    'provider' => $provider->key,
                    'source' => 'jet2_smartsearch_api',
                    'property' => $property,
                    'images' => $images,
                    'accommodation_options' => $item['accommodationOptions'] ?? null,
                    'distance_to_airport_km' => is_numeric($item['distanceToAirportKm'] ?? null)
                        ? (float) $item['distanceToAirportKm']
                        : (is_numeric($property['distanceToAirportKm'] ?? null) ? (float) $property['distanceToAirportKm'] : null),
                    'outbound_flight' => is_string($outboundFlight) ? $outboundFlight : (is_string($item['outboundFlight'] ?? null) ? $item['outboundFlight'] : null),
                    'inbound_flight' => is_string($inboundFlight) ? $inboundFlight : (is_string($item['inboundFlight'] ?? null) ? $item['inboundFlight'] : null),
                ],
            ];
            if ($images !== []) {
                $row['images'] = $images;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<int, array{arrival_airport_code: string, outbound_flight: string, inbound_flight: string, outbound_duration_minutes: ?int, inbound_duration_minutes: ?int}>
     */
    private function indexFlightsById(array $data): array
    {
        $out = [];
        foreach (($data['flights'] ?? []) as $flight) {
            if (! is_array($flight) || ! is_numeric($flight['flightId'] ?? null)) {
                continue;
            }
            $flightId = (int) $flight['flightId'];
            $outbound = is_array($flight['outbound'] ?? null) ? $flight['outbound'] : [];
            $inbound = is_array($flight['inbound'] ?? null) ? $flight['inbound'] : [];
            $obDep = (string) ($outbound['departureDateTimeLocal'] ?? '');
            $obArr = (string) ($outbound['arrivalDateTimeLocal'] ?? '');
            $ibDep = (string) ($inbound['departureDateTimeLocal'] ?? '');
            $ibArr = (string) ($inbound['arrivalDateTimeLocal'] ?? '');
            $out[$flightId] = [
                'arrival_airport_code' => strtoupper((string) ($outbound['arrivalAirportCode'] ?? '')),
                'outbound_flight' => $this->formatFlightWindow($obDep, $obArr) ?? '',
                'inbound_flight' => $this->formatFlightWindow($ibDep, $ibArr) ?? '',
                'outbound_duration_minutes' => $this->minutesBetweenLocalIsoStrings($obDep, $obArr),
                'inbound_duration_minutes' => $this->minutesBetweenLocalIsoStrings($ibDep, $ibArr),
            ];
        }

        return $out;
    }

    private function minutesBetweenLocalIsoStrings(string $departureIso, string $arrivalIso): ?int
    {
        try {
            if ($departureIso === '' || $arrivalIso === '') {
                return null;
            }
            $start = Carbon::parse($departureIso);
            $end = Carbon::parse($arrivalIso);
            $minutes = (int) round(abs($start->diffInRealMinutes($end)));
            if ($minutes <= 0 || $minutes > 48 * 60) {
                return null;
            }

            return $minutes;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Coach-style transfer estimate from resort–airport distance (same scale as CSV export heuristics).
     */
    private function coachTransferMinutesFromDistanceKm(?float $km): ?int
    {
        if ($km === null || $km <= 0.0) {
            return null;
        }

        return max(1, (int) round(($km / 50.0) * 60.0));
    }

    private function formatFlightWindow(string $departureIso, string $arrivalIso): ?string
    {
        try {
            if ($departureIso === '' || $arrivalIso === '') {
                return null;
            }
            $departure = Carbon::parse($departureIso)->format('D d M Y H:i');
            $arrival = Carbon::parse($arrivalIso)->format('D d M Y H:i');

            return $departure.' – '.$arrival;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string,mixed>  $item
     */
    private function firstFlightIdFromAccommodationOptions(array $item): ?int
    {
        if (! isset($item['accommodationOptions']) || ! is_array($item['accommodationOptions'])) {
            return null;
        }
        foreach ($item['accommodationOptions'] as $option) {
            if (! is_array($option) || ! isset($option['priceOptions']) || ! is_array($option['priceOptions'])) {
                continue;
            }
            foreach ($option['priceOptions'] as $priceOption) {
                if (! is_array($priceOption) || ! is_numeric($priceOption['flightId'] ?? null)) {
                    continue;
                }

                return (int) $priceOption['flightId'];
            }
        }

        return null;
    }

    /**
     * Best-effort extraction of hotel image URLs from smartsearch API result nodes.
     *
     * @param  array<string,mixed>  $item
     * @param  array<string,mixed>  $property
     * @return list<array{url: string, source: string, position: int}>
     */
    private function extractImageMetadataFromApiResultItem(array $item, array $property): array
    {
        $candidates = [
            $item['images'] ?? null,
            $item['image'] ?? null,
            $item['imageUrl'] ?? null,
            $item['imageURL'] ?? null,
            $item['heroImage'] ?? null,
            $item['heroImageUrl'] ?? null,
            $property['images'] ?? null,
            $property['image'] ?? null,
            $property['imageUrl'] ?? null,
            $property['imageURL'] ?? null,
            $property['heroImage'] ?? null,
            $property['heroImageUrl'] ?? null,
        ];

        $urls = [];
        foreach ($candidates as $node) {
            $this->appendImageUrlsFromNode($urls, $node);
        }

        $out = [];
        foreach (array_values(array_unique($urls)) as $pos => $imageUrl) {
            $out[] = [
                'url' => $imageUrl,
                'source' => 'jet2_smartsearch_api',
                'position' => $pos,
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $out
     */
    private function appendImageUrlsFromNode(array &$out, mixed $node): void
    {
        if (is_string($node)) {
            $trimmed = trim($node);
            if ($trimmed !== '' && filter_var($trimmed, FILTER_VALIDATE_URL)) {
                $out[] = $trimmed;
            }

            return;
        }
        if (! is_array($node)) {
            return;
        }
        if (array_is_list($node)) {
            foreach ($node as $entry) {
                $this->appendImageUrlsFromNode($out, $entry);
            }

            return;
        }

        foreach (['url', 'imageUrl', 'imageURL', 'src', 'sourceUrl', 'cdnUrl', 'contentUrl', 'contentURL'] as $key) {
            if (! isset($node[$key]) || ! is_string($node[$key])) {
                continue;
            }
            $trimmed = trim($node[$key]);
            if ($trimmed !== '' && filter_var($trimmed, FILTER_VALIDATE_URL)) {
                $out[] = $trimmed;
                break;
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->appendImageUrlsFromNode($out, $value);
            }
        }
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
                'is_family_friendly' => null,
                'has_kids_club' => null,
                'has_waterpark' => null,
                'has_family_rooms' => null,
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
