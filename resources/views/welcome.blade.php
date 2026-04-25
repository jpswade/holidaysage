<x-layouts.app-shell title="HolidaySage - Find your perfect holiday">
    <section class="mx-auto max-w-4xl py-10 text-center md:py-14">
        <div class="inline-flex items-center gap-2 rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">
            <x-lucide-sparkles class="h-3.5 w-3.5" />
            Smarter holiday search
        </div>
        <h1 class="mt-5 text-5xl font-bold tracking-tight text-slate-900 md:text-7xl">
            Find your perfect
            <br />
            holiday, <span class="text-teal-600">effortlessly</span>
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-600">
            Stop comparing holidays manually. Define your preferences once, and HolidaySage continuously finds and ranks the best options from Jet2 and TUI.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('searches.create') }}" class="rounded-xl bg-teal-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">
                Create Your Search
            </a>
            <a href="{{ route('searches.index') }}" class="rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">
                View Saved Searches
            </a>
        </div>
    </section>

    <section class="border-y border-slate-200 py-14 md:py-16">
        <div class="mx-auto max-w-6xl">
            <h2 class="text-center text-4xl font-bold tracking-tight text-slate-900">How HolidaySage works</h2>
            <p class="mt-3 text-center text-lg text-slate-600">We do the hard work so you can focus on getting excited about your trip.</p>
            <div class="mt-8 grid gap-4 md:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-700"><x-lucide-compass class="h-5 w-5" /></span>
                        <span class="text-3xl font-semibold text-slate-200">1</span>
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-slate-900">Define your preferences</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">Tell us where you want to go, when, who is travelling, and what matters most.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-700"><x-lucide-refresh-cw class="h-5 w-5" /></span>
                        <span class="text-3xl font-semibold text-slate-200">2</span>
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-slate-900">We track continuously</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">Our system monitors Jet2 and TUI around the clock, including price changes.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-700"><x-lucide-star class="h-5 w-5" /></span>
                        <span class="text-3xl font-semibold text-slate-200">3</span>
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-slate-900">Get ranked recommendations</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">See a shortlist of options, each with a score that explains why it is recommended.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="py-16">
        <div class="grid gap-8 lg:grid-cols-2 lg:items-start">
            <div>
                <h2 class="text-5xl font-bold tracking-tight text-slate-900">Why use HolidaySage?</h2>
                <ul class="mt-8 space-y-5">
                    <li class="flex gap-3">
                        <x-lucide-circle-check class="mt-0.5 h-5 w-5 text-teal-600" />
                        <div><p class="text-2xl font-semibold text-slate-900">Save hours of searching</p><p class="text-lg text-slate-600">No more opening 50 tabs and comparing prices manually.</p></div>
                    </li>
                    <li class="flex gap-3">
                        <x-lucide-circle-check class="mt-0.5 h-5 w-5 text-teal-600" />
                        <div><p class="text-2xl font-semibold text-slate-900">Smart recommendations</p><p class="text-lg text-slate-600">Our AI explains why each option is recommended for your specific needs.</p></div>
                    </li>
                    <li class="flex gap-3">
                        <x-lucide-circle-check class="mt-0.5 h-5 w-5 text-teal-600" />
                        <div><p class="text-2xl font-semibold text-slate-900">Never miss a deal</p><p class="text-lg text-slate-600">Continuous tracking means we catch price drops and new availability.</p></div>
                    </li>
                    <li class="flex gap-3">
                        <x-lucide-circle-check class="mt-0.5 h-5 w-5 text-teal-600" />
                        <div><p class="text-2xl font-semibold text-slate-900">Expert guidance</p><p class="text-lg text-slate-600">Get confidence in your choice with clear scores and explanations.</p></div>
                    </li>
                </ul>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-500">Top pick</p>
                    <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">Top Pick</span>
                </div>
                <div class="mt-4 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-3xl font-semibold text-slate-900">Secrets Lanzarote Resort</h3>
                        <p class="mt-1 text-xl text-slate-600">Lanzarote, Canary Islands</p>
                    </div>
                    <div class="rounded-2xl bg-green-600 px-4 py-3 text-center text-white">
                        <p class="text-4xl font-bold leading-none">9.4</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-green-100">/10</p>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm font-medium text-slate-700">Adults Only</span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm font-medium text-slate-700">50m from beach</span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm font-medium text-slate-700">Spa included</span>
                </div>
                <p class="mt-4 text-lg leading-relaxed text-slate-600">Exceptional value for a luxury adults-only resort. The beachfront location and included spa treatments make this a standout choice.</p>
                <div class="mt-5 border-t border-slate-200 pt-5">
                    <p class="text-5xl font-bold text-slate-900">£1,847</p>
                    <div class="mt-1 flex items-center justify-between">
                        <p class="text-lg text-slate-600">£924 per person</p>
                        <span class="inline-flex items-center gap-1 text-lg font-semibold text-teal-700"><x-lucide-badge-pound-sterling class="h-5 w-5" />Best value</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-t border-slate-200 py-16 text-center">
        <h2 class="text-5xl font-bold tracking-tight text-slate-900">Ready to find your perfect holiday?</h2>
        <p class="mt-4 text-2xl text-slate-600">Create your first search in under 2 minutes.</p>
        <a href="{{ route('searches.create') }}" class="mt-8 inline-flex items-center gap-2 rounded-xl bg-teal-600 px-8 py-4 text-xl font-semibold text-white transition hover:bg-teal-700">
            Get Started
            <x-lucide-arrow-right class="h-5 w-5" />
        </a>
    </section>
</x-layouts.app-shell>
