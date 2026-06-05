@php
    /** @var \App\Models\SavedHolidaySearch $search */
    /** @var \App\ViewModels\SearchSummaryViewModel $summary */
    /** @var \App\Models\SavedHolidaySearchRun|null $latestRun */
    /** @var \App\Models\SavedHolidaySearchRun|null $previousRun */
    /** @var list<array{viewModel: \App\ViewModels\ResultCardViewModel, displayRank: int}> $latestCards */
    /** @var list<array{viewModel: \App\ViewModels\ResultCardViewModel, displayRank: int}> $newCards */
    /** @var list<array{viewModel: \App\ViewModels\ResultCardViewModel, displayRank: int, previousScore: float, deltaScore: float}> $improvedCards */
    $shareUrl = null;
    if ($search->sharing_enabled && is_string($search->share_token) && $search->share_token !== '') {
        $shareUrl = route('saved-searches.shared.show', ['token' => $search->share_token]);
    }
    $lastUpdated = $latestRun?->finished_at ?? $search->last_scored_at;
@endphp

<x-layouts.app-shell title="{{ $search->name }} - HolidaySage">
    <section class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs uppercase tracking-wide text-slate-500">Saved search</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">{{ $search->name }}</h1>
            @if ($lastUpdated)
                <p class="mt-1 text-sm text-slate-500">Last updated {{ $lastUpdated->diffForHumans() }}. We'll show the latest results after each refresh.</p>
            @else
                <p class="mt-1 text-sm text-slate-500">No completed runs yet. Trigger a refresh to populate results.</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('holidays.index', ['search_id' => $search->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">
                <x-lucide-search class="h-4 w-4" />
                Browse all results
            </a>
            <a href="{{ route('searches.edit', $search) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <x-lucide-pencil class="h-4 w-4" />
                Edit criteria
            </a>
            <form method="post" action="{{ route('searches.refresh', $search) }}" class="inline-block">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <x-lucide-refresh-cw class="h-4 w-4" />
                    Refresh now
                </button>
            </form>
        </div>
    </section>

    <section class="mb-6 grid gap-6 md:grid-cols-3">
        <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Search criteria</h2>
            <ul class="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
                @foreach ($summary->primaryBullets as $bullet)
                    <li class="inline-flex items-start gap-2">
                        <x-lucide-circle-dot class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-teal-600" />
                        <span>{{ $bullet }}</span>
                    </li>
                @endforeach
                @foreach ($summary->constraintBullets as $bullet)
                    <li class="inline-flex items-start gap-2">
                        <x-lucide-circle-dot class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-slate-400" />
                        <span>{{ $bullet }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($summary->featureChips !== [])
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($summary->featureChips as $chip)
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ $chip['emoji'] }} {{ $chip['label'] }}</span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Share with friends and family</h2>
            <p class="mt-1 text-sm text-slate-600">A read-only link they can open without an account.</p>
            <form method="post" action="{{ route('saved-searches.sharing.update', $search) }}" class="mt-3 flex flex-wrap gap-2">
                @csrf
                @method('PATCH')
                @if ($search->sharing_enabled)
                    <button type="submit" name="action" value="rotate" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        <x-lucide-refresh-cw class="h-3.5 w-3.5" />
                        New link
                    </button>
                    <button type="submit" name="action" value="disable" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Stop sharing
                    </button>
                @else
                    <button type="submit" name="action" value="enable" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700">
                        <x-lucide-link class="h-3.5 w-3.5" />
                        Enable sharing
                    </button>
                @endif
            </form>
            @if ($shareUrl)
                <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs">
                    <input type="text" value="{{ $shareUrl }}" readonly class="w-full border-0 bg-transparent p-0 font-mono text-xs text-slate-700 focus:outline-none" onclick="this.select()" />
                </div>
            @endif
        </div>
    </section>

    <section class="mb-8">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Current best matches</h2>
            <a href="{{ route('holidays.index', ['search_id' => $search->id]) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">See all results →</a>
        </div>
        @if (empty($latestCards))
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-600">
                Nothing to show yet. Refreshing this saved search will fetch new results.
            </div>
        @else
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                @foreach ($latestCards as $row)
                    @include('holidays.partials.browse-holiday-card', ['holiday' => $row])
                @endforeach
            </div>
        @endif
    </section>

    @if (! empty($newCards))
        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">New matches since the previous refresh</h2>
            <p class="mt-1 text-sm text-slate-600">Options that weren't in the previous run.</p>
            <div class="mt-3 grid grid-cols-1 gap-5 md:grid-cols-2">
                @foreach ($newCards as $row)
                    @include('holidays.partials.browse-holiday-card', ['holiday' => $row])
                @endforeach
            </div>
        </section>
    @endif

    @if (! empty($improvedCards))
        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">Improved matches</h2>
            <p class="mt-1 text-sm text-slate-600">Properties whose overall score has gone up since the previous refresh.</p>
            <div class="mt-3 grid grid-cols-1 gap-5 md:grid-cols-2">
                @foreach ($improvedCards as $row)
                    <div class="relative">
                        @include('holidays.partials.browse-holiday-card', ['holiday' => $row])
                        <p class="mt-2 inline-flex items-center gap-1 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-800">
                            <x-lucide-trending-up class="h-3.5 w-3.5" />
                            +{{ number_format($row['deltaScore'], 1) }} since last refresh (was {{ number_format($row['previousScore'], 1) }})
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($previousRun === null && $latestRun !== null && empty($newCards) && empty($improvedCards))
        <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
            New and improved matches will appear here after the next refresh, once we have two runs to compare.
        </section>
    @endif
</x-layouts.app-shell>
