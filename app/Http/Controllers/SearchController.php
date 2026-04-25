<?php

namespace App\Http\Controllers;

use App\Enums\SavedHolidaySearchRunType;
use App\Enums\SavedHolidaySearchStatus;
use App\Http\Requests\ImportSearchUrlRequest;
use App\Http\Requests\StoreSavedHolidaySearchRequest;
use App\Jobs\RefreshSavedHolidaySearchJob;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Services\Imports\ImportUrlParserRegistry;
use App\ViewModels\ResultCardViewModel;
use App\ViewModels\SearchSummaryViewModel;
use App\ViewModels\TopPickViewModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class SearchController extends Controller
{
    public function index(): View
    {
        $searches = SavedHolidaySearch::query()
            ->withCount('scoredOptions')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('last_scored_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (SavedHolidaySearch $search): array => [
                'search' => $search,
                'summary' => SearchSummaryViewModel::fromModel($search),
            ]);

        return view('searches.index', [
            'searches' => $searches,
        ]);
    }

    public function create(): View
    {
        return view('searches.create');
    }

    public function store(StoreSavedHolidaySearchRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $search = SavedHolidaySearch::query()->create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'provider_import_url' => $validated['provider_import_url'] ?? null,
            'departure_airport_code' => strtoupper((string) $validated['departure_airport_code']),
            'travel_start_date' => $validated['travel_start_date'] ?? null,
            'travel_end_date' => $validated['travel_end_date'] ?? null,
            'travel_date_flexibility_days' => (int) ($validated['travel_date_flexibility_days'] ?? 0),
            'duration_min_nights' => (int) $validated['duration_min_nights'],
            'duration_max_nights' => (int) $validated['duration_max_nights'],
            'adults' => (int) $validated['adults'],
            'children' => (int) ($validated['children'] ?? 0),
            'infants' => (int) ($validated['infants'] ?? 0),
            'budget_total' => $validated['budget_total'] ?? null,
            'max_flight_minutes' => $validated['max_flight_minutes'] ?? null,
            'max_transfer_minutes' => $validated['max_transfer_minutes'] ?? null,
            'board_preferences' => $validated['board_preferences'] ?? null,
            'destination_preferences' => $validated['destination_preferences'] ?? null,
            'feature_preferences' => $validated['feature_preferences'] ?? null,
            'status' => $validated['status'] ?? SavedHolidaySearchStatus::Active->value,
        ]);

        return redirect()
            ->route('searches.show', $search)
            ->with('status', 'Search created. We can start tracking results now.');
    }

    public function show(SavedHolidaySearch $search): View
    {
        $search->load(['runs' => fn ($q) => $q->orderByDesc('id')->limit(5)]);
        $latestRun = $search->runs->first() ?: SavedHolidaySearchRun::query()
            ->where('saved_holiday_search_id', $search->id)
            ->latest('id')
            ->first();

        $rows = collect();
        if ($latestRun) {
            $rows = $latestRun->scoredOptions()
                ->with(['holidayPackage.hotel', 'holidayPackage.providerSource'])
                ->orderByRaw('rank_position IS NULL')
                ->orderBy('rank_position')
                ->orderByDesc('overall_score')
                ->limit(5)
                ->get()
                ->map(fn ($row) => ResultCardViewModel::fromModel($row));
        }

        $topPick = $rows->firstWhere('isDisqualified', false);
        $summary = SearchSummaryViewModel::fromModel($search);

        return view('searches.show', [
            'search' => $search,
            'summary' => $summary,
            'latestRun' => $latestRun,
            'topPick' => $topPick ? TopPickViewModel::fromResult($topPick) : null,
            'results' => $rows,
        ]);
    }

    public function results(SavedHolidaySearch $search): RedirectResponse
    {
        return redirect()->route('searches.show', $search);
    }

    public function import(ImportSearchUrlRequest $request, ImportUrlParserRegistry $parsers): JsonResponse
    {
        $url = (string) $request->validated('url');

        try {
            $parser = $parsers->parserFor($url);
            $criteria = $parser->parse($url);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => 'This provider URL is not supported yet.',
            ], 422);
        }

        return response()->json([
            'message' => 'Import criteria extracted.',
            'criteria' => $criteria,
        ]);
    }

    public function refresh(SavedHolidaySearch $search): RedirectResponse
    {
        RefreshSavedHolidaySearchJob::dispatch($search->id, SavedHolidaySearchRunType::Manual->value);

        return redirect()
            ->route('searches.show', $search)
            ->with('status', 'Refresh queued. Results will update shortly.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'saved-search';
        $slug = $base;
        $count = 1;
        while (SavedHolidaySearch::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$count++;
        }

        return $slug;
    }
}
