<?php

namespace App\Http\Controllers;

use App\Enums\SavedHolidaySearchRunType;
use App\Enums\SavedHolidaySearchStatus;
use App\Http\Requests\ImportSearchUrlRequest;
use App\Http\Requests\StoreSavedHolidaySearchRequest;
use App\Http\Requests\UpdateSavedHolidaySearchRequest;
use App\Jobs\RefreshSavedHolidaySearchJob;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Models\ScoredHolidayOption;
use App\Services\Imports\ImportUrlParserRegistry;
use App\Services\Providers\ProviderSourceResolver;
use App\Support\SavedHolidaySearchDisplayName;
use App\ViewModels\ResultCardViewModel;
use App\ViewModels\SearchSummaryViewModel;
use App\ViewModels\TopPickViewModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
            'prefill' => $this->sanitisedSearchPrefill($request),
        ]);
    }

    public function edit(SavedHolidaySearch $search): View
    {
        return view('searches.edit', [
            'search' => $search,
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
            'feature_preferences' => $validated['feature_preferences'] ?? [],
            'status' => $validated['status'] ?? $search->status->value,
        ]);
        $search->save();

        return redirect()
            ->route('searches.show', $search)
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
            'feature_preferences' => $validated['feature_preferences'] ?? null,
            'status' => $validated['status'] ?? SavedHolidaySearchStatus::Active->value,
        ]);

        return redirect()
            ->route('searches.show', $search)
            ->with('status', 'Search created. We can start tracking results now.');
    }

    public function show(Request $request, SavedHolidaySearch $search): View
    {
        $search->load(['runs' => fn ($q) => $q->orderByDesc('id')->limit(5)]);
        $latestRun = $search->runs->first() ?: SavedHolidaySearchRun::query()
            ->where('saved_holiday_search_id', $search->id)
            ->latest('id')
            ->first();

        $perPage = (int) config('holidaysage.search_results_per_page', 18);
        $resultsSort = $this->normaliseResultsSort((string) $request->query('sort', 'rank'));
        $resultsQuery = trim((string) $request->query('q', ''));
        $resultsQualifiedOnly = $request->boolean('qualified');

        if ($latestRun) {
            $listQuery = $latestRun->scoredOptions()
                ->with(['holidayPackage.hotel.photos', 'holidayPackage.providerSource']);
            $this->applyResultsListConstraints($listQuery, $request);
            $this->applyResultsSort($listQuery, $resultsSort);

            $results = $listQuery
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (ScoredHolidayOption $row): ResultCardViewModel => ResultCardViewModel::fromModel($row));

            $topPickQuery = $latestRun->scoredOptions()
                ->with(['holidayPackage.hotel.photos', 'holidayPackage.providerSource'])
                ->where('is_disqualified', false);
            $this->applyResultsListConstraints($topPickQuery, $request);
            $topPickModel = $topPickQuery
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position')
                ->orderByDesc('scored_holiday_options.overall_score')
                ->first();
        } else {
            $results = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
            $topPickModel = null;
        }

        $topPick = $topPickModel
            ? TopPickViewModel::fromResult(ResultCardViewModel::fromModel($topPickModel))
            : null;
        $summary = SearchSummaryViewModel::fromModel($search);

        return view('searches.show', [
            'search' => $search,
            'summary' => $summary,
            'latestRun' => $latestRun,
            'topPick' => $topPick,
            'results' => $results,
            'resultsSort' => $resultsSort,
            'resultsQuery' => $resultsQuery,
            'resultsQualifiedOnly' => $resultsQualifiedOnly,
        ]);
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
        return redirect()->route('searches.show', $search);
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
            ->route('searches.show', $search)
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

    private function normaliseResultsSort(string $raw): string
    {
        return match ($raw) {
            'price_low', 'price_high', 'score' => $raw,
            default => 'rank',
        };
    }

    private function applyResultsListConstraints(Builder|Relation $query, Request $request): void
    {
        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->whereHas('holidayPackage', function (Builder $packageQuery) use ($like): void {
                $packageQuery->whereHas('hotel', function (Builder $hotelQuery) use ($like): void {
                    $hotelQuery->where('hotel_name', 'like', $like)
                        ->orWhere('resort_name', 'like', $like)
                        ->orWhere('destination_name', 'like', $like);
                });
            });
        }

        if ($request->boolean('qualified')) {
            $query->where('is_disqualified', false);
        }
    }

    private function applyResultsSort(Builder|Relation $query, string $sort): void
    {
        $query->reorder();
        match ($sort) {
            'price_low' => $query
                ->leftJoin('holiday_packages as hp_sort', 'hp_sort.id', '=', 'scored_holiday_options.holiday_package_id')
                ->select('scored_holiday_options.*')
                ->orderByRaw('COALESCE(hp_sort.price_total, 999999999) asc')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            'price_high' => $query
                ->leftJoin('holiday_packages as hp_sort', 'hp_sort.id', '=', 'scored_holiday_options.holiday_package_id')
                ->select('scored_holiday_options.*')
                ->orderByRaw('COALESCE(hp_sort.price_total, 0) desc')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            'score' => $query
                ->orderByDesc('scored_holiday_options.overall_score')
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position'),
            default => $query
                ->orderByRaw('scored_holiday_options.rank_position IS NULL')
                ->orderBy('scored_holiday_options.rank_position')
                ->orderByDesc('scored_holiday_options.overall_score'),
        };
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

    /**
     * @return array<string, mixed>
     */
    private function sanitisedSearchPrefill(Request $request): array
    {
        $allowedFeatures = [
            'family_friendly',
            'near_beach',
            'walkable',
            'swimming_pool',
            'kids_club',
            'adults_only',
            'all_inclusive',
            'quiet_relaxing',
            'near_nightlife',
            'spa_wellness',
        ];

        $out = [];

        $code = $request->query('departure_airport_code');
        if (is_string($code) && $code !== '') {
            $normalised = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $code), 0, 8));
            if ($normalised !== '') {
                $out['departure_airport_code'] = $normalised;
            }
        }

        foreach (['travel_start_date', 'travel_end_date'] as $key) {
            $v = $request->query($key);
            if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1) {
                $out[$key] = $v;
            }
        }

        $flex = $request->query('travel_date_flexibility_days');
        if (is_numeric($flex)) {
            $n = (int) $flex;
            if ($n >= 0 && $n <= 14) {
                $out['travel_date_flexibility_days'] = $n;
            }
        }

        foreach (['duration_min_nights', 'duration_max_nights'] as $key) {
            $v = $request->query($key);
            if (! is_numeric($v)) {
                continue;
            }
            $n = (int) $v;
            if ($n >= 1 && $n <= 30) {
                $out[$key] = $n;
            }
        }

        if (isset($out['duration_min_nights'], $out['duration_max_nights']) && $out['duration_max_nights'] < $out['duration_min_nights']) {
            unset($out['duration_min_nights'], $out['duration_max_nights']);
        }

        foreach (['adults', 'children', 'infants'] as $key) {
            $v = $request->query($key);
            if (! is_numeric($v)) {
                continue;
            }
            $n = (int) $v;
            $min = $key === 'adults' ? 1 : 0;
            if ($n >= $min && $n <= 10) {
                $out[$key] = $n;
            }
        }

        $budget = $request->query('budget_total');
        if (is_numeric($budget)) {
            $b = (float) $budget;
            if ($b >= 0 && $b <= 1_000_000_000) {
                $out['budget_total'] = $b;
            }
        }

        foreach (['max_flight_minutes', 'max_transfer_minutes'] as $key) {
            $v = $request->query($key);
            if (! is_numeric($v)) {
                continue;
            }
            $n = (int) $v;
            if ($key === 'max_flight_minutes' && $n >= 30 && $n <= 1440) {
                $out[$key] = $n;
            }
            if ($key === 'max_transfer_minutes' && $n >= 0 && $n <= 600) {
                $out[$key] = $n;
            }
        }

        $features = $request->query('feature_preferences');
        if (is_string($features)) {
            $features = [$features];
        } elseif (! is_array($features)) {
            $features = [];
        }
        $filteredFeatures = [];
        foreach ($features as $f) {
            if (! is_string($f)) {
                continue;
            }
            if (in_array($f, $allowedFeatures, true)) {
                $filteredFeatures[] = $f;
            }
        }
        $filteredFeatures = array_values(array_unique($filteredFeatures));
        if ($filteredFeatures !== []) {
            $out['feature_preferences'] = $filteredFeatures;
        }

        $destinations = $request->query('destination_preferences');
        if (is_string($destinations)) {
            $destinations = [$destinations];
        } elseif (! is_array($destinations)) {
            $destinations = [];
        }
        $destOut = [];
        foreach (array_slice($destinations, 0, 10) as $d) {
            if (! is_string($d)) {
                continue;
            }
            $t = trim(substr($d, 0, 80));
            if ($t !== '') {
                $destOut[] = $t;
            }
        }
        $destOut = array_values(array_unique($destOut));
        if ($destOut !== []) {
            $out['destination_preferences'] = $destOut;
        }

        $importUrl = $request->query('provider_import_url');
        if (is_string($importUrl) && $importUrl !== '') {
            $len = strlen($importUrl);
            if ($len <= 2048 && filter_var($importUrl, FILTER_VALIDATE_URL)) {
                $out['provider_import_url'] = $importUrl;
            }
        }

        return $out;
    }
}
