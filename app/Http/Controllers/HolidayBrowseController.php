<?php

namespace App\Http\Controllers;

use App\Services\BrowseHolidaysQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayBrowseController extends Controller
{
    /**
     * @return array<int, array{label: string, country: string, query: array<string, mixed>}>
     */
    public static function destinationShortcuts(): array
    {
        return [
            ['label' => 'Majorca', 'country' => 'Spain', 'query' => ['destination_preferences' => ['Majorca']]],
            ['label' => 'Tenerife', 'country' => 'Spain', 'query' => ['destination_preferences' => ['Tenerife']]],
            ['label' => 'Lanzarote', 'country' => 'Spain', 'query' => ['destination_preferences' => ['Lanzarote']]],
            ['label' => 'Costa del Sol', 'country' => 'Spain', 'query' => ['destination_preferences' => ['Costa del Sol']]],
            ['label' => 'Algarve', 'country' => 'Portugal', 'query' => ['destination_preferences' => ['Algarve']]],
            ['label' => 'Crete', 'country' => 'Greece', 'query' => ['destination_preferences' => ['Crete']]],
        ];
    }

    /**
     * @return array<int, array{label: string, query: array<string, mixed>}>
     */
    public static function themeShortcuts(): array
    {
        return [
            ['label' => 'Family friendly', 'query' => ['feature_preferences' => ['family_friendly', 'near_beach']]],
            ['label' => 'Adults only', 'query' => ['feature_preferences' => ['adults_only', 'quiet_relaxing']]],
            ['label' => 'All inclusive', 'query' => ['feature_preferences' => ['all_inclusive']]],
            ['label' => 'Near the beach', 'query' => ['feature_preferences' => ['near_beach']]],
            ['label' => 'Kids club', 'query' => ['feature_preferences' => ['kids_club', 'family_friendly']]],
            ['label' => 'Spa & wellness', 'query' => ['feature_preferences' => ['spa_wellness']]],
        ];
    }

    /**
     * @return array<int, array{title: string, description: string, query: array<string, mixed>}>
     */
    public static function tripIdeaShortcuts(): array
    {
        return [
            [
                'title' => 'Summer week from Manchester',
                'description' => '7 nights, July window, party of two.',
                'query' => [
                    'departure_airport_code' => 'MAN',
                    'travel_start_date' => '2026-07-01',
                    'travel_end_date' => '2026-07-31',
                    'duration_min_nights' => 7,
                    'duration_max_nights' => 7,
                    'adults' => 2,
                    'children' => 0,
                ],
            ],
            [
                'title' => 'Family break, flexible dates',
                'description' => '7–10 nights, two adults and two children.',
                'query' => [
                    'departure_airport_code' => 'MAN',
                    'duration_min_nights' => 7,
                    'duration_max_nights' => 10,
                    'adults' => 2,
                    'children' => 2,
                    'feature_preferences' => ['family_friendly', 'kids_club'],
                ],
            ],
            [
                'title' => 'Short hop, max ten nights',
                'description' => 'Birmingham departure, tighten the window when you are ready.',
                'query' => [
                    'departure_airport_code' => 'BHX',
                    'duration_min_nights' => 5,
                    'duration_max_nights' => 10,
                    'adults' => 2,
                    'children' => 0,
                ],
            ],
        ];
    }

    public function index(Request $request, BrowseHolidaysQuery $browseHolidays): View
    {
        $provider = strtolower((string) $request->query('provider', 'all'));
        if (! in_array($provider, ['all', 'jet2', 'tui'], true)) {
            $provider = 'all';
        }
        $board = (string) $request->query('board', 'all');
        if (! in_array($board, ['all', 'all_inclusive', 'half_board', 'bed_breakfast', 'self_catering', 'other'], true)) {
            $board = 'all';
        }
        $q = (string) $request->query('q', '');

        $holidaysTotal = $browseHolidays->totalUnfilteredCount();
        $cards = $browseHolidays->filteredCards($request);
        $holidays = [];
        foreach ($cards as $i => $item) {
            $holidays[] = [
                'viewModel' => $item['viewModel'],
                'search' => $item['row']->search,
                'displayRank' => $i + 1,
            ];
        }
        $holidaysShown = count($holidays);

        return view('holidays.index', [
            'holidays' => $holidays,
            'holidaysTotal' => $holidaysTotal,
            'holidaysShown' => $holidaysShown,
            'filterProvider' => $provider,
            'filterBoard' => $board,
            'filterQuery' => $q,
            'destinationShortcuts' => self::destinationShortcuts(),
            'themeShortcuts' => self::themeShortcuts(),
            'tripIdeaShortcuts' => self::tripIdeaShortcuts(),
        ]);
    }
}
