<?php

namespace Tests\Unit\Support;

use App\Support\Jet2DestinationAreaIdList;
use PHPUnit\Framework\TestCase;

class Jet2DestinationAreaIdListTest extends TestCase
{
    public function test_parse_user_submitted_fixture_param_matches_underscore_tokenisation(): void
    {
        $j = $this->loadDestinationSamples();
        $param = (string) $j['user_submitted_search_results']['destination_param'];
        $this->assertNotSame('', $param);
        $expected = explode('_', $param);
        $this->assertNotSame([], $expected);
        $ids = Jet2DestinationAreaIdList::parse($param);
        $this->assertSame($expected, $ids);
        $this->assertSame('39', $ids[0]);
        $this->assertSame('4', $ids[array_key_last($ids)]);
    }

    public function test_parse_prinsotel_html_fixture_param_matches_underscore_tokenisation(): void
    {
        $j = $this->loadDestinationSamples();
        $param = (string) $j['prinsotel_mega_menu_holiday_calendar']['destination_param'];
        $this->assertNotSame('', $param);
        $this->assertSame(explode('_', $param), Jet2DestinationAreaIdList::parse($param));
    }

    public function test_prinsotel_detail_html_contains_documented_destination_param_in_href(): void
    {
        $path = dirname(__DIR__, 2).'/Fixtures/jet2_detail_prinsotel_alba.html';
        $this->assertFileExists($path, 'Prinsotel HTML must remain for href contract');
        $html = (string) file_get_contents($path);
        $expected = (string) $this->loadDestinationSamples()['prinsotel_mega_menu_holiday_calendar']['destination_param'];
        $this->assertStringContainsString('destination='.$expected, $html, 'Link href must still carry the same destination= list as the JSON fixture (drift will fail here)');
    }

    public function test_to_query_param_round_trips_excluding_empty_segments(): void
    {
        $this->assertSame('1_2_3', Jet2DestinationAreaIdList::toQueryParam(['1', '2', '3']));
    }

    public function test_rejects_malformed_segment(): void
    {
        $this->assertSame([], Jet2DestinationAreaIdList::parse('39_x_41'));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDestinationSamples(): array
    {
        $path = dirname(__DIR__, 2).'/Fixtures/jet2_destination_query_samples.json';
        $this->assertFileExists($path);
        $j = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($j);

        return $j;
    }
}
