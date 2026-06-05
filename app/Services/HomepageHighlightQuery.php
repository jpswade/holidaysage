<?php

namespace App\Services;

use App\Models\ScoredHolidayOption;
use App\ViewModels\ResultCardViewModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Curated rails for the homepage. Always real, persisted data — no fixtures or mocks.
 *
 * Each rail uses the same "best canonical row per package" rule as global browse, then
 * applies a facet-specific where/orderBy and caps the result so the homepage stays calm.
 */
final class HomepageHighlightQuery
{
    public const FAMILY = 'family';

    public const SHORT_TRANSFER = 'short_transfer';

    public const BEST_VALUE = 'best_value';

    public const ALL_INCLUSIVE = 'all_inclusive';

    private const RAIL_LIMIT = 4;

    /**
     * @return array<self::*, list<array{viewModel: ResultCardViewModel, displayRank: int}>>
     */
    public function railsForHomepage(): array
    {
        return [
            self::FAMILY => $this->family(),
            self::SHORT_TRANSFER => $this->shortTransfer(),
            self::BEST_VALUE => $this->bestValue(),
            self::ALL_INCLUSIVE => $this->allInclusive(),
        ];
    }

    /** @return list<array{viewModel: ResultCardViewModel, displayRank: int}> */
    public function family(): array
    {
        return $this->materialise(
            $this->base()
                ->whereHas('holidayPackage.hotel', function (Builder $q): void {
                    $q->where(function (Builder $q2): void {
                        $q2->where('has_kids_club', true)
                            ->orWhere('has_family_rooms', true)
                            ->orWhere('is_family_friendly', true);
                    });
                })
                ->orderByRaw('scored_holiday_options.family_fit_score IS NULL')
                ->orderByDesc('scored_holiday_options.family_fit_score')
                ->orderByDesc('scored_holiday_options.overall_score')
                ->limit(self::RAIL_LIMIT),
        );
    }

    /** @return list<array{viewModel: ResultCardViewModel, displayRank: int}> */
    public function shortTransfer(): array
    {
        return $this->materialise(
            $this->base()
                ->leftJoin('holiday_packages as hp_rail', 'hp_rail.id', '=', 'scored_holiday_options.holiday_package_id')
                ->select('scored_holiday_options.*')
                ->whereNotNull('hp_rail.transfer_minutes')
                ->where('hp_rail.transfer_minutes', '<=', 60)
                ->orderBy('hp_rail.transfer_minutes')
                ->orderByDesc('scored_holiday_options.overall_score')
                ->limit(self::RAIL_LIMIT),
        );
    }

    /** @return list<array{viewModel: ResultCardViewModel, displayRank: int}> */
    public function bestValue(): array
    {
        return $this->materialise(
            $this->base()
                ->whereNotNull('scored_holiday_options.value_score')
                ->orderByDesc('scored_holiday_options.value_score')
                ->orderByDesc('scored_holiday_options.overall_score')
                ->limit(self::RAIL_LIMIT),
        );
    }

    /** @return list<array{viewModel: ResultCardViewModel, displayRank: int}> */
    public function allInclusive(): array
    {
        return $this->materialise(
            $this->base()
                ->whereHas('holidayPackage', function (Builder $q): void {
                    $q->whereIn('board_type', ['all_inclusive', 'AI', '5']);
                })
                ->orderByDesc('scored_holiday_options.overall_score')
                ->limit(self::RAIL_LIMIT),
        );
    }

    /**
     * Base query: same "best canonical row per package" rule as BrowseHolidaysQuery.
     */
    private function base(): Builder
    {
        $correlated = 'scored_holiday_options.id = (
            select s2.id from scored_holiday_options as s2
            where s2.holiday_package_id = scored_holiday_options.holiday_package_id
            order by s2.overall_score desc, s2.id desc
            limit 1
        )';

        return ScoredHolidayOption::query()
            ->whereHas('holidayPackage', function (Builder $q): void {
                $q->whereNotNull('hotel_id');
            })
            ->whereRaw($correlated)
            ->where('is_disqualified', false)
            ->with([
                'search',
                'holidayPackage.hotel.photos',
                'holidayPackage.providerSource',
            ]);
    }

    /**
     * @return list<array{viewModel: ResultCardViewModel, displayRank: int}>
     */
    private function materialise(Builder $query): array
    {
        $rows = $query->get();
        $out = [];
        $index = 1;
        foreach ($rows as $row) {
            $out[] = [
                'viewModel' => ResultCardViewModel::fromModel($row),
                'displayRank' => $index++,
            ];
        }

        return $out;
    }
}
