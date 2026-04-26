<x-layouts.app-shell :title="$search->name . ' - HolidaySage'">
    <section>
        <a href="{{ route('searches.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
            <x-lucide-arrow-left class="h-4 w-4" />
            Back to saved searches
        </a>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-serif text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">{{ $search->name }}</h1>
                <div class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-800">
                    <x-lucide-sparkles class="h-3.5 w-3.5 text-teal-600" />
                    {{ ucfirst($search->status->value) }}
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <a href="{{ route('searches.edit', $search) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <x-lucide-sliders-horizontal class="h-4 w-4" />
                    Refine search
                </a>
                <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <x-lucide-bell class="h-4 w-4" />
                    Alerts
                </button>
                <form method="POST" action="{{ route('searches.refresh', $search) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <x-lucide-refresh-cw class="h-4 w-4" />
                        Refresh
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-5">
            @include('searches.partials.search-summary-bar', ['summary' => $summary, 'detailed' => true])
        </div>
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <div>
                @if ($results->total() > 0)
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900">Top {{ number_format($results->total()) }} recommended options</h2>
                    <p class="mt-2 max-w-2xl text-lg leading-relaxed text-slate-600">
                        We order these holidays by how well they fit what you told us—dates, party, budget, and the features you care about. Each card explains why it made your shortlist.
                        @if ($results->hasPages())
                            <span class="text-slate-500"> </span>
                            <span class="whitespace-nowrap text-slate-500">·</span>
                            <span class="text-slate-600">Showing {{ number_format($results->firstItem()) }}–{{ number_format($results->lastItem()) }} of {{ number_format($results->total()) }}</span>
                        @endif
                    </p>
                @else
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900">Recommended options</h2>
                    <p class="mt-1 text-lg text-slate-600">Ranked results will appear here after the first scoring run completes.</p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1">
                <x-lucide-clock-3 class="h-4 w-4 text-slate-500" />
                Updated {{ optional($latestRun?->finished_at ?? $search->last_scored_at ?? $search->updated_at)->diffForHumans() }}
            </span>
            @if ($results->onFirstPage() && $results->where('rank', '<=', 3)->count() > 0)
                <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2.5 py-1 text-teal-700">
                    <x-lucide-trending-up class="h-4 w-4" />
                    {{ $results->where('rank', '<=', 3)->count() }} improved since yesterday
                </span>
            @endif
            </div>
        </div>

    </section>

    <section class="mt-5 rounded-2xl border border-teal-200 bg-teal-50/40 p-4">
        <div class="flex items-start gap-3">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-100 text-teal-700">
                <x-lucide-sparkles class="h-5 w-5" />
            </span>
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Your results improve over time</h3>
                <p class="text-sm text-slate-600">We continuously track prices and availability. As deals change, your rankings update automatically. Check back regularly or enable alerts.</p>
            </div>
        </div>
    </section>

    <section class="mt-6">
        @if ($latestRun)
            <form method="get" action="{{ route('searches.show', $search) }}" class="mb-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:flex-row md:flex-wrap md:items-end">
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
                    <a href="{{ route('searches.show', $search) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </form>
        @endif

        @if ($results->total() > 0)
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($results as $card)
                    @include('searches.partials.recommendation-card', ['card' => $card, 'search' => $search, 'elevated' => false])
                @endforeach
            </div>
            @if ($results->hasPages())
                <div class="mt-8 border-t border-slate-200 pt-6">
                    {{ $results->links() }}
                </div>
            @endif
        @elseif ($latestRun)
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                @if ($resultsQuery !== '' || $resultsQualifiedOnly || $resultsSort !== 'rank')
                    <p class="text-base font-medium text-slate-800">No options match your filters.</p>
                    <p class="mt-2 text-sm">Try a different keyword, sort order, or <a href="{{ route('searches.show', $search) }}" class="font-semibold text-teal-700 hover:text-teal-800">reset filters</a>.</p>
                @else
                    <p>Tracking is in progress. Your ranked options will appear here after the first refresh cycle completes.</p>
                @endif
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                Tracking is in progress. Your ranked options will appear here after the first refresh cycle completes.
            </div>
        @endif
    </section>
</x-layouts.app-shell>
