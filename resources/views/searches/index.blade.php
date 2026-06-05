<x-layouts.app-shell title="Saved searches - HolidaySage">
    <section>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-900 md:text-4xl">Saved searches</h1>
                <p class="mt-2 text-base text-slate-600">A calm record of the searches you care about. Open any one to see its latest matches.</p>
            </div>
            <a href="{{ route('searches.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">
                <x-lucide-plus class="h-4 w-4" />
                Create a saved search
            </a>
        </div>

        @if ($searches->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <p class="text-lg font-semibold text-slate-900">No saved searches yet</p>
                <p class="mt-2 text-sm text-slate-600">Create your first search in under 2 minutes.</p>
                <a href="{{ route('searches.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">
                    <x-lucide-plus class="h-4 w-4" />
                    Create Search
                </a>
            </div>
        @else
            <div class="mt-8 grid gap-4 md:grid-cols-2">
                @foreach ($searches as $item)
                    @php($search = $item['search'])
                    @php($summary = $item['summary'])
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="text-3xl font-semibold tracking-tight text-slate-900">{{ $search->name }}</h2>
                            <x-lucide-ellipsis-vertical class="h-5 w-5 text-slate-400" />
                        </div>
                        <div class="mt-3 inline-flex items-center gap-1.5 text-sm text-slate-500">
                            <x-lucide-clock-3 class="h-4 w-4" />
                            Updated {{ optional($search->last_scored_at ?? $search->updated_at)->diffForHumans() }}
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-700">
                            <span class="inline-flex items-center gap-1.5">
                                <x-lucide-plane class="h-4 w-4 text-slate-400" />
                                {{ $summary->airport }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <x-lucide-calendar class="h-4 w-4 text-slate-400" />
                                {{ $summary->dateRange }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <x-lucide-users class="h-4 w-4 text-slate-400" />
                                {{ str_replace('adults', 'travellers', $summary->party) }}
                            </span>
                        </div>

                        @if (!empty($summary->preferences))
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach (array_slice($summary->preferences, 0, 3) as $preference)
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700">
                                        {{ ucwords(str_replace('_', ' ', $preference)) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-4 border-t border-slate-200 pt-3">
                            <div class="flex items-center justify-between">
                                <p class="text-4xl font-semibold tracking-tight text-slate-900">{{ $search->scored_options_count }}</p>
                                <span class="inline-flex items-center gap-1 text-xl font-semibold text-emerald-600">
                                    <x-lucide-trending-up class="h-4 w-4" />
                                    Improving
                                </span>
                            </div>
                            <p class="text-lg text-slate-600">options found</p>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <a href="{{ route('saved-searches.show', $search) }}" class="inline-flex rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Open saved search</a>
                            <a href="{{ route('holidays.index', ['search_id' => $search->id]) }}" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Browse all results</a>
                        </div>
                    </article>
                @endforeach
            </div>

        @endif
    </section>
</x-layouts.app-shell>
