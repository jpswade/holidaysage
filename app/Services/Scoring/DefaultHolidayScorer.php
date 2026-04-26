<?php

namespace App\Services\Scoring;

use App\Contracts\HolidayScorer;
use App\Data\ScoreBreakdown;
use App\Models\HolidayPackage;
use App\Models\SavedHolidaySearch;

/**
 * Holiday option scoring aligned with the Beachin rubric (package value bands, separate guest
 * reviews on a 0–10 scale, travel caps for long flights/transfers, budget fit in the overall blend).
 *
 * @see /Users/wade/Sites/beachin/src/scoring.py
 */
class DefaultHolidayScorer implements HolidayScorer
{
    private const FLIGHT_TIME_MAX_HOURS = 4.0;

    private const FLIGHT_TIME_MIN_HOURS = 2.0;

    private const TRANSFER_TIME_MAX_MINS = 90;

    private const TRANSFER_TIME_HARD_FAIL_MINS = 120;

    private const SCORE_TRAVEL_CAP_WHEN_FLIGHT_VIOLATION = 3.0;

    private const SCORE_TRAVEL_CAP_WHEN_TRANSFER_VIOLATION = 4.0;

    /** Weights sum to 1.0 — reviews and budget (price) are explicit so options do not cluster. */
    private const W_TRAVEL = 0.20;

    private const W_VALUE = 0.14;

    private const W_REVIEWS = 0.10;

    private const W_FAMILY = 0.18;

    private const W_LOCATION = 0.12;

    private const W_BOARD = 0.10;

    private const W_PRICE = 0.16;

    public function score(SavedHolidaySearch $search, HolidayPackage $option): ScoreBreakdown
    {
        $hotel = $option->hotel;
        $disqual = [];
        $warnings = [];
        if ($this->nightsOutOfRange($search, $option)) {
            $disqual[] = 'Stay length does not match your night range';
        }
        if ($this->isBudgetBreak($search, $option)) {
            $disqual[] = 'Total price is far above your stated budget';
        }
        if ($this->isTransferBreak($search, $option)) {
            $disqual[] = 'Transfer time exceeds your maximum';
        }

        if ($option->transfer_minutes && $option->transfer_minutes > 90) {
            $warnings[] = 'Transfer may feel long in peak season';
        }
        if ($hotel?->is_family_friendly && $search->children > 0) {
            $warnings = array_merge($warnings, []);
        } elseif ($search->children > 0 && ! $hotel?->is_family_friendly) {
            $warnings[] = 'Not marked as family friendly';
        }

        $travel = $this->dimTravel($search, $option);
        $value = $this->dimValue($option);
        $reviews = $this->dimReviews($option);
        $family = $this->dimFamily($search, $option);
        $location = $this->dimLocation($search, $option);
        $board = $this->dimBoard($search, $option);
        $price = $this->dimPrice($search, $option);

        $isDisq = $disqual !== [];
        if ($isDisq) {
            $overall = 0.0;
        } else {
            $overall = round(
                $travel * self::W_TRAVEL
                + $value * self::W_VALUE
                + $reviews * self::W_REVIEWS
                + $family * self::W_FAMILY
                + $location * self::W_LOCATION
                + $board * self::W_BOARD
                + $price * self::W_PRICE,
                2
            );
        }

        $reasons = $this->recommendationReasons($search, $option, $travel, $value, $reviews, $family);
        $summary = $this->summary($search, $option, (float) $overall, $isDisq, $disqual, $reasons);

        return new ScoreBreakdown(
            overallScore: (float) $overall,
            travelScore: $travel,
            valueScore: $value,
            reviewsScore: $reviews,
            familyFitScore: $family,
            locationScore: $location,
            boardScore: $board,
            priceScore: $price,
            isDisqualified: $isDisq,
            disqualificationReasons: $disqual,
            warningFlags: $warnings,
            recommendationSummary: $summary,
            recommendationReasons: $reasons,
        );
    }

    private function nightsOutOfRange(SavedHolidaySearch $search, HolidayPackage $option): bool
    {
        return $option->nights < $search->duration_min_nights
            || $option->nights > $search->duration_max_nights;
    }

    private function isBudgetBreak(SavedHolidaySearch $search, HolidayPackage $option): bool
    {
        if ($search->budget_total === null) {
            return false;
        }

        return (float) $option->price_total > (float) $search->budget_total * 1.2;
    }

    private function isTransferBreak(SavedHolidaySearch $search, HolidayPackage $option): bool
    {
        if ($search->max_transfer_minutes === null || $option->transfer_minutes === null) {
            return false;
        }

        return $option->transfer_minutes > (int) $search->max_transfer_minutes;
    }

    private function effectiveFlightHours(HolidayPackage $option): ?float
    {
        if ($option->flight_time_hours_est !== null) {
            return (float) $option->flight_time_hours_est;
        }
        $o = (int) ($option->flight_outbound_duration_minutes ?? 0);
        $i = (int) ($option->flight_inbound_duration_minutes ?? 0);
        if ($o <= 0 && $i <= 0) {
            return null;
        }

        return max($o, $i) / 60.0;
    }

    /**
     * Beachin {@see _score_travel}: base 5, flight and transfer bands, caps when flight ≥4h or transfer >90m.
     */
    private function dimTravel(SavedHolidaySearch $search, HolidayPackage $option): float
    {
        $flightH = $this->effectiveFlightHours($option);
        $transferM = (int) ($option->transfer_minutes ?? 0);
        $transferM = $transferM > 0 ? $transferM : 0;

        $score = 5.0;
        if ($flightH !== null) {
            $flightOk = $flightH >= self::FLIGHT_TIME_MIN_HOURS && $flightH < self::FLIGHT_TIME_MAX_HOURS;
            if ($flightOk) {
                $score += 2.5;
            } elseif ($flightH < self::FLIGHT_TIME_MAX_HOURS) {
                $score += 1.0;
            }
        }
        if ($transferM > 0) {
            $transferOk = $transferM <= self::TRANSFER_TIME_MAX_MINS;
            if ($transferOk) {
                $score += 2.5;
            } elseif ($transferM <= self::TRANSFER_TIME_HARD_FAIL_MINS) {
                $score += 1.0;
            }
        }

        if (strtoupper((string) $option->airport_code) === strtoupper($search->departure_airport_code)) {
            $score += 0.5;
        }

        if ($flightH !== null && $flightH >= self::FLIGHT_TIME_MAX_HOURS) {
            $score = min($score, self::SCORE_TRAVEL_CAP_WHEN_FLIGHT_VIOLATION);
        }
        if ($transferM > self::TRANSFER_TIME_MAX_MINS) {
            $score = min($score, self::SCORE_TRAVEL_CAP_WHEN_TRANSFER_VIOLATION);
        }

        return (float) max(0, min(10, $score));
    }

    /**
     * Beachin {@see _score_value}: package price bands; neutral 5 for AI/FB; else local beer/meal fallback.
     */
    private function dimValue(HolidayPackage $option): float
    {
        if ($this->packageBoardIsAiOrFb($option)) {
            return 5.0;
        }

        $scores = [];
        if ($option->price_per_person !== null) {
            $scores[] = self::packagePpScore((float) $option->price_per_person);
        }
        if ($option->price_total !== null) {
            $scores[] = self::packageTotalScore((float) $option->price_total);
        }
        if ($scores !== []) {
            return (float) round(array_sum($scores) / count($scores), 2);
        }

        $beer = $option->local_beer_price !== null ? (float) $option->local_beer_price : null;
        $meal = $option->three_course_meal_for_two_price !== null ? (float) $option->three_course_meal_for_two_price : null;
        $fallback = [];
        if ($beer !== null) {
            $fallback[] = self::beerScore($beer);
        }
        if ($meal !== null) {
            $fallback[] = self::mealScore($meal);
        }
        if ($fallback !== []) {
            return (float) round(array_sum($fallback) / count($fallback), 2);
        }

        return 5.0;
    }

    private static function packagePpScore(float $p): float
    {
        if ($p < 1200) {
            return 9.0;
        }
        if ($p < 1600) {
            return 8.0;
        }
        if ($p < 2000) {
            return 7.0;
        }
        if ($p < 2500) {
            return 5.0;
        }
        if ($p < 3200) {
            return 4.0;
        }

        return 3.0;
    }

    private static function packageTotalScore(float $t): float
    {
        if ($t < 4000) {
            return 9.0;
        }
        if ($t < 6000) {
            return 7.0;
        }
        if ($t < 8000) {
            return 5.0;
        }
        if ($t < 10000) {
            return 4.0;
        }

        return 3.0;
    }

    private static function beerScore(float $p): float
    {
        if ($p < 2.5) {
            return 9.0;
        }
        if ($p < 3.5) {
            return 7.0;
        }
        if ($p < 4.5) {
            return 5.0;
        }

        return 3.0;
    }

    private static function mealScore(float $p): float
    {
        if ($p < 35) {
            return 9.0;
        }
        if ($p < 45) {
            return 7.0;
        }
        if ($p < 55) {
            return 5.0;
        }

        return 3.0;
    }

    /**
     * Beachin {@see _score_reviews}: (score/scale)*10, unknown → 5.
     */
    private function dimReviews(HolidayPackage $option): float
    {
        $hotel = $option->hotel;
        if ($hotel === null || $hotel->review_score === null) {
            return 5.0;
        }
        $scoreVal = (float) $hotel->review_score;
        $scale = 10.0;
        $raw = is_array($hotel->raw_attributes) ? $hotel->raw_attributes : [];
        if (isset($raw['review_scale']) && is_numeric($raw['review_scale'])) {
            $s = (float) $raw['review_scale'];
            if ($s > 0) {
                $scale = $s;
            }
        } elseif ($scoreVal <= 5.0) {
            $scale = 5.0;
        }

        return (float) min(10.0, max(0.0, ($scoreVal / $scale) * 10.0));
    }

    private function packageBoardIsAiOrFb(HolidayPackage $option): bool
    {
        foreach ([$option->board_type, $option->board_recommended] as $raw) {
            if ($raw === null) {
                continue;
            }
            $s = trim((string) $raw);
            if ($s === '') {
                continue;
            }
            $u = strtoupper($s);
            if (in_array($u, ['AI', 'FB', '5', '4'], true)) {
                return true;
            }
            $norm = strtolower(str_replace([' ', '-'], '_', $s));
            if (str_contains($norm, 'all_inclusive') || str_contains($norm, 'allinclusive')) {
                return true;
            }
            if ($norm === 'all_inclusive' || $norm === 'full_board') {
                return true;
            }
            if (str_contains($u, 'ALL INCLUSIVE') || str_contains($u, 'ALL-INCLUSIVE')) {
                return true;
            }
            if (str_contains($u, 'FULL BOARD') || $u === 'FULL_BOARD') {
                return true;
            }
        }

        return false;
    }

    /**
     * Beachin-style facility tally when travelling with children; neutral 5 when party has no children.
     */
    private function dimFamily(SavedHolidaySearch $search, HolidayPackage $option): float
    {
        if ((int) $search->children < 1) {
            return 5.0;
        }

        $h = $option->hotel;
        if ($h === null) {
            return 5.0;
        }

        $s = 0.0;
        if ($h->has_kids_club) {
            $s += 3.0;
        }
        if ($h->play_area) {
            $s += 2.0;
        }
        if ($h->has_waterpark) {
            $s += 2.0;
        }
        if ($h->evening_entertainment) {
            $s += 1.0;
        }
        if ($h->kids_disco) {
            $s += 1.0;
        }
        if ($h->cots_available) {
            $s += 1.0;
        }
        if (! $h->is_family_friendly) {
            $s -= 1.5;
        }
        if (is_array($search->feature_preferences) && in_array('kids_club', $search->feature_preferences, true) && ! $h->has_kids_club) {
            $s -= 2.0;
        }

        if ($s <= 0.0) {
            return 5.0;
        }

        return (float) max(0, min(10, $s));
    }

    private function dimLocation(SavedHolidaySearch $search, HolidayPackage $option): float
    {
        $s = 5.0;
        $p = is_array($search->feature_preferences) ? $search->feature_preferences : [];
        if (in_array('near_beach', $p, true) && $option->hotel?->distance_to_beach_meters !== null) {
            $s += $option->hotel->distance_to_beach_meters < 500 ? 1.0 : 0.2;
        }
        if (in_array('walkable', $p, true) && $option->hotel?->distance_to_centre_meters !== null) {
            $s += $option->hotel->distance_to_centre_meters < 1500 ? 1.0 : 0.1;
        }

        return (float) max(0, min(10, $s));
    }

    /**
     * Beachin {@see _score_board}: unknown → 5; search preferences refine upward for AI match.
     */
    private function dimBoard(SavedHolidaySearch $search, HolidayPackage $option): float
    {
        $p = is_array($search->board_preferences) ? $search->board_preferences : [];
        if ($p !== [] && in_array('all_inclusive_preferred', $p, true) && $option->board_type === 'all_inclusive') {
            return 9.0;
        }
        if ($p === [] || $option->board_type === null || $option->board_type === '') {
            return 5.0;
        }

        return 5.5;
    }

    /** Budget fit vs stated total (0–10); unknown budget → neutral 5. */
    private function dimPrice(SavedHolidaySearch $search, HolidayPackage $option): float
    {
        if (! $search->budget_total) {
            return 5.0;
        }
        $ratio = (float) $option->price_total / max((float) $search->budget_total, 1.0);
        if ($ratio <= 0.55) {
            return 9.5;
        }
        if ($ratio <= 0.70) {
            return 8.5;
        }
        if ($ratio <= 0.85) {
            return 7.5;
        }
        if ($ratio <= 1.0) {
            return 6.5;
        }
        if ($ratio <= 1.1) {
            return 5.0;
        }
        if ($ratio <= 1.2) {
            return 3.5;
        }

        return 2.0;
    }

    /**
     * @return list<string>
     */
    private function recommendationReasons(
        SavedHolidaySearch $search,
        HolidayPackage $option,
        float $travel,
        float $value,
        float $reviews,
        float $family
    ): array {
        $r = [];
        if ($reviews >= 7.5) {
            $r[] = 'Strong guest ratings for this property';
        }
        if ($value >= 6.5) {
            $r[] = 'Competitive package price for this board and season';
        }
        if ($option->hotel?->has_kids_club) {
            $r[] = 'Kids club on site';
        }
        if (strtolower((string) $option->airport_code) === strtolower($search->departure_airport_code) && $travel >= 6) {
            $r[] = 'Departure airport matches your home airport';
        }
        if ($option->hotel?->is_family_friendly && (int) $search->children > 0) {
            $r[] = 'Property is marketed as family friendly';
        }
        if ((int) $search->children > 0 && $family >= 7.0) {
            $r[] = 'Good on-site facilities for younger guests';
        }
        if (count($r) < 2) {
            $r[] = 'Balanced option across the criteria you care about';
        }

        return array_slice($r, 0, 5);
    }

    /**
     * @param  list<string>  $disqualificationList
     * @param  list<string>  $reasons
     */
    private function summary(
        SavedHolidaySearch $search,
        HolidayPackage $option,
        float $overall,
        bool $isDisqualified,
        array $disqualificationList,
        array $reasons
    ): ?string {
        if ($isDisqualified) {
            return count($disqualificationList) > 0
                ? 'Screened out: '.implode('; ', $disqualificationList)
                : 'Screened out';
        }

        return 'Solid fit: '.($option->hotel?->hotel_name ?? 'Hotel').' in '.($option->hotel?->destination_name ?? 'Unknown destination').' scores '.number_format($overall, 1).'/10. '
            .($reasons[0] ?? 'Good overall match');
    }
}
