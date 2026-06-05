@php
    /** @var \App\Models\SavedHolidaySearch $search */
    /** @var \App\ViewModels\SearchSummaryViewModel $summary */
    /** @var \App\Models\SavedHolidaySearchRun|null $latestRun */
    /** @var list<array{viewModel: \App\ViewModels\ResultCardViewModel, displayRank: int}> $latestCards */
    /** @var list<array{viewModel: \App\ViewModels\ResultCardViewModel, displayRank: int}> $newCards */
    /** @var list<array{viewModel: \App\ViewModels\ResultCardViewModel, displayRank: int, previousScore: float, deltaScore: float}> $improvedCards */
    $lastUpdated = $latestRun?->finished_at ?? $search->last_scored_at;
@endphp

<x-layouts.app-shell title="Shared saved search - HolidaySage">
    <section class="mb-6">
        <p class="text-xs uppercase tracking-wide text-slate-500">Shared saved search</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">{{ $search->name }}</h1>
        @if ($lastUpdated)
            <p class="mt-1 text-sm text-slate-500">Showing results from {{ $lastUpdated->diffForHumans() }}.</p>
        @endif
    </section>

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
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
    </section>

    <section class="mb-8">
        <h2 class="text-lg font-semibold text-slate-900">Current best matches</h2>
        @if (empty($latestCards))
            <p class="mt-2 text-sm text-slate-600">No results yet for this saved search.</p>
        @else
            <div class="mt-3 grid grid-cols-1 gap-5 md:grid-cols-2">
                @foreach ($latestCards as $row)
                    @include('holidays.partials.browse-holiday-card', ['holiday' => $row])
                @endforeach
            </div>
        @endif
    </section>

    @if (! empty($newCards))
        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">New matches</h2>
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
            <div class="mt-3 grid grid-cols-1 gap-5 md:grid-cols-2">
                @foreach ($improvedCards as $row)
                    @include('holidays.partials.browse-holiday-card', ['holiday' => $row])
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app-shell>
