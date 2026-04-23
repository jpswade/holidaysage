<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="HolidaySage — smarter holiday search. Coming soon.">

        <title>HolidaySage — Coming soon</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen antialiased font-sans text-slate-800 bg-slate-50">
        <div class="relative min-h-screen overflow-hidden">
            {{-- Soft horizon-inspired backdrop --}}
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_120%_80%_at_50%_-20%,rgba(14,165,233,0.18),transparent_50%),radial-gradient(ellipse_90%_60%_at_100%_0%,rgba(20,184,166,0.12),transparent_45%),radial-gradient(ellipse_80%_50%_at_0%_100%,rgba(251,191,36,0.08),transparent_50%)]"
                aria-hidden="true"
            ></div>
            <div
                class="pointer-events-none absolute -top-32 left-1/2 h-96 w-[48rem] -translate-x-1/2 rounded-full bg-gradient-to-b from-sky-200/40 to-transparent blur-3xl"
                aria-hidden="true"
            ></div>

            <div class="relative flex min-h-screen flex-col">
                <header class="mx-auto flex w-full max-w-5xl items-center justify-between gap-6 px-6 py-8 md:py-10">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-xl font-bold tracking-tight text-slate-900 md:text-2xl">HolidaySage</span>
                        <span class="text-sm font-medium text-teal-700/90">Smarter holiday search</span>
                    </div>
                    <span
                        class="inline-flex shrink-0 items-center rounded-full border border-teal-200/80 bg-white/80 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-teal-800 shadow-sm backdrop-blur-sm md:px-4 md:text-sm"
                    >
                        Coming soon
                    </span>
                </header>

                <main class="mx-auto flex w-full max-w-3xl flex-1 flex-col justify-center px-6 pb-16 pt-4 md:pb-24 md:pt-8">
                    <div class="text-center">
                        <p class="mb-4 text-sm font-medium text-teal-700 md:text-base">We are nearly there</p>
                        <h1 class="text-balance text-3xl font-bold tracking-tight text-slate-900 md:text-5xl md:leading-tight">
                            Find your perfect holiday, effortlessly
                        </h1>
                        <p class="mx-auto mt-6 max-w-xl text-pretty text-base leading-relaxed text-slate-600 md:text-lg">
                            Stop comparing holidays manually. Soon you will be able to define your preferences once, and
                            HolidaySage will help you discover and rank the best options from leading UK operators.
                        </p>
                    </div>

                    {{-- Decorative preview card (non-interactive) --}}
                    <div class="mx-auto mt-12 w-full max-w-md">
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white/70 p-6 shadow-lg shadow-slate-200/50 ring-1 ring-white/60 backdrop-blur-md md:p-8"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
                                    <p class="mt-1 font-semibold text-slate-900">Your shortlist</p>
                                    <p class="mt-2 text-sm text-slate-500">Ranked matches and clear scores — launching here first.</p>
                                </div>
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500 to-sky-500 text-white shadow-md shadow-teal-500/25"
                                    aria-hidden="true"
                                >
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"
                                        />
                                    </svg>
                                </div>
                            </div>
                            <div class="mt-6 rounded-xl bg-slate-50/80 px-4 py-3 text-center text-sm font-medium text-slate-600">
                                Full experience — coming soon
                            </div>
                        </div>
                    </div>
                </main>

                <footer class="mx-auto w-full max-w-5xl px-6 py-8 text-center text-sm text-slate-500 md:py-10">
                    <p>Smarter holiday search. Built with care.</p>
                </footer>
            </div>
        </div>
    </body>
</html>
