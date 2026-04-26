<?php

namespace App\ViewModels;

use App\Models\SavedHolidaySearch;
use App\Support\BoardBasisDisplay;

class SearchSummaryViewModel
{
    /**
     * @param  list<string>  $primaryBullets
     * @param  list<string>  $constraintBullets
     * @param  list<string>  $boardChipLabels
     * @param  list<string>  $destinationChipLabels
     * @param  list<string>  $excludedDestinationLabels
     * @param  list<string>  $excludedFeatureLabels
     * @param  list<array{label: string, emoji: string}>  $featureChips
     */
    public function __construct(
        public readonly string $airport,
        public readonly string $dateRange,
        public readonly string $party,
        public readonly string $nights,
        public readonly ?string $budget,
        /** @var list<string> */
        public readonly array $preferences,
        /** @var list<string> */
        public readonly array $primaryBullets,
        /** @var list<string> */
        public readonly array $constraintBullets,
        /** @var list<string> */
        public readonly array $boardChipLabels,
        /** @var list<string> */
        public readonly array $destinationChipLabels,
        /** @var list<string> */
        public readonly array $excludedDestinationLabels,
        /** @var list<string> */
        public readonly array $excludedFeatureLabels,
        /** @var list<array{label: string, emoji: string}> */
        public readonly array $featureChips,
        public readonly ?string $providerImportUrl,
    ) {}

    public static function fromModel(SavedHolidaySearch $search): self
    {
        $airport = $search->departure_airport_name ?: strtoupper((string) $search->departure_airport_code);
        $dateRange = self::formatDateRange($search->travel_start_date?->toDateString(), $search->travel_end_date?->toDateString());
        $party = self::formatParty((int) $search->adults, (int) $search->children, (int) $search->infants);
        $nights = self::formatNights((int) $search->duration_min_nights, (int) $search->duration_max_nights);
        $budget = $search->budget_total !== null ? 'Up to £'.number_format((float) $search->budget_total, 0) : null;
        $preferences = array_values(array_filter(array_map('strval', $search->feature_preferences ?? [])));

        $primaryBullets = [];
        $primaryBullets[] = 'From '.$airport;

        $flexDays = (int) ($search->travel_date_flexibility_days ?? 0);
        if ($flexDays > 0) {
            $primaryBullets[] = '±'.$flexDays.' day'.($flexDays === 1 ? '' : 's').' date flexibility';
        }

        $primaryBullets[] = $dateRange;
        $primaryBullets[] = $nights;
        $primaryBullets[] = $party;

        if ($budget !== null) {
            $primaryBullets[] = $budget;
        }

        if ($search->budget_per_person !== null) {
            $primaryBullets[] = 'Up to £'.number_format((float) $search->budget_per_person, 0).' per person';
        }

        $constraintBullets = [];
        if ($search->max_flight_minutes !== null && (int) $search->max_flight_minutes > 0) {
            $constraintBullets[] = 'Max flight '.self::formatMinutes((int) $search->max_flight_minutes);
        }
        if ($search->max_transfer_minutes !== null && (int) $search->max_transfer_minutes > 0) {
            $constraintBullets[] = 'Max transfer '.(int) $search->max_transfer_minutes.' min';
        }
        if (is_string($search->sort_preference) && trim($search->sort_preference) !== '') {
            $constraintBullets[] = 'Sort: '.ucwords(str_replace('_', ' ', $search->sort_preference));
        }

        $boardChipLabels = self::boardLabelsFromPreferences($search->board_preferences ?? []);
        $destinationChipLabels = self::destinationLabelsFromPreferences($search->destination_preferences ?? []);
        $excludedDestinationLabels = self::stringListLabels($search->excluded_destinations ?? []);
        $excludedFeatureLabels = self::stringListLabels($search->excluded_features ?? []);
        $featureChips = self::featureChipsFromKeys($preferences);

        $providerImportUrl = null;
        if (is_string($search->provider_import_url) && $search->provider_import_url !== '') {
            $providerImportUrl = $search->provider_import_url;
        }

        return new self(
            airport: $airport,
            dateRange: $dateRange,
            party: $party,
            nights: $nights,
            budget: $budget,
            preferences: $preferences,
            primaryBullets: $primaryBullets,
            constraintBullets: $constraintBullets,
            boardChipLabels: $boardChipLabels,
            destinationChipLabels: $destinationChipLabels,
            excludedDestinationLabels: $excludedDestinationLabels,
            excludedFeatureLabels: $excludedFeatureLabels,
            featureChips: $featureChips,
            providerImportUrl: $providerImportUrl,
        );
    }

    /**
     * @param  list<mixed>  $boardPreferences
     * @return list<string>
     */
    private static function boardLabelsFromPreferences(array $boardPreferences): array
    {
        $out = [];
        foreach ($boardPreferences as $raw) {
            $s = trim((string) $raw);
            if ($s === '') {
                continue;
            }
            $label = BoardBasisDisplay::humanLabel($s, null) ?? ucwords(str_replace('_', ' ', $s));
            $out[] = $label;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<mixed>  $destinationPreferences
     * @return list<string>
     */
    private static function destinationLabelsFromPreferences(array $destinationPreferences): array
    {
        $out = [];
        foreach ($destinationPreferences as $raw) {
            $s = trim((string) $raw);
            if ($s === '') {
                continue;
            }
            if (preg_match('/^[0-9_]+$/', $s) === 1) {
                continue;
            }
            $out[] = $s;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<mixed>  $items
     * @return list<string>
     */
    private static function stringListLabels(array $items): array
    {
        $out = [];
        foreach ($items as $raw) {
            $s = trim((string) $raw);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{label: string, emoji: string}>
     */
    private static function featureChipsFromKeys(array $keys): array
    {
        $chips = [];
        foreach ($keys as $key) {
            $k = strtolower(trim($key));
            if ($k === '') {
                continue;
            }
            $chips[] = [
                'emoji' => self::FEATURE_EMOJI[$k] ?? '✓',
                'label' => self::FEATURE_LABEL[$k] ?? ucwords(str_replace('_', ' ', $k)),
            ];
        }

        return $chips;
    }

    /** @var array<string, string> */
    private const FEATURE_EMOJI = [
        'family_friendly' => '👨‍👩‍👧',
        'near_beach' => '🏖',
        'walkable' => '🚶',
        'swimming_pool' => '🏊',
        'kids_club' => '🧸',
        'adults_only' => '🍷',
        'all_inclusive' => '🍽',
        'quiet_relaxing' => '🧘',
        'near_nightlife' => '🎵',
        'spa_wellness' => '🧖',
    ];

    /** @var array<string, string> */
    private const FEATURE_LABEL = [
        'family_friendly' => 'Family friendly',
        'near_beach' => 'Near beach',
        'walkable' => 'Walkable area',
        'swimming_pool' => 'Swimming pool',
        'kids_club' => 'Kids club',
        'adults_only' => 'Adults only',
        'all_inclusive' => 'All inclusive',
        'quiet_relaxing' => 'Quiet & relaxing',
        'near_nightlife' => 'Near nightlife',
        'spa_wellness' => 'Spa & wellness',
    ];

    private static function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m > 0 ? $h.'h '.$m.'m' : $h.'h';
    }

    private static function formatDateRange(?string $start, ?string $end): string
    {
        if ($start && $end) {
            return date('j M', strtotime($start)).' – '.date('j M Y', strtotime($end));
        }

        if ($start) {
            return 'From '.date('j M Y', strtotime($start));
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
            return $min.' night'.($min === 1 ? '' : 's');
        }

        return $min.'–'.$max.' nights';
    }
}
