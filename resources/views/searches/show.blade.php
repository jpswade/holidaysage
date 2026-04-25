<x-layouts.app-shell :title="$search->name . ' - HolidaySage'">
    <section>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $search->name }}</h1>
                <div class="mt-2 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold uppercase text-emerald-700">
                    {{ $search->status->value }}
                </div>
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('searches.refresh', $search) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <x-lucide-icon name="refresh-cw" class="h-4 w-4" />
                        Refresh
                    </button>
                </form>
                <a href="{{ route('searches.results', $search) }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">
                    View Full Results
                </a>
            </div>
        </div>

        <div class="mt-5">
            @include('searches.partials.search-summary-bar', ['summary' => $summary])
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-2 text-sm text-slate-600">
            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1">
                <x-lucide-icon name="clock-3" class="h-4 w-4 text-slate-500" />
                Updated {{ optional($latestRun?->finished_at ?? $search->last_scored_at ?? $search->updated_at)->diffForHumans() }}
            </span>
            @if ($results instanceof \Illuminate\Support\Collection && $results->where('rank', '<=', 3)->count() > 0)
                <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2.5 py-1 text-teal-700">
                    <x-lucide-icon name="trending-up" class="h-4 w-4" />
                    {{ $results->where('rank', '<=', 3)->count() }} strong matches found
                </span>
            @endif
        </div>
    </section>

    @if ($topPick)
        <section class="mt-8">
            <h2 class="text-xl font-semibold text-slate-900">Top pick</h2>
            <div class="mt-3">
                @include('searches.partials.recommendation-card', ['card' => $topPick->card, 'elevated' => true])
            </div>
        </section>
    @endif

    <section class="mt-8">
        <h2 class="text-xl font-semibold text-slate-900">Top recommended options</h2>
        <p class="mt-1 text-sm text-slate-600">Ranked by match score based on your preferences.</p>
        @if ($results instanceof \Illuminate\Support\Collection && $results->isNotEmpty())
            <div class="mt-4 space-y-4">
                @foreach ($results as $card)
                    @include('searches.partials.recommendation-card', ['card' => $card, 'elevated' => false])
                @endforeach
            </div>
        @else
            <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                Tracking is in progress. Your ranked options will appear here after the first refresh cycle completes.
            </div>
        @endif
    </section>

    @if ($search->runs->isNotEmpty())
        <section class="mt-8">
            <h2 class="text-lg font-semibold text-slate-900">Recent run activity</h2>
            <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <ul class="space-y-2 text-sm text-slate-700">
                    @foreach ($search->runs as $run)
                        <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                            <span class="font-medium">Run #{{ $run->id }}</span>
                            <span class="text-slate-600">{{ $run->status->value }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layouts.app-shell>
