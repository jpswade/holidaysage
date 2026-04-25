<x-layouts.app-shell title="My Saved Searches - HolidaySage">
    <section>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-4xl font-bold tracking-tight text-slate-900">My Saved Searches</h1>
                <p class="mt-2 text-lg text-slate-600">Your holiday searches are being tracked and ranked continuously</p>
            </div>
            <a href="{{ route('searches.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">
                <x-lucide-plus class="h-4 w-4" />
                New Search
            </a>
        </div>

        <article class="mt-8 rounded-2xl border border-teal-200 bg-teal-50/40 p-5">
            <div class="flex gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-700">
                    <x-lucide-star class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">How HolidaySage works</h2>
                    <p class="mt-1 text-base leading-relaxed text-slate-600">
                        Each search continuously monitors Jet2 and TUI for the best matching holidays. We rank options based on your preferences, price trends, and availability. Results improve over time as we learn and find better deals.
                    </p>
                </div>
            </div>
        </article>

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

                        <a href="{{ route('searches.show', $search) }}" class="mt-4 inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">View Results</a>
                    </article>
                @endforeach
            </div>

            <section class="mt-12 border-t border-slate-200 pt-8">
                <div class="grid gap-4 md:grid-cols-3">
                    <article class="rounded-2xl bg-white p-5 shadow-sm">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><x-lucide-refresh-cw class="h-5 w-5" /></span>
                        <h3 class="mt-3 text-lg font-semibold text-slate-900">Check regularly</h3>
                        <p class="mt-1 text-sm text-slate-600">Results update frequently. New deals appear as availability changes.</p>
                    </article>
                    <article class="rounded-2xl bg-white p-5 shadow-sm">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><x-lucide-bell class="h-5 w-5" /></span>
                        <h3 class="mt-3 text-lg font-semibold text-slate-900">Enable alerts</h3>
                        <p class="mt-1 text-sm text-slate-600">Get notified when prices drop or highly-rated options become available.</p>
                    </article>
                    <article class="rounded-2xl bg-white p-5 shadow-sm">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><x-lucide-shield-check class="h-5 w-5" /></span>
                        <h3 class="mt-3 text-lg font-semibold text-slate-900">Trust the score</h3>
                        <p class="mt-1 text-sm text-slate-600">Our AI ranks options by how well they match your exact preferences.</p>
                    </article>
                </div>
            </section>
        @endif
    </section>
</x-layouts.app-shell>
