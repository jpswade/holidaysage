@php
    $hasActiveFilters = ($filterQuery ?? '') !== '' || ($filterProvider ?? 'all') !== 'all' || ($filterBoard ?? 'all') !== 'all';
    $holidaysTotal = (int) ($holidaysTotal ?? 0);
@endphp

<x-layouts.app-shell title="Browse holidays - HolidaySage" contentMax="max-w-7xl">
    <h1 class="sr-only">Browse holidays</h1>

    <form method="get" action="{{ route('holidays.index') }}" class="mb-6 space-y-3" id="browse-filters" aria-label="Filter holidays">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <details
                    class="group"
                    @if (($filterProvider ?? 'all') !== 'all' || ($filterBoard ?? 'all') !== 'all') open @endif
                >
                    <summary
                        class="list-none cursor-pointer select-none rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 [&::-webkit-details-marker]:hidden"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            Filters
                            <x-lucide-chevron-down class="h-3.5 w-3.5 text-slate-500 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="mt-2 flex min-w-0 max-w-md flex-col gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:max-w-none sm:flex-row">
                        <div class="min-w-0 flex-1">
                            <label for="filter-provider" class="text-xs font-medium text-slate-500">Provider</label>
                            <select
                                id="filter-provider"
                                name="provider"
                                onchange="this.form.submit()"
                                class="mt-1 w-full min-w-0 rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                            >
                                <option value="all" @selected(($filterProvider ?? 'all') === 'all')>All providers</option>
                                <option value="jet2" @selected(($filterProvider ?? '') === 'jet2')>Jet2</option>
                                <option value="tui" @selected(($filterProvider ?? '') === 'tui')>TUI</option>
                            </select>
                        </div>
                        <div class="min-w-0 flex-1">
                            <label for="filter-board" class="text-xs font-medium text-slate-500">Board</label>
                            <select
                                id="filter-board"
                                name="board"
                                onchange="this.form.submit()"
                                class="mt-1 w-full min-w-0 rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                            >
                                <option value="all" @selected(($filterBoard ?? 'all') === 'all')>All boards</option>
                                <option value="all_inclusive" @selected(($filterBoard ?? '') === 'all_inclusive')>All inclusive</option>
                                <option value="half_board" @selected(($filterBoard ?? '') === 'half_board')>Half board</option>
                                <option value="bed_breakfast" @selected(($filterBoard ?? '') === 'bed_breakfast')>Bed &amp; breakfast</option>
                                <option value="self_catering" @selected(($filterBoard ?? '') === 'self_catering')>Self catering</option>
                            </select>
                        </div>
                    </div>
                </details>

                <details
                    class="group"
                    @if (($filterQuery ?? '') !== '') open @endif
                >
                    <summary
                        class="list-none cursor-pointer select-none rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 [&::-webkit-details-marker]:hidden"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            More filters
                            <x-lucide-chevron-down class="h-3.5 w-3.5 text-slate-500 transition group-open:rotate-180" />
                        </span>
                    </summary>
                    <div class="mt-2 w-full min-w-0 max-w-md rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:max-w-sm">
                        <label for="filter-q" class="text-xs font-medium text-slate-500">Search by hotel or place</label>
                        <div class="mt-1 flex gap-2">
                            <input
                                id="filter-q"
                                type="search"
                                name="q"
                                value="{{ $filterQuery ?? '' }}"
                                class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                placeholder="Hotel or destination"
                            />
                            <button
                                type="submit"
                                class="shrink-0 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800"
                            >
                                Go
                            </button>
                        </div>
                    </div>
                </details>
            </div>
            <p class="shrink-0 text-sm text-slate-600">Showing <span class="font-medium text-slate-900">{{ $holidaysShown }} of {{ $holidaysTotal }} holidays</span></p>
        </div>
        @if ($hasActiveFilters)
            <a href="{{ route('holidays.index') }}" class="text-xs font-medium text-teal-700 hover:text-teal-900">Clear filters</a>
        @endif
    </form>

    @if ($holidaysTotal > 0)
        <p class="mb-6 text-xs text-slate-500">Deals and prices reflect your saved-search imports. Availability and pricing on the provider site is authoritative.</p>
    @endif

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 md:gap-5 lg:gap-6">
        @forelse ($holidays as $holiday)
            @include('holidays.partials.browse-holiday-card', ['holiday' => $holiday])
        @empty
            <div class="md:col-span-2">
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    @if ($holidaysTotal === 0)
                        <p class="text-base font-medium text-slate-900">No results yet</p>
                        <p class="mt-1 text-sm text-slate-600">Create a saved search, run an import, and scored holidays will appear here.</p>
                        <a href="{{ route('searches.create') }}" class="mt-4 inline-block text-sm font-semibold text-teal-700 hover:text-teal-800">Create a search</a>
                    @else
                        <p class="text-base font-medium text-slate-900">No results</p>
                        <p class="mt-1 text-sm text-slate-600">Nothing matched those filters. Try widening your search or clear filters.</p>
                        <a href="{{ route('holidays.index') }}" class="mt-4 inline-block text-sm font-semibold text-teal-700 hover:text-teal-800">Clear filters</a>
                    @endif
                </div>
            </div>
        @endforelse
    </div>
</x-layouts.app-shell>
