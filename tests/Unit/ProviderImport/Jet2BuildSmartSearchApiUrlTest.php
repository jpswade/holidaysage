<?php

namespace Tests\Unit\ProviderImport;

use App\Services\ProviderImport\Importers\Jet2LiveImporter;
use Tests\TestCase;

class Jet2BuildSmartSearchApiUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('holidaysage.jet2.strict_fixture_shape', false);
    }

    public function test_builds_filters_including_translated_flight_time_windows(): void
    {
        $importer = $this->app->make(Jet2LiveImporter::class);
        $m = new \ReflectionMethod($importer, 'buildSmartSearchApiUrl');
        $m->setAccessible(true);
        $url = 'https://www.jet2holidays.com/search/results?airport=98&destination=39&date=25-07-2026'
            .'&duration=10&occupancy=r2&page=1&sortorder=1&boardbasis=5_2_3&starrating=4_5'
            .'&feature=14_13&outboundflighttimes=07%3A00-09%3A59%2C10%3A00-13%3A59&inboundflighttimes=10%3A00-13%3A59';

        $apiUrl = $m->invoke($importer, $url, null);
        $this->assertIsString($apiUrl);
        parse_str((string) parse_url((string) $apiUrl, PHP_URL_QUERY), $q);
        $this->assertSame(
            'boardbasis_5-2-3!starrating_4-5!feature_14-13!inboundflighttimes_2-3!outboundflighttimes_2-3',
            $q['filters'] ?? null
        );
    }

    public function test_strict_fixture_shape_reduces_destination_and_optional_filters(): void
    {
        config()->set('holidaysage.jet2.strict_fixture_shape', true);

        $importer = $this->app->make(Jet2LiveImporter::class);
        $m = new \ReflectionMethod($importer, 'buildSmartSearchApiUrl');
        $m->setAccessible(true);
        $url = 'https://www.jet2holidays.com/search/results?airport=98_63_3&destination=39_1679_1452'
            .'&date=25-07-2026&duration=10&occupancy=r2c_r2c1_4&page=1&sortorder=1'
            .'&boardbasis=5_2_3&starrating=4_5&feature=14_13'
            .'&outboundflighttimes=07%3A00-09%3A59%2C10%3A00-13%3A59&inboundflighttimes=10%3A00-13%3A59';

        $apiUrl = $m->invoke($importer, $url, null);
        $this->assertIsString($apiUrl);
        parse_str((string) parse_url((string) $apiUrl, PHP_URL_QUERY), $q);

        $this->assertSame('98_63_3', $q['departureAirportIds'] ?? null);
        $this->assertSame('39', $q['destinationAreaIds'] ?? null);
        $this->assertSame(
            'boardbasis_5-2-3!starrating_4!inboundflighttimes_2-3!outboundflighttimes_2-3',
            $q['filters'] ?? null
        );
    }
}
