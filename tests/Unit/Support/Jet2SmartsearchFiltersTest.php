<?php

namespace Tests\Unit\Support;

use App\Support\Jet2SmartsearchFilters;
use PHPUnit\Framework\TestCase;

class Jet2SmartsearchFiltersTest extends TestCase
{
    public function test_default_slugs_when_optional_filters_omitted(): void
    {
        $q = [
            'boardbasis' => '5_2_3',
        ];
        $this->assertSame(
            [
                'boardbasis_5-2-3',
                'starrating_4',
                'inboundflighttimes_2-3',
                'outboundflighttimes_2-3',
            ],
            Jet2SmartsearchFilters::filterSlugsFromResultsQuery($q)
        );
    }

    public function test_feature_and_flight_time_slugs_from_results_query(): void
    {
        $q = [
            'boardbasis' => '5_2_3',
            'starrating' => '4_5',
            'feature' => '14_13',
            'outboundflighttimes' => '07:00-09:59,10:00-13:59',
            'inboundflighttimes' => '10:00-13:59',
        ];
        $this->assertSame(
            [
                'boardbasis_5-2-3',
                'starrating_4-5',
                'feature_14-13',
                'inboundflighttimes_2-3',
                'outboundflighttimes_2-3',
            ],
            Jet2SmartsearchFilters::filterSlugsFromResultsQuery($q)
        );
    }

    public function test_flight_times_filter_slug_for_ui_string(): void
    {
        $this->assertSame(
            'outboundflighttimes_2-3',
            Jet2SmartsearchFilters::flightTimesFilterSlug('outboundflighttimes', '07:00-09:59,10:00-13:59')
        );
    }
}
