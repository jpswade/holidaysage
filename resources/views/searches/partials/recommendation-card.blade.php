@props([
    'card',
    'search' => null,
    'elevated' => false,
])

<article @class([
    'overflow-hidden rounded-2xl border bg-white shadow-sm',
    'border-teal-200 shadow-teal-100/70 ring-1 ring-teal-100' => $elevated,
    'border-slate-200' => ! $elevated,
])>
    <div class="relative h-60 bg-gradient-to-br from-sky-200 via-indigo-200 to-emerald-200">
        @if ($card->imageUrl)
            <img src="{{ $card->imageUrl }}" alt="{{ $card->hotelName }}" class="h-full w-full object-cover" loading="lazy" />
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/25 via-transparent to-transparent"></div>
        @else
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.45),transparent_45%),radial-gradient(circle_at_80%_10%,rgba(255,255,255,0.35),transparent_35%),linear-gradient(to_top,rgba(15,23,42,0.16),transparent_45%)]"></div>
        @endif
        @if ($card->rank)
            <span class="absolute left-4 top-4 inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">{{ $card->rank }}</span>
        @endif
        <span class="absolute right-4 top-4 rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $card->providerName }}</span>
    </div>

    <div class="p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-3xl font-semibold tracking-tight text-slate-900">{{ $card->hotelName }}</h3>
                <p class="mt-1 inline-flex items-center gap-1.5 text-lg text-slate-600">
                    <x-lucide-map-pin class="h-4 w-4 text-slate-400" />
                    {{ $card->destinationName }}
                </p>
            </div>
            <div class="rounded-xl bg-green-600 px-3 py-2 text-center text-white">
                <p class="text-3xl font-bold leading-none">{{ number_format($card->overallScore, 1) }}</p>
                <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-green-100">/10</p>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-700">
            <span class="inline-flex items-center gap-1"><x-lucide-plane class="h-4 w-4 text-slate-400" />{{ $card->flightOutbound ?: 'Pending' }}</span>
            <span class="inline-flex items-center gap-1"><x-lucide-clock-3 class="h-4 w-4 text-slate-400" />{{ $card->transfer ?: 'Pending' }}</span>
            @if ($card->boardType)
                <span class="inline-flex items-center gap-1"><x-lucide-utensils class="h-4 w-4 text-slate-400" />{{ $card->boardType }}</span>
            @endif
        </div>

        @if (!empty($card->featureChips))
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (array_slice($card->featureChips, 0, 4) as $chip)
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $chip }}</span>
                @endforeach
            </div>
        @endif

        <p class="mt-4 border-l-2 border-teal-200/80 pl-4 text-base leading-relaxed text-slate-700">
            {{ $card->recommendationBlurb }}
        </p>

        <div class="mt-4 flex items-end justify-between gap-3 border-t border-slate-200 pt-4">
            <div>
                <p class="text-4xl font-bold tracking-tight text-slate-900">{{ $card->priceTotal }}</p>
                @if ($card->pricePerPerson)
                    <p class="text-base text-slate-600">{{ $card->pricePerPerson }}</p>
                @endif
            </div>
            @if ($search)
                <a href="{{ route('searches.deals.show', [$search, $card->id]) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">
                    View Deal
                </a>
            @endif
        </div>
    </div>
</article>
