@php
    $holidaysTotal = (int) ($holidaysTotal ?? 0);
@endphp

<x-layouts.app-shell title="Browse holidays - HolidaySage">
    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">Browse holidays</h1>
                @if ($results->total() > 0)
                    <p class="mt-1 text-slate-600">
                        @if ($results->hasPages())
                            Showing {{ number_format($results->firstItem()) }}–{{ number_format($results->lastItem()) }} of {{ number_format($results->total()) }}
                        @else
                            {{ number_format($results->total()) }} {{ $results->total() === 1 ? 'option' : 'options' }}
                        @endif
                    </p>
                @endif
            </div>
        </div>

        @include('partials.scored-holiday-results-filter-form', [
            'action' => route('holidays.index'),
            'resultsQuery' => $resultsQuery,
            'resultsSort' => $resultsSort,
            'resultsQualifiedOnly' => $resultsQualifiedOnly,
        ])

        @if ($holidaysTotal > 0)
            <p class="mb-6 text-xs text-slate-500">Prices can change. Confirm availability and final pricing on the provider's website.</p>
        @endif
    </section>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 md:gap-5 lg:gap-6">
        @forelse ($results as $holiday)
            @include('holidays.partials.browse-holiday-card', ['holiday' => $holiday])
        @empty
            <div class="md:col-span-2">
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    @if ($holidaysTotal === 0)
                        <p class="text-base font-medium text-slate-900">No results yet</p>
                        <p class="mt-1 text-sm text-slate-600">Create a saved search and refresh it from your search page to see holidays here.</p>
                        <a href="{{ route('searches.create') }}" class="mt-4 inline-block text-sm font-semibold text-teal-700 hover:text-teal-800">Create a search</a>
                    @else
                        <p class="text-base font-medium text-slate-800">No options match your filters.</p>
                        <p class="mt-2 text-sm text-slate-600">Try a different keyword, sort order, or <a href="{{ route('holidays.index') }}" class="font-semibold text-teal-700 hover:text-teal-800">reset filters</a>.</p>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    @if ($results->total() > 0 && $results->hasPages())
        <div class="mt-8 border-t border-slate-200 pt-6">
            {{ $results->links() }}
        </div>
    @endif
</x-layouts.app-shell>
