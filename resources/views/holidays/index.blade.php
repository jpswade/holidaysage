<x-layouts.app-shell title="Browse holidays - HolidaySage">
    <section class="text-center">
        <div class="inline-flex items-center gap-2 rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">
            <x-lucide-compass class="h-3.5 w-3.5" />
            Explore, then track
        </div>
        <h1 class="mt-5 text-4xl font-bold tracking-tight text-slate-900 md:text-5xl">
            Browse holidays your way
        </h1>
        <p class="mx-auto mt-4 max-w-2xl text-lg leading-relaxed text-slate-600">
            Pick a destination, a theme, or a ready-made trip idea. We will open the search form with your choices so you can refine dates and airports, then HolidaySage ranks matching packages from Jet2 and TUI.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('searches.create') }}" class="rounded-xl bg-teal-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">
                Start from scratch
            </a>
            <a href="{{ route('searches.index') }}" class="rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">
                My saved searches
            </a>
        </div>
    </section>

    <section class="mt-14">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900">Popular destinations</h2>
        <p class="mt-2 text-slate-600">Jump in with a region in mind — adjust airports and dates on the next step.</p>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($destinationShortcuts as $item)
                <a
                    href="{{ route('searches.create', $item['query']) }}"
                    class="group rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-teal-200 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900 group-hover:text-teal-800">{{ $item['label'] }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $item['country'] }}</p>
                        </div>
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 group-hover:bg-teal-50 group-hover:text-teal-700">
                            <x-lucide-map-pin class="h-4 w-4" />
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-medium text-teal-700">Set up tracking →</p>
                </a>
            @endforeach
        </div>
    </section>

    <section class="mt-14">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900">What matters most?</h2>
        <p class="mt-2 text-slate-600">We will pre-select matching preferences; you can toggle more before saving.</p>
        <div class="mt-6 flex flex-wrap gap-2">
            @foreach ($themeShortcuts as $item)
                <a
                    href="{{ route('searches.create', $item['query']) }}"
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-teal-300 hover:bg-teal-50 hover:text-teal-900"
                >
                    <x-lucide-sparkles class="h-4 w-4 text-teal-600" />
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </section>

    <section class="mt-14">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900">Trip ideas</h2>
        <p class="mt-2 text-slate-600">Sensible defaults you can edit before creating your saved search.</p>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            @foreach ($tripIdeaShortcuts as $item)
                <a
                    href="{{ route('searches.create', $item['query']) }}"
                    class="rounded-2xl border border-slate-200 bg-[#fffdfa] p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-teal-200 hover:shadow-md"
                >
                    <h3 class="text-lg font-semibold text-slate-900">{{ $item['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $item['description'] }}</p>
                    <p class="mt-4 text-sm font-semibold text-teal-700">Use this template →</p>
                </a>
            @endforeach
        </div>
    </section>

    <article class="mt-14 rounded-2xl border border-teal-200 bg-teal-50/40 p-6 md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Already have a Jet2 or TUI results link?</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                    Paste it on the create search page to import your criteria automatically.
                </p>
            </div>
            <a href="{{ route('searches.create') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">
                Import from provider URL
            </a>
        </div>
    </article>
</x-layouts.app-shell>
