<x-layouts.holidaysage title="HolidaySage - Find your perfect holiday">
    <section class="grid gap-10 lg:grid-cols-2 lg:items-center">
        <div>
            <p class="mb-3 text-sm font-semibold uppercase tracking-wide text-teal-700">Smarter holiday search</p>
            <h1 class="text-4xl font-bold tracking-tight text-slate-900 md:text-5xl">Find your perfect holiday, effortlessly</h1>
            <p class="mt-5 max-w-xl text-base leading-relaxed text-slate-600 md:text-lg">
                Stop comparing holidays manually. Define your preferences once, and HolidaySage continuously finds and ranks the best options from Jet2 and TUI.
            </p>
            <div class="mt-7 flex flex-wrap items-center gap-3">
                <a href="{{ route('searches.create') }}" class="rounded-lg bg-teal-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">
                    Create Your Search
                </a>
                <a href="{{ route('searches.index') }}" class="rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">
                    View Saved Searches
                </a>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Sample result · Top pick</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-900">Secrets Lanzarote Resort</h2>
            <p class="text-sm text-slate-600">Lanzarote, Canary Islands</p>
            <div class="mt-4 flex items-center gap-2">
                <span class="rounded-lg bg-slate-900 px-2.5 py-1 text-lg font-bold text-white">9.4</span>
                <span class="text-sm text-slate-600">Exceptional value with premium facilities and beachfront access.</span>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700">Adults only</span>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700">50m from beach</span>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700">Spa included</span>
            </div>
            <div class="mt-5 border-t border-slate-100 pt-4">
                <p class="text-2xl font-bold text-slate-900">£1,847</p>
                <p class="text-sm text-slate-600">£924 per person</p>
            </div>
        </div>
    </section>

    <section class="mt-16">
        <h2 class="text-2xl font-bold text-slate-900">How HolidaySage works</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-900">Define your preferences</p>
                <p class="mt-2 text-sm text-slate-600">Tell us where and when you want to travel and what matters most.</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-900">We track continuously</p>
                <p class="mt-2 text-sm text-slate-600">Our system monitors Jet2 and TUI around the clock for matching holidays.</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-slate-900">Get ranked recommendations</p>
                <p class="mt-2 text-sm text-slate-600">See a shortlist of the best options with clear scores and reasons.</p>
            </article>
        </div>
    </section>
</x-layouts.holidaysage>
