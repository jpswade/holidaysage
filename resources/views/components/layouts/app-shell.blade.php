@props(['title' => 'HolidaySage', 'contentMax' => 'max-w-6xl'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#f9f7f2] font-sans text-slate-800 antialiased">
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_120%_80%_at_50%_-20%,rgba(14,165,233,0.09),transparent_50%),radial-gradient(ellipse_90%_60%_at_100%_0%,rgba(20,184,166,0.05),transparent_45%)]"></div>
        <div class="relative min-h-screen">
            <header class="border-b border-slate-200/80 bg-white/90 backdrop-blur">
                <div class="mx-auto flex w-full items-center justify-between px-6 py-4 {{ $contentMax }}">
                    <a href="{{ route('home') }}" class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-teal-600 text-white shadow-sm">
                            <x-lucide-compass class="h-5 w-5" />
                        </span>
                        <span class="flex flex-col">
                            <span class="text-xl font-bold tracking-tight text-slate-900">HolidaySage</span>
                            <span class="text-xs font-medium text-teal-700">Smarter holiday search</span>
                        </span>
                    </a>
                    <nav class="flex flex-wrap items-center justify-end gap-2">
                        <a href="{{ route('holidays.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">Browse</a>
                        <a href="{{ route('searches.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">My Searches</a>
                        <a href="{{ route('searches.create') }}" class="rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">New Search</a>
                    </nav>
                </div>
            </header>
            <main class="mx-auto w-full {{ $contentMax }} px-6 py-6 md:py-8">
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif
                {{ $slot }}
            </main>
            <footer class="mx-auto w-full {{ $contentMax }} px-6 pb-10 pt-4 text-sm text-slate-500">
                <div class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 pt-5 text-center sm:flex-row sm:text-left">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-teal-600 text-white shadow-sm">
                            <x-lucide-compass class="h-4 w-4" />
                        </span>
                        <span class="font-semibold text-slate-700">HolidaySage</span>
                    </div>
                    <div class="text-sm text-slate-500">
                        <p>&copy; {{ now()->year }} HolidaySage. All rights reserved.</p>
                        <p>Smarter holiday search. Built with care.</p>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
