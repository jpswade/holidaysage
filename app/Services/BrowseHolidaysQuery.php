<?php

namespace App\Services;

use App\Models\SavedHolidaySearch;
use App\Models\ScoredHolidayOption;
use App\Support\ScoredHolidayResultsFilter;
use App\ViewModels\ResultCardViewModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * /holidays: global list is one best row per holiday package; with `?search_id=` the list matches the latest
 * run for that saved search, using the same filters as global browse.
 */
final class BrowseHolidaysQuery
{
    public function __construct(
        private readonly ScoredHolidayResultsFilter $scoredHolidayResultsFilter,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array{viewModel: ResultCardViewModel, search: SavedHolidaySearch|null, displayRank: int}>
     */
    public function paginate(Request $request, ?SavedHolidaySearch $searchScope = null): LengthAwarePaginator
    {
        $perPage = (int) config('holidaysage.search_results_per_page', 18);
        $sort = $this->scoredHolidayResultsFilter->normaliseSort((string) $request->query('sort', 'rank'));

        $query = $this->listQueryForRequest($searchScope);
        $this->scoredHolidayResultsFilter->applyListConstraints($query, $request);
        $this->scoredHolidayResultsFilter->applySort($query, $sort);

        $paginator = $query->paginate($perPage)->withQueryString();
        $paginator->setCollection(
            $paginator->getCollection()->map(function (ScoredHolidayOption $row, int $i) use ($paginator): array {
                return [
                    'viewModel' => ResultCardViewModel::fromModel($row),
                    'search' => $row->search,
                    'displayRank' => (int) (($paginator->firstItem() ?? 0) + $i),
                ];
            })
        );

        return $paginator;
    }

    public function totalUnfilteredCount(?SavedHolidaySearch $searchScope = null): int
    {
        if ($searchScope) {
            $run = $searchScope->runs()->orderByDesc('id')->first();
            if (! $run) {
                return 0;
            }

            return (int) $run->scoredOptions()->count();
        }

        return (int) $this->baseQuery()->count();
    }

    /**
     * @return Builder|Relation
     */
    private function listQueryForRequest(?SavedHolidaySearch $searchScope)
    {
        if ($searchScope) {
            $run = $searchScope->runs()->orderByDesc('id')->first();
            if (! $run) {
                return ScoredHolidayOption::query()->whereRaw('0 = 1');
            }

            return $run->scoredOptions()->with([
                'search',
                'holidayPackage.hotel.photos',
                'holidayPackage.providerSource',
            ]);
        }

        return $this->baseListQuery();
    }

    private function baseListQuery(): Builder
    {
        return $this->baseQuery()->with([
            'search',
            'holidayPackage.hotel.photos',
            'holidayPackage.providerSource',
        ]);
    }

    /**
     * One row per package: the highest {@see ScoredHolidayOption::overall_score} (latest id on ties).
     */
    private function baseQuery(): Builder
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
            ->whereRaw($correlated);
    }
}
