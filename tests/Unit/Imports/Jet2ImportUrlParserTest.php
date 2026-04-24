<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\Parsers\Jet2ImportUrlParser;
use PHPUnit\Framework\TestCase;

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
}
