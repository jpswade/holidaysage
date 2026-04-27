<x-layouts.app-shell :title="$card->hotelName . ' Deal - HolidaySage'">
    <section>
        <a href="{{ route('holidays.index', ['search_id' => $search->id]) }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
            <x-lucide-arrow-left class="h-4 w-4" />
            Back to holidays
        </a>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $search->name }}</h1>
            <div class="mt-4">
                @include('searches.partials.search-summary-bar', ['summary' => $summary, 'detailed' => true])
            </div>
        </div>
    </section>

    <section class="mt-6">
        @include('searches.partials.recommendation-card', ['card' => $card, 'search' => null, 'elevated' => true])
        @if ($providerUrl)
            <div class="mt-4 rounded-xl border border-teal-200 bg-teal-50/50 p-4">
                <p class="text-sm text-slate-700">Ready to book? Continue on the provider site.</p>
                <a href="{{ $providerUrl }}" target="_blank" rel="noreferrer noopener" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">
                    Open Provider Deal
                    <x-lucide-external-link class="h-4 w-4" />
                </a>
            </div>
        @endif
    </section>
</x-layouts.app-shell>
