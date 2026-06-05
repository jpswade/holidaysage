@props([
    'action',
    'resetUrl' => null,
    'searchId' => null,
    'keywordLabel' => 'Filter by keyword',
    'resultsQuery' => '',
    'resultsSort' => 'rank',
    'resultsQualifiedOnly' => false,
    'resultsProviders' => [],
    'resultsBoards' => [],
    'resultsMaxTransfer' => null,
    'availableProviders' => [],
])
@php
    use App\Support\ScoredHolidayResultsFilter;

    $resetUrl = is_string($resetUrl) && $resetUrl !== '' ? $resetUrl : $action;
    $searchId = is_int($searchId) || (is_string($searchId) && is_numeric($searchId)) ? (int) $searchId : null;
    $resultsProviders = array_values(array_filter(array_map(static fn ($v) => is_scalar($v) ? strtolower((string) $v) : null, (array) $resultsProviders)));
    $resultsBoards = array_values(array_filter(array_map(static fn ($v) => is_scalar($v) ? strtolower((string) $v) : null, (array) $resultsBoards)));
    $availableProviders = is_array($availableProviders) ? $availableProviders : [];
    $sortLabels = ScoredHolidayResultsFilter::sortLabels();
    $boardLabels = ScoredHolidayResultsFilter::boardBucketLabels();
    $hasAdvancedFilter = $resultsProviders !== [] || $resultsBoards !== [] || $resultsMaxTransfer !== null;
@endphp

<form
    method="get"
    action="{{ $action }}"
    class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
    x-data="{ advancedOpen: @js($hasAdvancedFilter) }"
>
    @if ($searchId !== null && $searchId > 0)
        <input type="hidden" name="search_id" value="{{ $searchId }}" />
    @endif

    <div class="flex flex-col gap-4 md:flex-row md:flex-wrap md:items-end">
        <div class="min-w-0 flex-1 md:max-w-xs">
            <label for="results-q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $keywordLabel }}</label>
            <div class="relative mt-1">
                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input id="results-q" type="search" name="q" value="{{ $resultsQuery }}" placeholder="Hotel, resort, destination…" class="w-full rounded-lg border-slate-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" autocomplete="off" />
            </div>
        </div>
        <div>
            <label for="results-sort" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Sort by</label>
            <select id="results-sort" name="sort" class="mt-1 w-full min-w-[12rem] rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 md:w-auto">
                @foreach ($sortLabels as $sortValue => $sortLabel)
                    <option value="{{ $sortValue }}" @selected($resultsSort === $sortValue)>{{ $sortLabel }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm font-medium text-slate-700">
            <input type="checkbox" name="qualified" value="1" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked($resultsQualifiedOnly) />
            Hide disqualified
        </label>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">
                <x-lucide-filter class="h-4 w-4" />
                Apply
            </button>
            <a href="{{ $resetUrl }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
        <button
            type="button"
            class="ml-auto inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900"
            x-on:click="advancedOpen = !advancedOpen"
            x-bind:aria-expanded="advancedOpen.toString()"
            aria-controls="results-advanced-filters"
        >
            <x-lucide-sliders-horizontal class="h-4 w-4" />
            <span x-text="advancedOpen ? 'Hide filters' : 'More filters'"></span>
        </button>
    </div>

    <div
        id="results-advanced-filters"
        class="mt-4 grid gap-5 border-t border-slate-200 pt-4 md:grid-cols-3"
        x-show="advancedOpen"
        x-cloak
        x-transition.opacity
    >
        <fieldset>
            <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">Provider</legend>
            @if (count($availableProviders) === 0)
                <p class="mt-2 text-sm text-slate-500">No providers yet.</p>
            @else
                <div class="mt-2 space-y-1.5">
                    @foreach ($availableProviders as $providerKey => $providerName)
                        @php $providerKey = strtolower((string) $providerKey); @endphp
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="provider[]" value="{{ $providerKey }}" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked(in_array($providerKey, $resultsProviders, true)) />
                            <span>{{ $providerName }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </fieldset>

        <fieldset>
            <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">Board</legend>
            <div class="mt-2 space-y-1.5">
                @foreach ($boardLabels as $bucketKey => $bucketLabel)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="board[]" value="{{ $bucketKey }}" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked(in_array($bucketKey, $resultsBoards, true)) />
                        <span>{{ $bucketLabel }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div>
            <label for="results-max-transfer" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Max transfer</label>
            <div class="relative mt-2">
                <select id="results-max-transfer" name="max_transfer" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    <option value="">Any transfer time</option>
                    <option value="30" @selected((int) $resultsMaxTransfer === 30)>30 minutes or less</option>
                    <option value="60" @selected((int) $resultsMaxTransfer === 60)>1 hour or less</option>
                    <option value="90" @selected((int) $resultsMaxTransfer === 90)>1.5 hours or less</option>
                    <option value="120" @selected((int) $resultsMaxTransfer === 120)>2 hours or less</option>
                </select>
            </div>
            <p class="mt-2 text-xs text-slate-500">Useful when travelling with kids or arriving late.</p>
        </div>
    </div>
</form>
