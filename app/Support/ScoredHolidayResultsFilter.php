<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Shared list constraints and sorting for scored holiday result lists (saved search show, /holidays, etc.).
 *
 * Sorts supported: rank (default "best overall"), score, value, transfer, family, price_low, price_high.
 *
 * Minimal filters (DB only, intentionally calm—not a wall of facets):
 *   - q (keyword on hotel/resort/destination)
 *   - qualified (boolean: hide disqualified)
 *   - provider (provider_source key like "jet2", "tui"; repeatable as array)
 *   - board (board bucket key: all_inclusive, half_board, full_board, bed_breakfast, self_catering, room_only)
 *   - max_transfer (minutes; numeric)
 */
final class ScoredHolidayResultsFilter
{
    public const SORT_RANK = 'rank';

    public const SORT_SCORE = 'score';

    public const SORT_VALUE = 'value';

    public const SORT_TRANSFER = 'transfer';

    public const SORT_FAMILY = 'family';

    public const SORT_PRICE_LOW = 'price_low';

    public const SORT_PRICE_HIGH = 'price_high';

    /** @var array<int, string> */
    public const SORTS = [
        self::SORT_RANK,
        self::SORT_SCORE,
        self::SORT_VALUE,
        self::SORT_TRANSFER,
        self::SORT_FAMILY,
        self::SORT_PRICE_LOW,
        self::SORT_PRICE_HIGH,
    ];

    /** Recognised board buckets and the database `holiday_packages.board_type` strings they match. */
    private const BOARD_BUCKETS = [
        'all_inclusive' => ['all_inclusive', 'AI', '5'],
        'full_board' => ['full_board', 'FB', '4'],
        'half_board' => ['half_board', 'HB', '3'],
        'bed_breakfast' => ['bed_breakfast', 'BB', '2'],
        'room_only' => ['room_only', 'RO', '1'],
        'self_catering' => ['self_catering', 'SC'],
    ];

    public function normaliseSort(string $raw): string
    {
        return in_array($raw, self::SORTS, true) ? $raw : self::SORT_RANK;
    }

    /**
     * @return array{
     *     q: string,
     *     qualified: bool,
     *     providers: list<string>,
     *     boards: list<string>,
     *     max_transfer: int|null,
     * }
     */
    public function normaliseFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'qualified' => $request->boolean('qualified'),
            'providers' => $this->normaliseList($request->query('provider')),
            'boards' => $this->normaliseList($request->query('board'), array_keys(self::BOARD_BUCKETS)),
            'max_transfer' => $this->normaliseMaxTransfer($request->query('max_transfer')),
        ];
    }

    public function applyListConstraints(Builder|Relation $query, Request $request): void
    {
        $filters = $this->normaliseFilters($request);

        if ($filters['q'] !== '') {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->whereHas('holidayPackage', function (Builder $packageQuery) use ($like): void {
                $packageQuery->whereHas('hotel', function (Builder $hotelQuery) use ($like): void {
                    $hotelQuery->where('hotel_name', 'like', $like)
                        ->orWhere('resort_name', 'like', $like)
                        ->orWhere('destination_name', 'like', $like);
                });
            });
        }

        if ($filters['qualified']) {
            $query->where('is_disqualified', false);
        }

        if ($filters['providers'] !== []) {
            $providerKeys = $filters['providers'];
            $query->whereHas('holidayPackage.providerSource', function (Builder $providerQuery) use ($providerKeys): void {
                $providerQuery->whereIn('key', $providerKeys);
            });
        }

        if ($filters['boards'] !== []) {
            $boardValues = $this->boardBucketValues($filters['boards']);
            if ($boardValues !== []) {
                $query->whereHas('holidayPackage', function (Builder $packageQuery) use ($boardValues): void {
                    $packageQuery->whereIn('board_type', $boardValues);
                });
            }
        }

        if ($filters['max_transfer'] !== null) {
            $cap = $filters['max_transfer'];
            $query->whereHas('holidayPackage', function (Builder $packageQuery) use ($cap): void {
                $packageQuery->where('transfer_minutes', '<=', $cap);
            });
        }
    }

    public function applySort(Builder|Relation $query, string $sort): void
    {
        $query->reorder();
        match ($sort) {
            self::SORT_PRICE_LOW => $query
                ->leftJoin('holiday_packages as hp_sort', 'hp_sort.id', '=', 'scored_holiday_options.holiday_package_id')
                ->select('scored_holiday_options.*')
                ->orderByRaw('COALESCE(hp_sort.price_total, 999999999) asc')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            self::SORT_PRICE_HIGH => $query
                ->leftJoin('holiday_packages as hp_sort', 'hp_sort.id', '=', 'scored_holiday_options.holiday_package_id')
                ->select('scored_holiday_options.*')
                ->orderByRaw('COALESCE(hp_sort.price_total, 0) desc')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            self::SORT_SCORE => $query
                ->orderByDesc('scored_holiday_options.overall_score')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            self::SORT_VALUE => $query
                ->orderByRaw('scored_holiday_options.value_score IS NULL')
                ->orderByDesc('scored_holiday_options.value_score')
                ->orderByDesc('scored_holiday_options.overall_score')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            self::SORT_TRANSFER => $query
                ->leftJoin('holiday_packages as hp_sort', 'hp_sort.id', '=', 'scored_holiday_options.holiday_package_id')
                ->select('scored_holiday_options.*')
                ->orderByRaw('COALESCE(hp_sort.transfer_minutes, 999999) asc')
                ->orderByDesc('scored_holiday_options.overall_score')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            self::SORT_FAMILY => $query
                ->orderByRaw('scored_holiday_options.family_fit_score IS NULL')
                ->orderByDesc('scored_holiday_options.family_fit_score')
                ->orderByDesc('scored_holiday_options.overall_score')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            default => $query
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position')
                ->orderByDesc('scored_holiday_options.overall_score'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function sortLabels(): array
    {
        return [
            self::SORT_RANK => 'Best overall',
            self::SORT_SCORE => 'Highest score',
            self::SORT_VALUE => 'Best value',
            self::SORT_TRANSFER => 'Shortest transfer',
            self::SORT_FAMILY => 'Family fit',
            self::SORT_PRICE_LOW => 'Price: low to high',
            self::SORT_PRICE_HIGH => 'Price: high to low',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function boardBucketLabels(): array
    {
        return [
            'all_inclusive' => 'All inclusive',
            'half_board' => 'Half board',
            'full_board' => 'Full board',
            'bed_breakfast' => 'Bed & breakfast',
            'self_catering' => 'Self catering',
            'room_only' => 'Room only',
        ];
    }

    /**
     * @param  mixed  $raw
     * @param  list<string>|null  $allowed  When provided, restrict to allowlist (after lowercasing).
     * @return list<string>
     */
    private function normaliseList(mixed $raw, ?array $allowed = null): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $values = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $clean = strtolower(trim((string) $value));
            if ($clean === '') {
                continue;
            }
            if ($allowed !== null && ! in_array($clean, $allowed, true)) {
                continue;
            }
            if (! in_array($clean, $out, true)) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    private function normaliseMaxTransfer(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }
        $minutes = (int) $raw;

        return $minutes > 0 && $minutes <= 24 * 60 ? $minutes : null;
    }

    /**
     * @param  list<string>  $buckets
     * @return list<string>
     */
    private function boardBucketValues(array $buckets): array
    {
        $out = [];
        foreach ($buckets as $bucket) {
            $values = self::BOARD_BUCKETS[$bucket] ?? null;
            if ($values === null) {
                continue;
            }
            foreach ($values as $value) {
                if (! in_array($value, $out, true)) {
                    $out[] = $value;
                }
            }
        }

        return $out;
    }
}
