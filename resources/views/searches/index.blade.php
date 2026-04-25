<x-layouts.holidaysage title="My Saved Searches - HolidaySage">
    <section>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">My Saved Searches</h1>
                <p class="mt-2 text-slate-600">Your holiday searches are tracked and ranked continuously.</p>
            </div>
            <a href="{{ route('searches.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Create New Search</a>
        </div>

        @if ($searches->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <p class="text-lg font-semibold text-slate-900">No saved searches yet</p>
                <p class="mt-2 text-sm text-slate-600">Create your first search in under 2 minutes.</p>
            </div>
        @else
            <div class="mt-8 grid gap-4 md:grid-cols-2">
                @foreach ($searches as $item)
                    @php($search = $item['search'])
                    @php($summary = $item['summary'])
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="text-lg font-semibold text-slate-900">{{ $search->name }}</h2>
                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold uppercase text-emerald-700">{{ $search->status->value }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">{{ $summary->airport }} · {{ $summary->dateRange }} · {{ $summary->party }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full bg-slate-100 px-2 py-1 font-medium text-slate-700">{{ $search->scored_options_count }} options found</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2 py-1 font-medium text-teal-700">
                                <x-lucide-icon name="trending-up" class="h-3.5 w-3.5" />
                                Improving
                            </span>
                            <span class="rounded-full bg-slate-100 px-2 py-1 font-medium text-slate-600">Updated {{ optional($search->last_scored_at ?? $search->updated_at)->diffForHumans() }}</span>
                        </div>
                        <a href="{{ route('searches.show', $search) }}" class="mt-5 inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                            View Results
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.holidaysage>
