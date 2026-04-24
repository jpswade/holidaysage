<?php

namespace App\Services\Scoring;

use App\Contracts\HolidayScorer;
use App\Data\ScoreBreakdown;
use App\Models\HolidayOption;
use App\Models\SavedHolidaySearch;

class DefaultHolidayScorer implements HolidayScorer
{
    public function score(SavedHolidaySearch $search, HolidayOption $option): ScoreBreakdown
    {
        $disqual = [];
        $warnings = [];
        if ($this->nightsOutOfRange($search, $option)) {
            $disqual[] = 'Stay length does not match your night range';
        }
        // Board conflicts are down-ranked via dimBoard instead of hard disqualification in the MVP.
        if ($this->isBudgetBreak($search, $option)) {
            $disqual[] = 'Total price is far above your stated budget';
        }
        if ($this->isTransferBreak($search, $option)) {
            $disqual[] = 'Transfer time exceeds your maximum';
        }

        if ($option->transfer_minutes && $option->transfer_minutes > 90) {
            $warnings[] = 'Transfer may feel long in peak season';
        }
        if ($option->is_family_friendly && $search->children > 0) {
            $warnings = array_merge($warnings, []);
        } elseif ($search->children > 0 && ! $option->is_family_friendly) {
            $warnings[] = 'Not marked as family friendly';
        }

        $travel = $this->dimTravel($search, $option);
        $value = $this->dimValue($search, $option);
        $family = $this->dimFamily($search, $option);
        $location = $this->dimLocation($search, $option);
        $board = $this->dimBoard($search, $option);
        $price = $this->dimPrice($search, $option);

        $isDisq = $disqual !== [];
        if ($isDisq) {
            $overall = 0.0;
        } else {
            $overall = round(
                $travel * 0.25
                + $value * 0.25
                + $family * 0.25
                + $location * 0.15
                + $board * 0.10,
                2
            );
        }

        $reasons = $this->recommendationReasons($search, $option, $travel, $value, $family);
        $summary = $this->summary($search, $option, (float) $overall, $isDisq, $disqual, $reasons);

        return new ScoreBreakdown(
            overallScore: (float) $overall,
            travelScore: $travel,
            valueScore: $value,
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

    private function nightsOutOfRange(SavedHolidaySearch $search, HolidayOption $option): bool
    {
        return $option->nights < $search->duration_min_nights
            || $option->nights > $search->duration_max_nights;
    }

    private function isBudgetBreak(SavedHolidaySearch $search, HolidayOption $option): bool
    {
        if ($search->budget_total === null) {
            return false;
        }

        return (float) $option->price_total > (float) $search->budget_total * 1.2;
    }

    private function isTransferBreak(SavedHolidaySearch $search, HolidayOption $option): bool
    {
        if ($search->max_transfer_minutes === null || $option->transfer_minutes === null) {
            return false;
        }

        return $option->transfer_minutes > (int) $search->max_transfer_minutes;
    }

    private function dimTravel(SavedHolidaySearch $search, HolidayOption $option): float
    {
        $s = 7.0;
        if (strtoupper($option->airport_code) === strtoupper($search->departure_airport_code)) {
            $s += 0.5;
        }
        $o = (int) ($option->flight_outbound_duration_minutes ?? 0);
        $i = (int) ($option->flight_inbound_duration_minutes ?? 0);
        if ($o > 0 && $i > 0) {
            $long = max($o, $i);
            if ($search->max_flight_minutes) {
                $m = (int) $search->max_flight_minutes;
                if ($long > $m) {
                    $s -= 2.0;
                } else {
                    $s += (1.0 - $long / max($m, 1)) * 1.0;
                }
            } else {
                $s += max(0, 1.0 - $long / 600);
            }
        }
        $t = (int) ($option->transfer_minutes ?? 0);
        if ($t > 0) {
            $s += max(0, 1.0 - $t / 200);
        }

        return (float) max(0, min(10, $s));
    }

    private function dimValue(SavedHolidaySearch $search, HolidayOption $option): float
    {
        $s = 5.0;
        if ($search->budget_total) {
            $b = (float) $search->budget_total;
            $p = (float) $option->price_total;
            if ($p <= $b) {
                $s += 3.0;
            } else {
                $s -= min(3.0, ($p - $b) / max($b, 1) * 2);
            }
        }
        if ($option->review_score) {
            $s += (float) $option->review_score;
        } else {
            $s += 0.3;
        }

        return (float) max(0, min(10, $s));
    }

    private function dimFamily(SavedHolidaySearch $search, HolidayOption $option): float
    {
        $s = 5.0;
        if ((int) $search->children < 1) {
            $s = 6.0;

            return (float) max(0, min(10, $s));
        }
        if ($option->is_family_friendly) {
            $s += 2.0;
        }
        if ($option->has_kids_club) {
            $s += 1.0;
        }
        if ($option->has_family_rooms) {
            $s += 0.5;
        }
        if ($option->has_waterpark) {
            $s += 0.5;
        }
        if (is_array($search->feature_preferences) && in_array('kids_club', $search->feature_preferences, true) && ! $option->has_kids_club) {
            $s -= 2.0;
        }

        return (float) max(0, min(10, $s));
    }

    private function dimLocation(SavedHolidaySearch $search, HolidayOption $option): float
    {
        $s = 6.0;
        $p = is_array($search->feature_preferences) ? $search->feature_preferences : [];
        if (in_array('near_beach', $p, true) && $option->distance_to_beach_meters) {
            $s += $option->distance_to_beach_meters < 500 ? 1.0 : 0.2;
        }
        if (in_array('walkable', $p, true) && $option->distance_to_centre_meters) {
            $s += $option->distance_to_centre_meters < 1500 ? 1.0 : 0.1;
        }

        return (float) max(0, min(10, $s));
    }

    private function dimBoard(SavedHolidaySearch $search, HolidayOption $option): float
    {
        $p = is_array($search->board_preferences) ? $search->board_preferences : [];
        if ($p === [] || $option->board_type === null) {
            return 6.0;
        }
        if (in_array('all_inclusive_preferred', $p, true) && $option->board_type === 'all_inclusive') {
            return 9.0;
        }

        return 6.0;
    }

    private function dimPrice(SavedHolidaySearch $search, HolidayOption $option): float
    {
        if (! $search->budget_total) {
            return 6.0;
        }
        $ratio = (float) $option->price_total / max((float) $search->budget_total, 1.0);
        if ($ratio <= 0.7) {
            return 8.0;
        }
        if ($ratio <= 1.0) {
            return 6.0;
        }

        return 3.0;
    }

    /**
     * @return list<string>
     */
    private function recommendationReasons(
        SavedHolidaySearch $search,
        HolidayOption $option,
        float $travel,
        float $value,
        float $family
    ): array {
        $r = [];
        if ($value >= 6.5) {
            $r[] = 'Strong value for this holiday type';
        }
        if ($option->has_kids_club) {
            $r[] = 'Kids club on site';
        }
        if (strtolower($option->airport_code) === strtolower($search->departure_airport_code) && $travel >= 6) {
            $r[] = 'Departure airport matches your home airport';
        }
        if ($option->is_family_friendly && (int) $search->children > 0) {
            $r[] = 'Property is marketed as family friendly';
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
        HolidayOption $option,
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

        return 'Solid fit: '.$option->hotel_name.' in '.$option->destination_name.' scores '.number_format($overall, 1).'/10. '
            .($reasons[0] ?? 'Good overall match');
    }
}
