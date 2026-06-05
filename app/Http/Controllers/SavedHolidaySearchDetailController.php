<?php

namespace App\Http\Controllers;

use App\Models\SavedHolidaySearch;
use App\Services\SavedHolidaySearchRunDiff;
use App\ViewModels\SearchSummaryViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedHolidaySearchDetailController extends Controller
{
    public function show(SavedHolidaySearch $savedHolidaySearch, SavedHolidaySearchRunDiff $diff): View
    {
        $diffData = $diff->diffForSearch($savedHolidaySearch);

        return view('saved-searches.show', [
            'search' => $savedHolidaySearch,
            'summary' => SearchSummaryViewModel::fromModel($savedHolidaySearch),
            'latestRun' => $diffData['latest'],
            'previousRun' => $diffData['previous'],
            'latestCards' => $diffData['latestCards'],
            'newCards' => $diffData['newCards'],
            'improvedCards' => $diffData['improvedCards'],
            'isOwnerView' => true,
        ]);
    }

    public function sharing(Request $request, SavedHolidaySearch $savedHolidaySearch): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:enable,disable,rotate'],
        ]);

        match ($data['action']) {
            'enable' => $savedHolidaySearch->enableSharing(),
            'disable' => $savedHolidaySearch->disableSharing(),
            'rotate' => $savedHolidaySearch->rotateShareToken(),
        };

        return redirect()->route('saved-searches.show', $savedHolidaySearch);
    }
}
