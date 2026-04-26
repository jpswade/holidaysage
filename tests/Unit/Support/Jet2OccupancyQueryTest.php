<?php

namespace Tests\Unit\Support;

use App\Support\Jet2OccupancyQuery;
use PHPUnit\Framework\TestCase;
use Tests\Support\Jet2UrlContract;

class Jet2OccupancyQueryTest extends TestCase
{
    public function test_sums_occupancy_segment_from_jet2_url_contract(): void
    {
        $q = [];
        parse_str((string) parse_url(Jet2UrlContract::forCommandAndPrefill(), PHP_URL_QUERY), $q);
        $this->assertArrayHasKey('occupancy', $q);

        $t = Jet2OccupancyQuery::totals((string) $q['occupancy']);
        $this->assertNotNull($t);
        $this->assertSame(4, $t['adults']);
        $this->assertSame(2, $t['children']);
        $this->assertSame(0, $t['infants']);
    }

    public function test_sums_r2c1_4_from_fixture_url_single_room(): void
    {
        $q = [];
        parse_str((string) parse_url(Jet2UrlContract::forSingleRoomWithTwoChildAges(), PHP_URL_QUERY), $q);

        $t = Jet2OccupancyQuery::totals((string) $q['occupancy']);
        $this->assertNotNull($t);
        $this->assertSame(2, $t['adults']);
        $this->assertSame(2, $t['children']);
    }

    public function test_tolerates_no_leading_r(): void
    {
        $t = Jet2OccupancyQuery::totals('2c1_4');
        $this->assertNotNull($t);
        $this->assertSame(2, $t['adults']);
        $this->assertSame(2, $t['children']);
    }
}
