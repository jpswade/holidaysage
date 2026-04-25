@props(['summary'])

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-600">
        <span class="inline-flex items-center gap-1.5 font-medium text-slate-900">
            <x-lucide-icon name="plane" class="h-4 w-4 text-teal-600" />
            {{ $summary->airport }}
        </span>
        <span>{{ $summary->dateRange }}</span>
        <span>{{ $summary->nights }}</span>
        <span>{{ $summary->party }}</span>
        @if ($summary->budget)
            <span>{{ $summary->budget }}</span>
        @endif
    </div>
    @if (!empty($summary->preferences))
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach (array_slice($summary->preferences, 0, 6) as $preference)
                <span class="inline-flex items-center rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-800">
                    {{ str_replace('_', ' ', $preference) }}
                </span>
            @endforeach
        </div>
    @endif
</div>
