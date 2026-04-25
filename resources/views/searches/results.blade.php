<x-layouts.holidaysage :title="$search->name . ' Results - HolidaySage'">
    <section>
        <a href="{{ route('searches.show', $search) }}" class="inline-flex items-center text-sm font-medium text-teal-700 hover:text-teal-800">← Back to saved search</a>
        <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $search->name }}</h1>
                <p class="mt-2 text-slate-600">Full ranked list of current holiday options.</p>
            </div>
        </div>

        <div class="mt-5">
            @include('searches.partials.search-summary-bar', ['summary' => $summary])
        </div>

        <div class="mt-4 text-sm text-slate-600">
            Updated {{ optional($latestRun?->finished_at ?? $search->last_scored_at ?? $search->updated_at)->diffForHumans() }}
        </div>
    </section>

    <section class="mt-8">
        @if ($results instanceof \Illuminate\Pagination\LengthAwarePaginator && $results->count() > 0)
            <div class="space-y-4">
                @foreach ($results as $card)
                    @include('searches.partials.recommendation-card', ['card' => $card, 'elevated' => false])
                @endforeach
            </div>
            <div class="mt-6">
                {{ $results->links() }}
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                No ranked results available yet. Try refreshing this search shortly.
            </div>
        @endif
    </section>
</x-layouts.holidaysage>
