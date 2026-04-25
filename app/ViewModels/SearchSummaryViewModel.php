<?php

namespace App\ViewModels;

use App\Models\SavedHolidaySearch;

class SearchSummaryViewModel
{
    public function __construct(
        public readonly string $airport,
        public readonly string $dateRange,
        public readonly string $party,
        public readonly string $nights,
        public readonly ?string $budget,
        /** @var list<string> */
        public readonly array $preferences,
    ) {}

    public static function fromModel(SavedHolidaySearch $search): self
    {
        $airport = $search->departure_airport_name ?: strtoupper((string) $search->departure_airport_code);
        $dateRange = self::formatDateRange($search->travel_start_date?->toDateString(), $search->travel_end_date?->toDateString());
        $party = self::formatParty((int) $search->adults, (int) $search->children, (int) $search->infants);
        $nights = self::formatNights((int) $search->duration_min_nights, (int) $search->duration_max_nights);
        $budget = $search->budget_total !== null ? 'Up to £'.number_format((float) $search->budget_total, 0) : null;
        $preferences = array_values(array_filter(array_map('strval', $search->feature_preferences ?? [])));

        return new self(
            airport: $airport,
            dateRange: $dateRange,
            party: $party,
            nights: $nights,
            budget: $budget,
            preferences: $preferences,
        );
    }

    private static function formatDateRange(?string $start, ?string $end): string
    {
        if ($start && $end) {
            return date('j M', strtotime($start)).' - '.date('j M', strtotime($end));
        }

        if ($start) {
            return 'From '.date('j M', strtotime($start));
        }

        return 'Flexible dates';
    }

    private static function formatParty(int $adults, int $children, int $infants): string
    {
        $parts = [];
        $parts[] = $adults.' adult'.($adults === 1 ? '' : 's');
        if ($children > 0) {
            $parts[] = $children.' child'.($children === 1 ? '' : 'ren');
        }
        if ($infants > 0) {
            $parts[] = $infants.' infant'.($infants === 1 ? '' : 's');
        }

        return implode(', ', $parts);
    }

    private static function formatNights(int $min, int $max): string
    {
        if ($min === $max) {
            return $min.' nights';
        }

        return $min.'-'.$max.' nights';
    }
}
