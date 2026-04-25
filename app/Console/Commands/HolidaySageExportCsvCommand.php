<?php

namespace App\Console\Commands;

use App\Models\HolidayPackage;
use App\Models\SavedHolidaySearchRun;
use App\Models\ScoredHolidayOption;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class HolidaySageExportCsvCommand extends Command
{
    /** @var array<string,string> */
    private const BOARD_LABELS = [
        '1' => 'Room Only',
        '2' => 'Bed & Breakfast',
        '3' => 'Half Board',
        '4' => 'Full Board',
        '5' => 'All Inclusive',
        'AI' => 'All Inclusive',
        'FB' => 'Full Board',
        'HB' => 'Half Board',
        'BB' => 'Bed & Breakfast',
        'SC' => 'Self Catering',
        'RO' => 'Room Only',
    ];

    protected $signature = 'holidaysage:export-csv
                            {--path= : Output CSV path (defaults to storage/app/exports/holidaysage-output.csv)}
                            {--provider=jet2 : Provider key to export}
                            {--run-id= : Limit scores to a specific saved_holiday_search_run_id}';

    protected $description = 'Export holiday package data in a CSV format aligned with the Beachin output shape.';

    /** @var list<string> */
    private const COLUMNS = [
        'id', 'source', 'hotel_name', 'url', 'country', 'region', 'parent_region', 'resort', 'airport',
        'distance_to_airport_km', 'private_transfer_time_by_distance_est_mins', 'flight_time_hours_est', 'transfer_time_mins_est', 'transfer_type',
        'board_recommended', 'beachfront', 'distance_to_beach_m', 'kids_club', 'kids_club_age_min', 'play_area', 'splash_pool', 'water_slides',
        'evening_entertainment', 'kids_disco', 'gym', 'spa', 'adults_only_area', 'promenade', 'near_shops', 'distance_to_shops_m', 'cafes_bars',
        'distance_to_cafes_bars_m', 'distance_from_resort_centre_m', 'harbour', 'has_lift', 'steps_mentioned', 'steps_count', 'accessibility_issues',
        'ground_floor_available', 'accessibility_notes', 'review_score', 'review_count', 'official_star_rating', 'provider_star_rating', 'total_price',
        'price_per_person', 'outbound_flight', 'inbound_flight', 'rooms_seaview_balcony', 'cots_available', 'introduction_snippet', 'key_selling_points',
        'style_keywords', 'local_beer', 'three_course_meal_for_two', 'rooms_count', 'blocks_count', 'floors_count', 'restaurants_count', 'bars_count',
        'pools_count', 'sports_leisure_count', 'score_travel', 'score_kids', 'score_board', 'score_walkability', 'score_reviews', 'score_accessibility',
        'score_value', 'score_aesthetics', 'score_size', 'overall_score_10', 'disqualified_reason',
    ];

    public function handle(): int
    {
        $provider = (string) $this->option('provider');
        $runId = $this->option('run-id');
        $path = (string) ($this->option('path') ?: storage_path('app/exports/holidaysage-output.csv'));
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            $this->error('Failed to create output directory: '.$dir);

            return self::FAILURE;
        }

        $rows = $this->buildRows($provider, is_numeric($runId) ? (int) $runId : null);
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            $this->error('Failed to open output path: '.$path);

            return self::FAILURE;
        }

        fputcsv($handle, self::COLUMNS);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($column) => $row[$column] ?? '', self::COLUMNS));
        }
        fclose($handle);

        $this->info(sprintf('Exported %d rows to %s', count($rows), $path));

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRows(string $providerKey, ?int $runId): array
    {
        $runPackageIds = null;
        if ($runId !== null) {
            $run = SavedHolidaySearchRun::query()->find($runId);
            $rawIds = is_array($run?->imported_holiday_package_ids) ? $run->imported_holiday_package_ids : [];
            $runPackageIds = array_values(array_unique(array_map('intval', array_filter($rawIds, fn ($id) => is_numeric($id)))));
        }

        $latestScored = ScoredHolidayOption::query()
            ->selectRaw('MAX(id) as id, holiday_package_id')
            ->when($runId !== null, fn (Builder $q) => $q->where('saved_holiday_search_run_id', $runId))
            ->groupBy('holiday_package_id');

        $packages = HolidayPackage::query()
            ->select('holiday_packages.*')
            ->with(['hotel', 'providerSource'])
            ->leftJoinSub($latestScored, 'latest_scores', fn ($join) => $join->on('latest_scores.holiday_package_id', '=', 'holiday_packages.id'))
            ->leftJoin('scored_holiday_options as s', 's.id', '=', 'latest_scores.id')
            ->addSelect([
                's.overall_score',
                's.travel_score',
                's.family_fit_score',
                's.board_score',
                's.location_score',
                's.value_score',
                's.disqualification_reasons',
            ])
            ->whereHas('providerSource', fn (Builder $q) => $q->where('key', $providerKey))
            ->when($runId !== null, fn (Builder $q) => $q->whereNotNull('latest_scores.id'))
            ->when($runPackageIds !== null && $runPackageIds !== [], fn (Builder $q) => $q->whereIn('holiday_packages.id', $runPackageIds))
            ->orderBy('holiday_packages.id')
            ->get();

        $rows = [];
        foreach ($packages as $package) {
            $hotel = $package->hotel;
            if (! $hotel) {
                continue;
            }
            $hotelRaw = is_array($hotel->raw_attributes) ? Arr::get($hotel->raw_attributes, 'hotel_extra', []) : [];
            $packageRaw = is_array($package->raw_attributes) ? Arr::get($package->raw_attributes, 'package_extra', []) : [];
            $property = is_array($hotelRaw['property'] ?? null) ? $hotelRaw['property'] : [];
            $keyPoints = is_array($property['keySellingPoints'] ?? null) ? $property['keySellingPoints'] : [];
            $roomsSeaView = Arr::get($hotelRaw, 'rooms_with_seaview_balcony', []);
            $disq = $this->normaliseDisqualificationReasons($package->getAttribute('disqualification_reasons'));

            $rows[] = [
                'id' => $hotel->provider_hotel_id ?: $hotel->hotel_slug,
                'source' => $providerKey,
                'hotel_name' => $hotel->hotel_name,
                'url' => $this->absoluteUrl($package->provider_url),
                'country' => $hotel->destination_country,
                'region' => $hotel->destination_name,
                'parent_region' => '',
                'resort' => $hotel->resort_name,
                'airport' => $package->airport_code,
                'distance_to_airport_km' => $hotel->distance_to_airport_km,
                'private_transfer_time_by_distance_est_mins' => $this->privateTransferMinutesByDistance($hotel->distance_to_airport_km),
                'flight_time_hours_est' => $package->flight_time_hours_est,
                'transfer_time_mins_est' => $package->transfer_minutes,
                'transfer_type' => $package->transfer_type ?? '',
                'board_recommended' => $this->boardLabel($package->board_recommended ?: $package->board_type),
                'beachfront' => $this->boolToCsv($hotel->distance_to_beach_meters === 0 ? true : null),
                'distance_to_beach_m' => $hotel->distance_to_beach_meters,
                'kids_club' => $this->boolToCsv($hotel->has_kids_club),
                'kids_club_age_min' => $hotel->kids_club_age_min,
                'play_area' => $this->boolToCsv($hotel->play_area),
                'splash_pool' => $this->boolToCsv($hotel->has_waterpark),
                'water_slides' => $this->boolToCsv($hotel->has_waterpark),
                'evening_entertainment' => $this->boolToCsv($hotel->evening_entertainment),
                'kids_disco' => $this->boolToCsv($hotel->kids_disco),
                'gym' => $this->boolToCsv($hotel->gym),
                'spa' => $this->boolToCsv($hotel->spa),
                'adults_only_area' => $this->boolToCsv($hotel->adults_only_area),
                'promenade' => $this->boolToCsv($hotel->promenade),
                'near_shops' => $this->boolToCsv($hotel->near_shops),
                'distance_to_shops_m' => $hotel->distance_to_shops_meters,
                'cafes_bars' => $this->boolToCsv($hotel->cafes_bars),
                'distance_to_cafes_bars_m' => $hotel->distance_to_cafes_bars_meters,
                'distance_from_resort_centre_m' => $hotel->distance_to_centre_meters,
                'harbour' => $this->boolToCsv($hotel->harbour),
                'has_lift' => $this->boolToCsv($hotel->has_lift),
                'steps_mentioned' => $this->boolToCsv($hotel->accessibility_issues && str_contains((string) $hotel->accessibility_issues, 'steps')),
                'steps_count' => $hotel->steps_count,
                'accessibility_issues' => $hotel->accessibility_issues,
                'ground_floor_available' => $this->boolToCsv($hotel->ground_floor_available),
                'accessibility_notes' => $hotel->accessibility_notes ?? '',
                'review_score' => $hotel->review_score,
                'review_count' => $hotel->review_count,
                'official_star_rating' => $hotel->star_rating,
                'provider_star_rating' => $hotel->star_rating,
                'total_price' => $package->price_total,
                'price_per_person' => $package->price_per_person,
                'outbound_flight' => $package->outbound_flight_time_text ?: (string) Arr::get($packageRaw, 'outbound_flight', ''),
                'inbound_flight' => $package->inbound_flight_time_text ?: (string) Arr::get($packageRaw, 'inbound_flight', ''),
                'rooms_seaview_balcony' => is_array($roomsSeaView) ? implode('; ', $roomsSeaView) : '',
                'cots_available' => $this->boolToCsv($hotel->cots_available),
                'introduction_snippet' => (string) ($hotel->introduction_snippet ?? Arr::get($hotelRaw, 'introduction_text', '')),
                'key_selling_points' => implode('; ', array_filter(array_map('strval', $keyPoints))),
                'style_keywords' => (string) ($hotel->style_keywords ?? ''),
                'local_beer' => $package->local_beer_price !== null ? '£'.number_format((float) $package->local_beer_price, 2) : '',
                'three_course_meal_for_two' => $package->three_course_meal_for_two_price !== null ? '£'.number_format((float) $package->three_course_meal_for_two_price, 2) : '',
                'rooms_count' => $hotel->rooms_count,
                'blocks_count' => $hotel->blocks_count,
                'floors_count' => $hotel->floors_count,
                'restaurants_count' => $hotel->restaurants_count,
                'bars_count' => $hotel->bars_count,
                'pools_count' => $hotel->pools_count,
                'sports_leisure_count' => $hotel->sports_leisure_count,
                'score_travel' => $package->getAttribute('travel_score'),
                'score_kids' => $package->getAttribute('family_fit_score'),
                'score_board' => $package->getAttribute('board_score'),
                'score_walkability' => $package->getAttribute('location_score'),
                'score_reviews' => '',
                'score_accessibility' => '',
                'score_value' => $package->getAttribute('value_score'),
                'score_aesthetics' => '',
                'score_size' => '',
                'overall_score_10' => $package->getAttribute('overall_score'),
                'disqualified_reason' => $disq,
            ];
        }

        return $rows;
    }

    private function boolToCsv(?bool $value): string
    {
        return $value === null ? '' : ($value ? 'TRUE' : 'FALSE');
    }

    private function absoluteUrl(string $providerUrl): string
    {
        if ($providerUrl === '') {
            return '';
        }
        if (str_starts_with($providerUrl, 'http://') || str_starts_with($providerUrl, 'https://')) {
            return $providerUrl;
        }

        return 'https://www.jet2holidays.com'.(str_starts_with($providerUrl, '/') ? $providerUrl : '/'.$providerUrl);
    }

    private function normaliseDisqualificationReasons(mixed $value): string
    {
        if (is_array($value)) {
            return implode('; ', array_values(array_filter(array_map('strval', $value))));
        }
        if (is_string($value)) {
            return $value;
        }

        return '';
    }

    private function boardLabel(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }
        $v = strtoupper(trim($value));
        if (isset(self::BOARD_LABELS[$v])) {
            return self::BOARD_LABELS[$v];
        }
        return match (true) {
            is_numeric($v) && isset(self::BOARD_LABELS[$v]) => self::BOARD_LABELS[$v],
            str_contains($v, 'ALL') => self::BOARD_LABELS['AI'],
            str_contains($v, 'FULL') => self::BOARD_LABELS['FB'],
            str_contains($v, 'HALF') => self::BOARD_LABELS['HB'],
            str_contains($v, 'BREAKFAST') => self::BOARD_LABELS['BB'],
            str_contains($v, 'SELF') => self::BOARD_LABELS['SC'],
            str_contains($v, 'ROOM ONLY') => self::BOARD_LABELS['RO'],
            default => $value,
        };
    }

    private function privateTransferMinutesByDistance(mixed $distanceKm): string
    {
        if (! is_numeric($distanceKm)) {
            return '';
        }

        $minutes = (int) round(((float) $distanceKm / 50) * 60);

        return (string) $minutes;
    }
}
