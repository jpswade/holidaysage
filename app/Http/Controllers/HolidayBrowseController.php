<?php

namespace App\Http\Controllers;

use App\Enums\ProviderSourceStatus;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Services\BrowseHolidaysQuery;
use App\Support\ScoredHolidayResultsFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayBrowseController extends Controller
{
    public function __construct(
        private readonly ScoredHolidayResultsFilter $scoredHolidayResultsFilter,
    ) {}

    public function index(Request $request, BrowseHolidaysQuery $browseHolidays): View
    {
        $resultsSort = $this->scoredHolidayResultsFilter->normaliseSort((string) $request->query('sort', 'rank'));
        $filters = $this->scoredHolidayResultsFilter->normaliseFilters($request);

        $searchScope = $this->searchScopeFromRequest($request);
        $holidaysTotal = $browseHolidays->totalUnfilteredCount($searchScope);
        $results = $browseHolidays->paginate($request, $searchScope);

        return view('holidays.index', [
            'results' => $results,
            'holidaysTotal' => $holidaysTotal,
            'resultsSort' => $resultsSort,
            'resultsQuery' => $filters['q'],
            'resultsQualifiedOnly' => $filters['qualified'],
            'resultsProviders' => $filters['providers'],
            'resultsBoards' => $filters['boards'],
            'resultsMaxTransfer' => $filters['max_transfer'],
            'availableProviders' => $this->availableProviders(),
            'scopedSearch' => $searchScope,
        ]);
    }

    private function searchScopeFromRequest(Request $request): ?SavedHolidaySearch
    {
        if (! $request->filled('search_id')) {
            return null;
        }
        if (! is_numeric($request->query('search_id')) || (int) $request->query('search_id') < 1) {
            abort(404);
        }
        $search = SavedHolidaySearch::query()->find((int) $request->query('search_id'));
        abort_if($search === null, 404);

        return $search;
    }

    /**
     * @return array<string, string> Map of provider key → display name (active providers only).
     */
    private function availableProviders(): array
    {
        return ProviderSource::query()
            ->where('status', ProviderSourceStatus::Active)
            ->orderBy('name')
            ->pluck('name', 'key')
            ->all();
    }
}
