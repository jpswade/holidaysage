<x-layouts.app-shell :title="$search->name . ' - HolidaySage'">
    <section>
        <a href="{{ route('searches.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
            <x-lucide-arrow-left class="h-4 w-4" />
            Back to saved searches
        </a>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $search->name }}</h1>
                <div class="mt-2 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold uppercase text-emerald-700">
                    {{ $search->status->value }}
                </div>
            </div>
            <div class="flex items-center gap-2">
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
            @include('searches.partials.search-summary-bar', ['summary' => $summary])
        </div>
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-3xl font-semibold tracking-tight text-slate-900">Top {{ max(5, $results->count()) }} recommended options</h2>
                <p class="mt-1 text-lg text-slate-600">Ranked by match score based on your preferences</p>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1">
                <x-lucide-clock-3 class="h-4 w-4 text-slate-500" />
                Updated {{ optional($latestRun?->finished_at ?? $search->last_scored_at ?? $search->updated_at)->diffForHumans() }}
            </span>
            @if ($results instanceof \Illuminate\Support\Collection && $results->where('rank', '<=', 3)->count() > 0)
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
        @if ($results instanceof \Illuminate\Support\Collection && $results->isNotEmpty())
            <div class="space-y-4">
                @foreach ($results as $card)
                    @include('searches.partials.recommendation-card', ['card' => $card, 'elevated' => false])
                @endforeach
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                Tracking is in progress. Your ranked options will appear here after the first refresh cycle completes.
            </div>
        @endif
    </section>
</x-layouts.app-shell>
