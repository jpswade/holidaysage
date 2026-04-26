@props(['holiday'])

@php
    assert(is_array($holiday));
    $createUrl = route('searches.create', $holiday['create_query'] ?? []);
    $price = (int) $holiday['price'];
    $perPerson = (int) $holiday['per_person'];
    $fmt = static fn (int $n): string => '£' . number_format($n);
@endphp

<article class="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="relative h-44 flex-shrink-0 overflow-hidden sm:h-48">
        <div class="absolute inset-0 bg-gradient-to-br from-sky-200 via-indigo-200 to-emerald-200"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.45),transparent_45%),radial-gradient(circle_at_80%_10%,rgba(255,255,255,0.35),transparent_35%)]"></div>
        <span class="absolute left-3 top-3 inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">{{ $holiday['display_rank'] ?? $holiday['rank'] ?? '—' }}</span>
        <span class="absolute right-3 top-3 rounded-full bg-white/90 px-2.5 py-0.5 text-xs font-semibold text-slate-800">{{ $holiday['provider'] }}</span>
    </div>

    <div class="flex flex-1 flex-col p-4 sm:p-5">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <h3 class="text-xl font-semibold leading-tight tracking-tight text-slate-900 sm:text-2xl">{{ $holiday['hotel'] }}</h3>
                <p class="mt-1 line-clamp-1 text-sm text-slate-600 sm:text-base">
                    <span class="align-middle">{{ $holiday['destination'] }}</span>
                </p>
            </div>
            <div class="flex flex-shrink-0 items-end gap-0.5 rounded-lg bg-green-600 px-2.5 py-1.5 text-right text-white sm:px-3 sm:py-2">
                <p class="text-2xl font-bold leading-none sm:text-3xl">{{ number_format((float) $holiday['score'], 1) }}</p>
                <span class="pb-0.5 text-[10px] font-semibold text-green-100 sm:text-xs">/10</span>
            </div>
        </div>

        <p class="mt-2 text-sm tracking-wider text-amber-500">★★★★★</p>

        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1.5 text-xs text-slate-600 sm:gap-x-3.5 sm:text-sm">
            <span class="inline-flex items-center gap-1">
                <x-lucide-plane class="h-3.5 w-3.5 text-slate-400 sm:h-4 sm:w-4" />
                {{ $holiday['flight'] }}
            </span>
            <span class="inline-flex items-center gap-1">
                <x-lucide-clock-3 class="h-3.5 w-3.5 text-slate-400 sm:h-4 sm:w-4" />
                {{ $holiday['transfer'] }}
            </span>
            <span class="inline-flex min-w-0 items-center gap-1">
                <x-lucide-utensils class="h-3.5 w-3.5 flex-shrink-0 text-slate-400 sm:h-4 sm:w-4" />
                <span class="min-w-0 shrink">{{ $holiday['board'] }}</span>
            </span>
        </div>

        @if (!empty($holiday['chips']))
            <div class="mt-2.5 flex flex-wrap gap-1.5 sm:mt-3 sm:gap-2">
                @foreach (array_slice($holiday['chips'], 0, 4) as $chip)
                    <span class="rounded-full border border-slate-200 bg-slate-50/90 px-2 py-0.5 text-xs font-medium text-slate-700 sm:px-2.5 sm:py-1">{{ $chip }}</span>
                @endforeach
            </div>
        @endif

        <p class="mt-3 line-clamp-3 flex-1 text-sm leading-relaxed text-slate-600">{{ $holiday['summary'] }}</p>

        @if (!empty($holiday['caveats']))
            <ul class="mt-2 space-y-0.5 text-xs text-amber-800 sm:text-sm">
                @foreach ($holiday['caveats'] as $line)
                    <li class="inline-flex items-start gap-1">
                        <x-lucide-triangle-alert class="mt-0.5 h-3 w-3 flex-shrink-0 text-amber-600 sm:h-3.5 sm:w-3.5" />
                        <span>{{ $line }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-3 flex flex-col gap-2.5 border-t border-slate-200 pt-3.5 sm:mt-4 sm:flex-row sm:items-end sm:justify-between sm:gap-3 sm:pt-4">
            <div>
                <p class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $fmt($price) }}</p>
                <p class="text-sm text-slate-600">{{ $fmt($perPerson) }} per person</p>
            </div>
            <a
                href="{{ $createUrl }}"
                class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 sm:min-w-[7.5rem] sm:w-auto"
            >
                View Deal
            </a>
        </div>
    </div>
</article>
