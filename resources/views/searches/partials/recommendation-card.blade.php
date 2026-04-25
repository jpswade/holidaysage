@props([
    'card',
    'elevated' => false,
])

<article @class([
    'rounded-2xl border bg-white p-5 shadow-sm',
    'border-teal-200 shadow-teal-100/70 ring-1 ring-teal-100' => $elevated,
    'border-slate-200' => ! $elevated,
])>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="mb-2 flex items-center gap-2">
                @if ($card->rank)
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">{{ $card->rank }}</span>
                @endif
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $card->providerName }}</span>
            </div>
            <h3 class="text-lg font-semibold text-slate-900">{{ $card->hotelName }}</h3>
            <p class="text-sm text-slate-600">{{ $card->destinationName }}</p>
        </div>
        <div class="rounded-xl bg-slate-900 px-3 py-2 text-right text-white">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-300">Score</p>
            <p class="text-xl font-bold">{{ number_format($card->overallScore, 1) }}</p>
        </div>
    </div>

    <div class="mt-4 grid gap-2 text-sm text-slate-700 md:grid-cols-3">
        <div>{{ $card->nights }}</div>
        <div>{{ $card->flightOutbound ?: 'Flight time pending' }}</div>
        <div>{{ $card->transfer ?: 'Transfer pending' }}</div>
        @if ($card->boardType)
            <div>{{ $card->boardType }}</div>
        @endif
        @if ($card->review)
            <div>{{ $card->review }}</div>
        @endif
    </div>

    @if (!empty($card->featureChips))
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($card->featureChips as $chip)
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $chip }}</span>
            @endforeach
        </div>
    @endif

    @if ($card->recommendationSummary)
        <p class="mt-4 text-sm leading-relaxed text-slate-700">{{ $card->recommendationSummary }}</p>
    @endif

    @if (!empty($card->reasons))
        <ul class="mt-4 space-y-1 text-sm text-slate-700">
            @foreach (array_slice($card->reasons, 0, 3) as $reason)
                <li class="flex items-start gap-2">
                    <x-lucide-trending-up class="mt-0.5 h-4 w-4 shrink-0 text-teal-600" />
                    <span>{{ $reason }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if (!empty($card->warnings))
        <ul class="mt-3 space-y-1 text-sm text-amber-800">
            @foreach (array_slice($card->warnings, 0, 2) as $warning)
                <li class="flex items-start gap-2 rounded-lg bg-amber-50 px-2 py-1.5">
                    <x-lucide-triangle-alert class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                    <span>{{ $warning }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mt-5 flex flex-wrap items-end justify-between gap-3 border-t border-slate-100 pt-4">
        <div>
            <p class="text-2xl font-bold text-slate-900">{{ $card->priceTotal }}</p>
            @if ($card->pricePerPerson)
                <p class="text-sm text-slate-600">{{ $card->pricePerPerson }}</p>
            @endif
        </div>
        @if ($card->providerUrl)
            <a href="{{ $card->providerUrl }}" target="_blank" rel="noreferrer noopener" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">
                View Deal
            </a>
        @endif
    </div>
</article>
