<?php

namespace App\Http\Controllers;

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
        $resultsQuery = trim((string) $request->query('q', ''));
        $resultsQualifiedOnly = $request->boolean('qualified');

        $holidaysTotal = $browseHolidays->totalUnfilteredCount();
        $results = $browseHolidays->paginate($request);

        return view('holidays.index', [
            'results' => $results,
            'holidaysTotal' => $holidaysTotal,
            'resultsSort' => $resultsSort,
            'resultsQuery' => $resultsQuery,
            'resultsQualifiedOnly' => $resultsQualifiedOnly,
        ]);
    }
}
