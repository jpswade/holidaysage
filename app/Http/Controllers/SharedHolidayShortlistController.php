<?php

namespace App\Http\Controllers;

use App\Models\HolidayShortlist;
use App\ViewModels\ResultCardViewModel;
use Illuminate\View\View;

/**
 * Public, read-only view of a shortlist that an owner has opted to share.
 */
class SharedHolidayShortlistController extends Controller
{
    public function show(string $token): View
    {
        $shortlist = HolidayShortlist::query()
            ->where('share_token', $token)
            ->where('sharing_enabled', true)
            ->first();

        abort_if($shortlist === null, 404);

        $shortlist->load([
            'user:id,name',
            'items.scoredHolidayOption.search',
            'items.scoredHolidayOption.holidayPackage.hotel.photos',
            'items.scoredHolidayOption.holidayPackage.providerSource',
        ]);

        $cards = [];
        foreach ($shortlist->items as $item) {
            $option = $item->scoredHolidayOption;
            if ($option === null || $option->holidayPackage === null) {
                continue;
            }
            $cards[] = [
                'item' => $item,
                'viewModel' => ResultCardViewModel::fromModel($option),
            ];
        }

        return view('shortlists.shared', [
            'shortlist' => $shortlist,
            'cards' => $cards,
        ]);
    }
}
