<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\Parsers\Jet2ImportUrlParser;
use PHPUnit\Framework\TestCase;
use Tests\Support\Jet2UrlContract;

class Jet2ImportUrlParserTest extends TestCase
{
    public function test_parses_dep_adults_and_date_from_query(): void
    {
        $p = new Jet2ImportUrlParser;
        $url = 'https://www.jet2holidays.com/en/cyprus?Adults=2&Children=1&DepAirportIata=MAN&DepartureDate=2025-08-15&Duration=10';

        $this->assertTrue($p->supports($url));
        $c = $p->parse($url);

        $this->assertSame('MAN', $c['departure_airport_code'] ?? null);
        $this->assertSame(2, $c['adults'] ?? null);
        $this->assertSame(1, $c['children'] ?? null);
        $this->assertSame(10, $c['duration_min_nights'] ?? null);
        $this->assertSame('2025-08-15', $c['travel_start_date'] ?? null);
    }

    public function test_parses_search_results_date_and_occupancy_from_fixture_url(): void
    {
        $p = new Jet2ImportUrlParser;
        $url = Jet2UrlContract::forCommandAndPrefill();

        $this->assertTrue($p->supports($url), 'Contract URL must be a supported Jet2 search');
        $c = $p->parse($url);

        $this->assertSame('2026-07-25', $c['travel_start_date'] ?? null);
        $this->assertSame(4, $c['adults'] ?? null);
        $this->assertSame(2, $c['children'] ?? null);
        $this->assertSame(['jet2' => ['39']], $c['provider_destination_ids'] ?? null);
        $this->assertSame(['jet2' => 'r2c_r2c1_4'], $c['provider_occupancy'] ?? null);
        $this->assertSame(
            [
                'jet2' => [
                    'airport' => '98',
                    'boardbasis' => '5_2_3',
                    'sortorder' => '1',
                    'page' => '1',
                ],
            ],
            $c['provider_url_params'] ?? null,
        );
    }

    public function test_parses_provider_url_params_including_flight_time_windows_and_feature(): void
    {
        $p = new Jet2ImportUrlParser;
        $url = 'https://www.jet2holidays.com/search/results?airport=98&destination=39&date=25-07-2026'
            .'&duration=10&occupancy=r2c_r2c1_4&boardbasis=5_2_3&starrating=4_5&feature=14_13'
            .'&outboundflighttimes=07%3A00-09%3A59%2C10%3A00-13%3A59&inboundflighttimes=10%3A00-13%3A59&sr=true';

        $c = $p->parse($url);
        $this->assertSame(
            [
                'jet2' => [
                    'airport' => '98',
                    'boardbasis' => '5_2_3',
                    'starrating' => '4_5',
                    'outboundflighttimes' => '07:00-09:59,10:00-13:59',
                    'inboundflighttimes' => '10:00-13:59',
                    'feature' => '14_13',
                    'sr' => 'true',
                ],
            ],
            $c['provider_url_params'] ?? null,
        );
    }

    public function test_parses_user_submitted_jet2_destination_id_list_param(): void
    {
        $j = json_decode(
            (string) file_get_contents(
                (string) realpath(__DIR__.'/../../Fixtures/jet2_destination_query_samples.json')
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertIsArray($j);
        $param = (string) $j['user_submitted_search_results']['destination_param'];
        $url = 'https://www.jet2holidays.com/search/results?date=25-07-2026&duration=10&occupancy=r2&destination='
            .rawurlencode($param);

        $p = new Jet2ImportUrlParser;
        $c = $p->parse($url);
        $this->assertSame(['jet2' => 'r2'], $c['provider_occupancy'] ?? null);
        $this->assertIsArray($c['provider_destination_ids']['jet2'] ?? null);
        $this->assertCount(count(explode('_', $param)), $c['provider_destination_ids']['jet2'] ?? []);
    }
}
