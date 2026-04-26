<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\Parsers\Jet2ImportUrlParser;
use App\Services\ProviderImport\Importers\Jet2LiveImporter;
use App\Support\Jet2OccupancyQuery;
use App\Support\SearchFormPrefill;
use Illuminate\Http\Request;
use Tests\Support\Jet2UrlContract;
use Tests\TestCase;

/**
 * Contract tests: the search URL in {@see tests/Fixtures/jet2_search_url_contract.json} must stay
 * consistent with the checked-in smartsearch JSON in {@see tests/Fixtures/jet2_search_api_airport98_page1.json}
 * (same capture session — departure date in the URL matches the first outbound flight in the API file).
 */
class Jet2SearchUrlContractTest extends TestCase
{
    public function test_command_url_matches_api_fixture_departure_date_and_parsed_criteria(): void
    {
        $url = Jet2UrlContract::forCommandAndPrefill();
        $api = $this->loadApiFixture();
        $outLocal = (string) data_get($api, 'flights.0.outbound.departureDateTimeLocal');
        $this->assertNotSame('', $outLocal, 'Real API fixture must include flights[0].outbound.departureDateTimeLocal');
        $fromApi = substr($outLocal, 0, 10);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        $parser = new Jet2ImportUrlParser;
        $criteria = $parser->parse($url);
        $this->assertSame(
            ['jet2' => (string) $q['occupancy']],
            $criteria['provider_occupancy'] ?? null,
        );
        $this->assertSame($fromApi, $criteria['travel_start_date'] ?? null, 'Import parser date must match API fixture');

        $totals = Jet2OccupancyQuery::totals((string) $q['occupancy']);
        $this->assertNotNull($totals);
        $this->assertSame(4, $totals['adults']);
        $this->assertSame(2, $totals['children']);
        $this->assertSame($totals['adults'], $criteria['adults'] ?? null);
        $this->assertSame($totals['children'], $criteria['children'] ?? null);
        $this->assertSame(['jet2' => ['39']], $criteria['provider_destination_ids'] ?? null, 'Command fixture URL must expose Jet2 area ids for destination=39');
        $this->assertSame(
            [
                'jet2' => [
                    'airport' => (string) $q['airport'],
                    'boardbasis' => (string) $q['boardbasis'],
                    'sortorder' => (string) $q['sortorder'],
                    'page' => (string) $q['page'],
                ],
            ],
            $criteria['provider_url_params'] ?? null,
        );

        $request = Request::create('https://holidaysage.test/searches/1/edit', 'GET', $q);
        $prefill = SearchFormPrefill::fromRequest($request);
        $this->assertSame($fromApi, $prefill['travel_start_date'] ?? null);
        $this->assertSame(4, $prefill['adults'] ?? null);
        $this->assertSame(2, $prefill['children'] ?? null);
        $this->assertSame(['jet2' => ['39']], $prefill['provider_destination_ids'] ?? null);
        $this->assertSame(
            ['jet2' => (string) $q['occupancy']],
            $prefill['provider_occupancy'] ?? null,
            'Prefill must keep the raw Jet2 occupancy wire string under the provider key',
        );

        $importer = $this->app->make(Jet2LiveImporter::class);
        $build = new \ReflectionMethod($importer, 'buildSmartSearchApiUrl');
        $build->setAccessible(true);
        $apiUrl = $build->invoke($importer, $url, null);
        $this->assertIsString($apiUrl);
        parse_str((string) parse_url((string) $apiUrl, PHP_URL_QUERY), $apiQuery);

        $this->assertSame(
            implode('!', [
                'boardbasis_5-2-3',
                'starrating_4',
                'inboundflighttimes_2-3',
                'outboundflighttimes_2-3',
            ]),
            $apiQuery['filters'] ?? null,
        );

        $dd = new \ReflectionMethod($importer, 'ddmmyyyyToIso');
        $dd->setAccessible(true);
        $expectedDepartureDate = $dd->invoke($importer, (string) $q['date']);
        $this->assertSame($expectedDepartureDate, $apiQuery['departureDate'] ?? null);
        $this->assertSame((string) $q['airport'], $apiQuery['departureAirportIds'] ?? null);
        $this->assertSame((string) $q['destination'], $apiQuery['destinationAreaIds'] ?? null);

        $occ = new \ReflectionMethod($importer, 'occupancyToApi');
        $occ->setAccessible(true);
        $expectedOccupancies = $occ->invoke($importer, (string) $q['occupancy']);
        $this->assertSame($expectedOccupancies, $apiQuery['occupancies'] ?? null);
        $this->assertSame($fromApi, $expectedDepartureDate, 'URL date and API fixture first flight must match (same capture)');
    }

    /**
     * @return array<string, mixed>
     */
    private function loadApiFixture(): array
    {
        $path = Jet2UrlContract::apiResponsePath();
        $raw = file_get_contents($path);
        $this->assertIsString($raw);
        $data = json_decode($raw, true);
        $this->assertIsArray($data);

        return $data;
    }
}
