@php
    use App\Services\HomepageHighlightQuery;

    /** @var array<string, list<array{viewModel: \App\ViewModels\ResultCardViewModel, displayRank: int}>> $rails */
    $rails = $rails ?? [];

    $railSections = [
        HomepageHighlightQuery::FAMILY => [
            'title' => 'Best family holidays',
            'lede' => 'Properties with kids clubs, family rooms, and a strong family-fit score.',
        ],
        HomepageHighlightQuery::SHORT_TRANSFER => [
            'title' => 'Short transfer escapes',
            'lede' => 'Less than an hour from the runway to your room.',
        ],
        HomepageHighlightQuery::BEST_VALUE => [
            'title' => 'Best value this week',
            'lede' => 'Strong scores at a price that holds up against similar holidays.',
        ],
        HomepageHighlightQuery::ALL_INCLUSIVE => [
            'title' => 'All-inclusive picks',
            'lede' => 'Food, drinks, and entertainment built in.',
        ],
    ];
@endphp

<x-layouts.app-shell title="HolidaySage - Better holiday discovery">
    <section class="mx-auto max-w-5xl py-10 text-center md:py-14">
        <div class="inline-flex items-center gap-2 rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">
            <x-lucide-compass class="h-3.5 w-3.5" />
            Calm, curated holiday discovery
        </div>
        <h1 class="mt-6 text-4xl font-bold tracking-tight text-slate-900 md:text-6xl">
            Find better holidays without comparing the same sites for hours
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-600">
            HolidaySage ranks package holidays from real providers like Jet2 and TUI and explains why each one might suit you — so you can shortlist a few worth considering rather than wade through hundreds.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('holidays.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">
                Browse holidays
                <x-lucide-arrow-right class="h-4 w-4" />
            </a>
            <a href="{{ route('searches.create') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">
                Create a saved search
            </a>
        </div>

        <form method="get" action="{{ route('holidays.index') }}" class="mx-auto mt-10 grid max-w-3xl grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm md:grid-cols-[1fr_auto] md:items-end">
            <div>
                <label for="home-q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Where do you want to look?</label>
                <div class="relative mt-1">
                    <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input id="home-q" type="search" name="q" placeholder="Try a destination, resort, or hotel name" class="w-full rounded-lg border-slate-300 py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" autocomplete="off" />
                </div>
            </div>
            <button type="submit" class="inline-flex h-[42px] items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                <x-lucide-search class="h-4 w-4" />
                Search
            </button>
            <p class="md:col-span-2 text-xs text-slate-500">No accounts needed to browse. Save a search if you want HolidaySage to keep an eye on it for you.</p>
        </form>
    </section>

    @foreach ($railSections as $railKey => $meta)
        @php $rail = $rails[$railKey] ?? []; @endphp
        <section class="mb-10 md:mb-12">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $meta['title'] }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $meta['lede'] }}</p>
                </div>
                <a href="{{ route('holidays.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">See more in browse →</a>
            </div>

            @if (empty($rail))
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-8 text-center text-sm text-slate-600">
                    Nothing to highlight here yet. Try creating a saved search and refreshing it to add real options.
                </div>
            @else
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-{{ min(4, count($rail)) }}">
                    @foreach ($rail as $row)
                        @include('holidays.partials.browse-holiday-card', ['holiday' => $row])
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    <section class="relative left-1/2 mb-10 w-screen -translate-x-1/2 border-y border-slate-200 bg-white py-12 md:py-14">
        <div class="mx-auto max-w-5xl px-6 md:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900">How HolidaySage ranks holidays</h2>
            <p class="mt-2 text-base text-slate-600">Every option is scored across five plain-English dimensions so you can see, at a glance, why one holiday beats another.</p>
            <ul class="mt-6 grid gap-5 md:grid-cols-5">
                @foreach ([
                    ['icon' => 'plane', 'label' => 'Travel', 'note' => 'Flight length, transfer time, and overall hassle.'],
                    ['icon' => 'badge-pound-sterling', 'label' => 'Value', 'note' => 'How the price compares with similar properties.'],
                    ['icon' => 'baby', 'label' => 'Family fit', 'note' => 'Facilities and amenities that matter to families.'],
                    ['icon' => 'map-pin', 'label' => 'Location', 'note' => 'Proximity to beach, resort, and points of interest.'],
                    ['icon' => 'star', 'label' => 'Reviews', 'note' => 'What previous guests actually thought.'],
                ] as $dim)
                    <li class="rounded-2xl border border-slate-200 bg-[#fffdfa] p-4">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-700">
                            <x-dynamic-component :component="'lucide-'.$dim['icon']" class="h-4 w-4" />
                        </span>
                        <p class="mt-3 text-base font-semibold text-slate-900">{{ $dim['label'] }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $dim['note'] }}</p>
                    </li>
                @endforeach
            </ul>
            <p class="mt-6 text-sm text-slate-600">Each holiday card shows the score, the reasons behind it, and any trade-offs to weigh up — no hidden ranking and no fake urgency.</p>
        </div>
    </section>

    <section class="mb-10 text-center">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900">Decide with confidence, not tabs</h2>
        <p class="mt-2 text-base text-slate-600">Start with browse, or save a search and pick up where you left off.</p>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('holidays.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">
                Browse holidays
                <x-lucide-arrow-right class="h-4 w-4" />
            </a>
            <a href="{{ route('searches.create') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">
                Create a saved search
            </a>
            <a href="{{ route('searches.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                Saved searches
                <x-lucide-chevron-right class="h-4 w-4" />
            </a>
        </div>
    </section>
</x-layouts.app-shell>
