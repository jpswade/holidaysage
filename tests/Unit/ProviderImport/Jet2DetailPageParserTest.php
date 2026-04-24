<?php

namespace Tests\Unit\ProviderImport;

use App\Services\ProviderImport\DetailParsers\Jet2DetailPageParser;
use Tests\TestCase;

class Jet2DetailPageParserTest extends TestCase
{
    public function test_it_extracts_phase_one_beachin_aligned_fields(): void
    {
        $parser = new Jet2DetailPageParser;
        $candidate = [
            'raw_attributes' => [
                'distance_to_airport_km' => 47.51,
                'outbound_flight' => '08:05-12:10',
                'inbound_flight' => '13:20-15:35',
                'property' => [
                    'tripAdvisorRating' => 4.4,
                    'tripAdvisorReviewCount' => 3496,
                    'mapLocation' => ['latitude' => 36.7262, 'longitude' => -3.9624],
                    'rating' => 4,
                    'features' => ['3 blocks', '7 floors', '413 rooms', '4 restaurants', '2 bars', '2 pools', 'family rooms'],
                    'keySellingPoints' => ['Beachfront', '100m from resort centre'],
                ],
                'accommodation_options' => [
                    [
                        'board' => 'All Inclusive',
                        'boardId' => 'AI',
                        'priceOptions' => [
                            ['totalPrice' => 9390, 'pricePerPerson' => 1878],
                        ],
                    ],
                ],
            ],
        ];

        $html = <<<'HTML'
<html><body>
<script type="application/ld+json">
{"@type":"Hotel","address":{"addressCountry":"Spain"}}
</script>
<div>official rating: 4 star</div>
<div class="accordion">
  <h3 class="accordion__heading">Sports &amp; Leisure</h3>
  <div class="accordion__content">
    <ul class="accordion__list"><li>Gym</li><li>Aerobics</li></ul>
  </div>
</div>
<p class="grid-item__heading">Local beer</p><p class="grid-item__text">£2.50</p>
<p class="grid-item__heading">Three-course meal for two</p><p class="grid-item__text">£42.10</p>
<div class="overview__board-type-title">Half Board</div>
<div class="overview__board-type-title">All Inclusive</div>
</body></html>
HTML;

        $parsed = $parser->parse($candidate, $html);
        $hotel = $parsed['hotel'];
        $packages = $parsed['packages'];

        $this->assertSame(413, $hotel['rooms_count']);
        $this->assertSame(3, $hotel['blocks_count']);
        $this->assertSame(7, $hotel['floors_count']);
        $this->assertSame(4, $hotel['restaurants_count']);
        $this->assertSame(2, $hotel['bars_count']);
        $this->assertSame(2, $hotel['pools_count']);
        $this->assertSame(2, $hotel['sports_leisure_count']);
        $this->assertSame(47.51, $hotel['distance_to_airport_km']);
        $this->assertSame('Spain', $hotel['destination_country']);

        $this->assertNotEmpty($packages);
        $this->assertSame('All Inclusive', $packages[0]['board_recommended']);
        $this->assertSame(2.5, $packages[0]['local_beer_price']);
        $this->assertSame(42.1, $packages[0]['three_course_meal_for_two_price']);
        $this->assertSame('08:05-12:10', $packages[0]['outbound_flight_time_text']);
        $this->assertSame('13:20-15:35', $packages[0]['inbound_flight_time_text']);
    }
}
