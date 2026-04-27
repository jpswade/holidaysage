<?php

namespace App\Http\Controllers;

use App\Enums\SavedHolidaySearchRunType;
use App\Enums\SavedHolidaySearchStatus;
use App\Http\Requests\ImportSearchUrlRequest;
use App\Http\Requests\StoreSavedHolidaySearchRequest;
use App\Http\Requests\UpdateSavedHolidaySearchRequest;
use App\Jobs\RefreshSavedHolidaySearchJob;
use App\Models\SavedHolidaySearch;
use App\Models\ScoredHolidayOption;
use App\Services\Imports\ImportUrlParserRegistry;
use App\Services\Providers\ProviderSourceResolver;
use App\Support\SavedHolidaySearchDisplayName;
use App\Support\SearchFormPrefill;
use App\ViewModels\ResultCardViewModel;
use App\ViewModels\SearchSummaryViewModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(Request $request): View
    {
        return view('searches.create', [
            'prefill' => SearchFormPrefill::fromRequest($request),
        ]);
    }

    public function edit(Request $request, SavedHolidaySearch $search): View
    {
        return view('searches.edit', [
            'search' => $search,
            'prefill' => SearchFormPrefill::fromRequest($request),
        ]);
    }

    public function update(UpdateSavedHolidaySearchRequest $request, SavedHolidaySearch $search): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['name'] !== $search->name) {
            $search->slug = $this->uniqueSlug($validated['name'], $search->id);
        }

        $search->fill([
            'name' => $validated['name'],
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
            'board_preferences' => $validated['board_preferences'] ?? $search->board_preferences,
            'destination_preferences' => $validated['destination_preferences'] ?? $search->destination_preferences,
            'provider_destination_ids' => $validated['provider_destination_ids'] ?? $search->provider_destination_ids,
            'provider_occupancy' => $validated['provider_occupancy'] ?? $search->provider_occupancy,
            'provider_url_params' => $validated['provider_url_params'] ?? $search->provider_url_params,
            'feature_preferences' => $validated['feature_preferences'] ?? [],
            'status' => $validated['status'] ?? $search->status->value,
        ]);
        $search->save();

        return redirect()
            ->route('holidays.index', $this->holidaysIndexQueryForSearch($search))
            ->with('status', 'Search updated. Run a refresh when you are ready to fetch new provider results.');
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
            'provider_destination_ids' => $validated['provider_destination_ids'] ?? null,
            'provider_occupancy' => $validated['provider_occupancy'] ?? null,
            'provider_url_params' => $validated['provider_url_params'] ?? null,
            'feature_preferences' => $validated['feature_preferences'] ?? null,
            'status' => $validated['status'] ?? SavedHolidaySearchStatus::Active->value,
        ]);

        return redirect()
            ->route('holidays.index', $this->holidaysIndexQueryForSearch($search))
            ->with('status', 'Search created. We can start tracking results now.');
    }

    public function show(Request $request, SavedHolidaySearch $search): RedirectResponse
    {
        $params = $this->holidaysIndexQueryForSearch($search, $request);

        return redirect()->route('holidays.index', $params);
    }

    public function deal(SavedHolidaySearch $search, ScoredHolidayOption $scoredOption): View
    {
        abort_unless($scoredOption->saved_holiday_search_id === $search->id, 404);

        $scoredOption->load(['holidayPackage.hotel.photos', 'holidayPackage.providerSource']);
        $card = ResultCardViewModel::fromModel($scoredOption);
        $package = $scoredOption->holidayPackage;
        $provider = $package?->providerSource;
        $providerUrl = $this->absoluteProviderUrl($package?->provider_url, $provider?->base_url);

        return view('searches.deal', [
            'search' => $search,
            'summary' => SearchSummaryViewModel::fromModel($search),
            'card' => $card,
            'providerUrl' => $providerUrl,
        ]);
    }

    public function results(SavedHolidaySearch $search): RedirectResponse
    {
        return redirect()->route('holidays.index', $this->holidaysIndexQueryForSearch($search));
    }

    public function import(
        ImportSearchUrlRequest $request,
        ImportUrlParserRegistry $parsers,
        ProviderSourceResolver $providerResolver,
    ): JsonResponse {
        $url = (string) $request->validated('url');

        try {
            $parser = $parsers->parserFor($url);
            $criteria = $parser->parse($url);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => 'This provider URL is not supported yet.',
            ], 422);
        }

        $provider = $providerResolver->forUrl($url);

        return response()->json([
            'message' => 'Import criteria extracted.',
            'criteria' => $criteria,
            'suggested_name' => SavedHolidaySearchDisplayName::fromExtracted($criteria, $provider),
        ]);
    }

    public function refresh(SavedHolidaySearch $search): RedirectResponse
    {
        RefreshSavedHolidaySearchJob::dispatch($search->id, SavedHolidaySearchRunType::Manual->value);

        return redirect()
            ->route('holidays.index', $this->holidaysIndexQueryForSearch($search))
            ->with('status', 'Refresh queued. Results will update shortly.');
    }

    private function uniqueSlug(string $name, ?int $ignoreSearchId = null): string
    {
        $base = Str::slug($name) ?: 'saved-search';
        $slug = $base;
        $count = 1;
        while (true) {
            $query = SavedHolidaySearch::query()->where('slug', $slug);
            if ($ignoreSearchId !== null) {
                $query->where('id', '!=', $ignoreSearchId);
            }
            if (! $query->exists()) {
                return $slug;
            }
            $slug = $base.'-'.$count++;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function holidaysIndexQueryForSearch(SavedHolidaySearch $search, ?Request $request = null): array
    {
        $q = array_merge(
            [
                'search_id' => $search->id,
            ],
            $this->holidaysBrowseParamBag($request)
        );

        // Drop defaults so the URL is tidy.
        if (isset($q['sort']) && (string) $q['sort'] === 'rank') {
            unset($q['sort']);
        }

        return $q;
    }

    /**
     * @return array<string, mixed>
     */
    private function holidaysBrowseParamBag(?Request $request): array
    {
        if (! $request) {
            return [];
        }

        $q = [];
        if ($request->integer('page') > 1) {
            $q['page'] = (string) $request->integer('page');
        }
        if ($request->filled('q') && is_string($request->query('q')) && trim($request->query('q') ?? '') !== '') {
            $q['q'] = (string) $request->query('q');
        }
        $sort = (string) $request->query('sort', 'rank');
        if ($sort !== 'rank' && in_array($sort, ['price_low', 'price_high', 'score'], true)) {
            $q['sort'] = $sort;
        }
        if ($request->boolean('qualified')) {
            $q['qualified'] = 1;
        }

        return $q;
    }

    private function absoluteProviderUrl(?string $providerUrl, ?string $baseUrl): ?string
    {
        if (! is_string($providerUrl) || $providerUrl === '') {
            return null;
        }

        if (str_starts_with($providerUrl, 'http://') || str_starts_with($providerUrl, 'https://')) {
            return $providerUrl;
        }

        if (! is_string($baseUrl) || $baseUrl === '') {
            return $providerUrl;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($providerUrl, '/');
    }
}
