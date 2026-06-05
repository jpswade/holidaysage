<?php

namespace App\Http\Controllers;

use App\Models\SavedHolidaySearch;
use App\Services\SavedHolidaySearchRunDiff;
use App\ViewModels\SearchSummaryViewModel;
use Illuminate\View\View;

/**
 * Public, read-only view of a saved search the owner has chosen to share.
 */
class SharedSavedHolidaySearchController extends Controller
{
    public function show(string $token, SavedHolidaySearchRunDiff $diff): View
    {
        $search = SavedHolidaySearch::query()
            ->where('share_token', $token)
            ->where('sharing_enabled', true)
            ->first();

        abort_if($search === null, 404);

        $diffData = $diff->diffForSearch($search);

        return view('saved-searches.shared', [
            'search' => $search,
            'summary' => SearchSummaryViewModel::fromModel($search),
            'latestRun' => $diffData['latest'],
            'latestCards' => $diffData['latestCards'],
            'newCards' => $diffData['newCards'],
            'improvedCards' => $diffData['improvedCards'],
        ]);
    }
}
