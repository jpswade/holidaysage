@props([
    'search' => null,
    'prefill' => [],
    'submitLabel' => 'Find My Best Holiday Options',
    'showFooterBadges' => true,
])

@php
    $prefill = is_array($prefill) ? $prefill : [];
    $featureOptions = [
        'family_friendly' => 'Family friendly',
        'near_beach' => 'Near beach',
        'walkable' => 'Walkable area',
        'swimming_pool' => 'Swimming pool',
        'kids_club' => 'Kids club',
        'adults_only' => 'Adults only',
        'all_inclusive' => 'All inclusive',
        'quiet_relaxing' => 'Quiet & relaxing',
        'near_nightlife' => 'Near nightlife',
        'spa_wellness' => 'Spa & wellness',
    ];
    $selectedFeatures = old(
        'feature_preferences',
        is_array($search?->feature_preferences)
            ? $search->feature_preferences
            : ($prefill['feature_preferences'] ?? []),
    );
    if (! is_array($selectedFeatures)) {
        $selectedFeatures = [];
    }
    $destinationList = old(
        'destination_preferences',
        is_array($search?->destination_preferences)
            ? $search->destination_preferences
            : ($prefill['destination_preferences'] ?? []),
    );
    if (! is_array($destinationList)) {
        $destinationList = [];
    }
@endphp

@foreach ($destinationList as $destination)
    <input type="hidden" name="destination_preferences[]" value="{{ e($destination) }}" />
@endforeach

<div class="grid gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <label class="text-sm font-medium text-slate-700">Search name</label>
        <input type="text" name="name" value="{{ old('name', $search?->name ?? ($prefill['name'] ?? '')) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" required />
        @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Departure airport</label>
        <input type="text" name="departure_airport_code" value="{{ old('departure_airport_code', $search?->departure_airport_code ?? ($prefill['departure_airport_code'] ?? 'MAN')) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm uppercase shadow-sm focus:border-teal-500 focus:ring-teal-500" required />
        @error('departure_airport_code') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Total budget (£)</label>
        <input type="number" step="0.01" min="0" name="budget_total" value="{{ old('budget_total', $search?->budget_total ?? ($prefill['budget_total'] ?? '')) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('budget_total') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Earliest departure</label>
        <input type="date" name="travel_start_date" value="{{ old('travel_start_date', $search?->travel_start_date?->format('Y-m-d') ?? ($prefill['travel_start_date'] ?? '')) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('travel_start_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Latest return</label>
        <input type="date" name="travel_end_date" value="{{ old('travel_end_date', $search?->travel_end_date?->format('Y-m-d') ?? ($prefill['travel_end_date'] ?? '')) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('travel_end_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Date flexibility (± days)</label>
        <input type="number" name="travel_date_flexibility_days" min="0" max="14" value="{{ old('travel_date_flexibility_days', $search?->travel_date_flexibility_days ?? ($prefill['travel_date_flexibility_days'] ?? 0)) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('travel_date_flexibility_days') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Min nights</label>
        <input type="number" name="duration_min_nights" min="1" value="{{ old('duration_min_nights', $search?->duration_min_nights ?? ($prefill['duration_min_nights'] ?? 7)) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('duration_min_nights') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Max nights</label>
        <input type="number" name="duration_max_nights" min="1" value="{{ old('duration_max_nights', $search?->duration_max_nights ?? ($prefill['duration_max_nights'] ?? 10)) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('duration_max_nights') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Adults</label>
        <input type="number" name="adults" min="1" value="{{ old('adults', $search?->adults ?? ($prefill['adults'] ?? 2)) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('adults') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Children</label>
        <input type="number" name="children" min="0" value="{{ old('children', $search?->children ?? ($prefill['children'] ?? 0)) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('children') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Infants</label>
        <input type="number" name="infants" min="0" value="{{ old('infants', $search?->infants ?? ($prefill['infants'] ?? 0)) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('infants') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Max flight (minutes)</label>
        <input type="number" name="max_flight_minutes" min="30" value="{{ old('max_flight_minutes', $search?->max_flight_minutes ?? ($prefill['max_flight_minutes'] ?? '')) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('max_flight_minutes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Max transfer (minutes)</label>
        <input type="number" name="max_transfer_minutes" min="0" value="{{ old('max_transfer_minutes', $search?->max_transfer_minutes ?? ($prefill['max_transfer_minutes'] ?? '')) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('max_transfer_minutes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div class="md:col-span-2">
        <label class="text-sm font-medium text-slate-700">Provider search URL (optional)</label>
        <input type="url" name="provider_import_url" value="{{ old('provider_import_url', $search?->provider_import_url ?? ($prefill['provider_import_url'] ?? '')) }}" placeholder="https://www.jet2holidays.com/..." class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
        @error('provider_import_url') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div class="md:col-span-2">
        <label class="text-sm font-medium text-slate-700">What matters most?</label>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach ($featureOptions as $value => $label)
                <label class="cursor-pointer">
                    <input type="checkbox" name="feature_preferences[]" value="{{ $value }}" class="peer sr-only" {{ in_array($value, $selectedFeatures, true) ? 'checked' : '' }} />
                    <span class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition peer-checked:border-teal-300 peer-checked:bg-teal-50 peer-checked:text-teal-800">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('feature_preferences') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
</div>
<div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-4">
    @if ($showFooterBadges)
        <div class="grid max-w-xl flex-1 grid-cols-3 gap-3 text-center text-xs text-slate-600">
            <span class="rounded-lg bg-slate-50 px-3 py-2">2 providers tracked</span>
            <span class="rounded-lg bg-slate-50 px-3 py-2">24/7 monitoring</span>
            <span class="rounded-lg bg-slate-50 px-3 py-2">Smart recommendations</span>
        </div>
    @else
        <p class="text-sm text-slate-600">Saving updates your criteria. Run a refresh to re-score against providers.</p>
    @endif
    <button type="submit" class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">
        {{ $submitLabel }}
    </button>
</div>
