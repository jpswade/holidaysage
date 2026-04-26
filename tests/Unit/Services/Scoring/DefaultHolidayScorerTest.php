<?php

namespace Tests\Unit\Services\Scoring;

use App\Models\HolidayPackage;
use App\Models\Hotel;
use App\Models\SavedHolidaySearch;
use App\Services\Scoring\DefaultHolidayScorer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DefaultHolidayScorerTest extends TestCase
{
    private function baseSearch(): SavedHolidaySearch
    {
        return new SavedHolidaySearch([
            'departure_airport_code' => 'MAN',
            'duration_min_nights' => 3,
            'duration_max_nights' => 14,
            'children' => 0,
            'budget_total' => null,
            'max_flight_minutes' => null,
            'max_transfer_minutes' => null,
            'board_preferences' => null,
            'feature_preferences' => null,
        ]);
    }

    #[Test]
    public function all_inclusive_package_uses_neutral_value_not_review_score(): void
    {
        $search = $this->baseSearch();
        $hotel = new Hotel(['review_score' => 4.2]);
        $option = new HolidayPackage([
            'nights' => 7,
            'airport_code' => 'MAN',
            'board_type' => 'all_inclusive',
            'price_total' => 9000,
            'price_per_person' => 3000,
        ]);
        $option->setRelation('hotel', $hotel);

        $b = (new DefaultHolidayScorer)->score($search, $option);

        $this->assertSame(5.0, $b->valueScore);
        $this->assertSame(8.4, $b->reviewsScore);
    }

    #[Test]
    public function half_board_uses_package_price_bands_not_flat_ten(): void
    {
        $search = $this->baseSearch();
        $hotel = new Hotel(['review_score' => 4.0]);
        $option = new HolidayPackage([
            'nights' => 7,
            'airport_code' => 'MAN',
            'board_type' => 'half_board',
            'price_total' => 5500,
            'price_per_person' => 1500,
        ]);
        $option->setRelation('hotel', $hotel);

        $b = (new DefaultHolidayScorer)->score($search, $option);
        // pp 1500 → 8.0, total 5500 → 7.0 → average 7.5
        $this->assertSame(7.5, $b->valueScore);
        $this->assertSame(8.0, $b->reviewsScore);
    }

    #[Test]
    public function overall_includes_budget_fit_when_budget_set(): void
    {
        $search = $this->baseSearch();
        $search->budget_total = 5000;
        $search->children = 0;
        $hotel = new Hotel(['review_score' => 3.0]);
        $cheap = new HolidayPackage([
            'nights' => 7,
            'airport_code' => 'MAN',
            'board_type' => 'half_board',
            'price_total' => 3500,
            'price_per_person' => 1200,
            'flight_time_hours_est' => 3.0,
            'transfer_minutes' => 45,
        ]);
        $cheap->setRelation('hotel', $hotel);

        $dearHotel = new Hotel(['review_score' => 3.0]);
        $dear = new HolidayPackage([
            'nights' => 7,
            'airport_code' => 'MAN',
            'board_type' => 'half_board',
            'price_total' => 4900,
            'price_per_person' => 2400,
            'flight_time_hours_est' => 3.0,
            'transfer_minutes' => 45,
        ]);
        $dear->setRelation('hotel', $dearHotel);

        $scorer = new DefaultHolidayScorer;
        $cheapB = $scorer->score($search, $cheap);
        $dearB = $scorer->score($search, $dear);

        $this->assertGreaterThan($dearB->overallScore, $cheapB->overallScore);
        $this->assertSame(8.5, $cheapB->priceScore);
        $this->assertSame(6.5, $dearB->priceScore);
    }

    #[Test]
    public function long_flight_caps_travel_score(): void
    {
        $search = $this->baseSearch();
        $hotel = new Hotel(['review_score' => 4.0]);
        $option = new HolidayPackage([
            'nights' => 7,
            'airport_code' => 'MAN',
            'board_type' => 'half_board',
            'price_total' => 5000,
            'price_per_person' => 1600,
            'flight_time_hours_est' => 4.5,
            'transfer_minutes' => 40,
        ]);
        $option->setRelation('hotel', $hotel);

        $travel = (new DefaultHolidayScorer)->score($search, $option)->travelScore;

        $this->assertSame(3.0, $travel);
    }
}
