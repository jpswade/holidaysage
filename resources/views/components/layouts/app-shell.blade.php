@props(['title' => 'HolidaySage'])

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
    <body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_120%_80%_at_50%_-20%,rgba(14,165,233,0.14),transparent_50%),radial-gradient(ellipse_90%_60%_at_100%_0%,rgba(20,184,166,0.08),transparent_45%)]"></div>
        <div class="relative min-h-screen">
            <header class="border-b border-slate-200/80 bg-white/90 backdrop-blur">
                <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
                    <a href="{{ route('home') }}" class="flex flex-col">
                        <span class="text-xl font-bold tracking-tight text-slate-900">HolidaySage</span>
                        <span class="text-xs font-medium text-teal-700">Smarter holiday search</span>
                    </a>
                    <nav class="flex items-center gap-2">
                        <a href="{{ route('searches.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">My Searches</a>
                        <a href="{{ route('searches.create') }}" class="rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">New Search</a>
                    </nav>
                </div>
            </header>
            <main class="mx-auto w-full max-w-6xl px-6 py-8 md:py-10">
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif
                {{ $slot }}
            </main>
            <footer class="mx-auto w-full max-w-6xl px-6 pb-10 text-sm text-slate-500">
                Smarter holiday search. Built with care.
            </footer>
        </div>
    </body>
</html>
