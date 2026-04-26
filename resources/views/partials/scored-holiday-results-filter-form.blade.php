@props([
    'action',
    'resultsQuery' => '',
    'resultsSort' => 'rank',
    'resultsQualifiedOnly' => false,
])

<form method="get" action="{{ $action }}" class="mb-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:flex-row md:flex-wrap md:items-end">
    <div class="min-w-0 flex-1 md:max-w-xs">
        <label for="results-q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search results</label>
        <div class="relative mt-1">
            <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input id="results-q" type="search" name="q" value="{{ $resultsQuery }}" placeholder="Hotel, resort, destination…" class="w-full rounded-lg border-slate-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" autocomplete="off" />
        </div>
    </div>
    <div>
        <label for="results-sort" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Sort by</label>
        <select id="results-sort" name="sort" class="mt-1 w-full min-w-[12rem] rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 md:w-auto">
            <option value="rank" @selected($resultsSort === 'rank')>Match rank (default)</option>
            <option value="score" @selected($resultsSort === 'score')>Overall score</option>
            <option value="price_low" @selected($resultsSort === 'price_low')>Price: low to high</option>
            <option value="price_high" @selected($resultsSort === 'price_high')>Price: high to low</option>
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
        <a href="{{ $action }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
    </div>
</form>
