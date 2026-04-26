<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Shared list constraints and sorting for scored holiday result lists (saved search show, /holidays, etc.).
 */
final class ScoredHolidayResultsFilter
{
    public function normaliseSort(string $raw): string
    {
        return match ($raw) {
            'price_low', 'price_high', 'score' => $raw,
            default => 'rank',
        };
    }

    public function applyListConstraints(Builder|Relation $query, Request $request): void
    {
        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->whereHas('holidayPackage', function (Builder $packageQuery) use ($like): void {
                $packageQuery->whereHas('hotel', function (Builder $hotelQuery) use ($like): void {
                    $hotelQuery->where('hotel_name', 'like', $like)
                        ->orWhere('resort_name', 'like', $like)
                        ->orWhere('destination_name', 'like', $like);
                });
            });
        }

        if ($request->boolean('qualified')) {
            $query->where('is_disqualified', false);
        }
    }

    public function applySort(Builder|Relation $query, string $sort): void
    {
        $query->reorder();
        match ($sort) {
            'price_low' => $query
                ->leftJoin('holiday_packages as hp_sort', 'hp_sort.id', '=', 'scored_holiday_options.holiday_package_id')
                ->select('scored_holiday_options.*')
                ->orderByRaw('COALESCE(hp_sort.price_total, 999999999) asc')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            'price_high' => $query
                ->leftJoin('holiday_packages as hp_sort', 'hp_sort.id', '=', 'scored_holiday_options.holiday_package_id')
                ->select('scored_holiday_options.*')
                ->orderByRaw('COALESCE(hp_sort.price_total, 0) desc')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            'score' => $query
                ->orderByDesc('scored_holiday_options.overall_score')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            default => $query
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position')
                ->orderByDesc('scored_holiday_options.overall_score'),
        };
    }
}
