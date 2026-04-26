@props(['summary', 'detailed' => false])

@if ($detailed)
    <div class="mt-5 space-y-4 border-t border-slate-100 pt-5">
        <p class="text-base leading-relaxed text-slate-800">
            {{ implode(' · ', $summary->primaryBullets) }}
        </p>

        @if (! empty($summary->constraintBullets))
            <p class="text-sm leading-relaxed text-slate-600">
                {{ implode(' · ', $summary->constraintBullets) }}
            </p>
        @endif

        @if ($summary->providerImportUrl)
            <div>
                <a href="{{ $summary->providerImportUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-sm font-medium text-teal-700 hover:text-teal-800">
                    <x-lucide-external-link class="h-4 w-4 shrink-0" />
                    View original provider search
                </a>
            </div>
        @endif

        @php
            $hasPreferenceChips = ! empty($summary->destinationChipLabels)
                || ! empty($summary->boardChipLabels)
                || ! empty($summary->featureChips)
                || ! empty($summary->excludedDestinationLabels)
                || ! empty($summary->excludedFeatureLabels);
        @endphp

        @if ($hasPreferenceChips)
            <div class="flex flex-col gap-3">
                @if (! empty($summary->destinationChipLabels))
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Destinations</span>
                        @foreach ($summary->destinationChipLabels as $label)
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-800">{{ $label }}</span>
                        @endforeach
                    </div>
                @endif

                @if (! empty($summary->boardChipLabels))
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Board</span>
                        @foreach ($summary->boardChipLabels as $label)
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-800">{{ $label }}</span>
                        @endforeach
                    </div>
                @endif

                @if (! empty($summary->featureChips))
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Features</span>
                        @foreach ($summary->featureChips as $chip)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50/90 px-3 py-1.5 text-sm font-medium text-slate-800">
                                <span aria-hidden="true">{{ $chip['emoji'] }}</span>
                                {{ $chip['label'] }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @if (! empty($summary->excludedDestinationLabels) || ! empty($summary->excludedFeatureLabels))
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-rose-600/90">Excluding</span>
                        @foreach ($summary->excludedDestinationLabels as $label)
                            <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-medium text-rose-900">{{ $label }}</span>
                        @endforeach
                        @foreach ($summary->excludedFeatureLabels as $label)
                            <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-medium text-rose-900">{{ $label }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
@else
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-600">
            <span class="inline-flex items-center gap-1.5 font-medium text-slate-900">
                <x-lucide-plane class="h-4 w-4 text-teal-600" />
                {{ $summary->airport }}
            </span>
            <span>{{ $summary->dateRange }}</span>
            <span>{{ $summary->nights }}</span>
            <span>{{ $summary->party }}</span>
            @if ($summary->budget)
                <span>{{ $summary->budget }}</span>
            @endif
        </div>
        @if (! empty($summary->preferences))
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (array_slice($summary->preferences, 0, 6) as $preference)
                    <span class="inline-flex items-center rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-800">
                        {{ str_replace('_', ' ', $preference) }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
@endif
