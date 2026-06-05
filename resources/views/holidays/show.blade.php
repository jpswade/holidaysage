@php
    use App\Support\BoardBasisDisplay;

    /** @var \App\ViewModels\UnifiedPropertyViewModel $property */
    $leadHotel = $property->leadHotel;
    $bestOption = $property->bestOption();
    $bestPackage = $bestOption?->holidayPackage;
    $highlightPackageId = is_int($highlightPackageId ?? null) ? $highlightPackageId : null;

    $hotelName = (string) $leadHotel->hotel_name;
    $destinationName = (string) $leadHotel->destination_name;
    $countryName = is_string($leadHotel->destination_country ?? null) ? trim((string) $leadHotel->destination_country) : '';
    $reviewText = null;
    if (is_numeric($leadHotel->review_score)) {
        $reviewText = number_format((float) $leadHotel->review_score, 1).'/5';
        if (is_numeric($leadHotel->review_count)) {
            $reviewText .= ' ('.number_format((int) $leadHotel->review_count).' reviews)';
        }
    }
    $heroImage = $leadHotel->primaryImageUrlForDisplay();

    $bestScore = $bestOption !== null ? number_format((float) $bestOption->overall_score, 1) : null;
    $shortlistRoute = \Illuminate\Support\Facades\Route::has('saved.items.store') ? route('saved.items.store') : null;

    $scoreDimensions = [];
    if ($bestOption) {
        foreach ([
            'Travel' => 'travel_score',
            'Value' => 'value_score',
            'Family fit' => 'family_fit_score',
            'Location' => 'location_score',
            'Reviews' => 'reviews_score',
        ] as $label => $field) {
            $value = $bestOption->{$field} ?? null;
            if (is_numeric($value)) {
                $scoreDimensions[$label] = (float) $value;
            }
        }
    }

    $similarPropertyQuery = \App\Models\Hotel::query()
        ->where('destination_name', $leadHotel->destination_name)
        ->where('id', '!=', $leadHotel->id);
    if (is_string($property->canonicalPropertySlug) && $property->canonicalPropertySlug !== '') {
        $similarPropertyQuery->where(function ($q) use ($property): void {
            $q->where('canonical_property_slug', '!=', $property->canonicalPropertySlug)
                ->orWhereNull('canonical_property_slug');
        });
    }
    $similarHotels = $similarPropertyQuery->limit(4)->get()
        ->unique(fn ($h) => $h->canonicalPropertySlugOrFallback())
        ->take(3);

    $reasons = is_array($bestOption?->recommendation_reasons) ? array_values(array_filter(array_map('strval', $bestOption->recommendation_reasons))) : [];
    $warnings = is_array($bestOption?->warning_flags) ? array_values(array_filter(array_map('strval', $bestOption->warning_flags))) : [];
@endphp

<x-layouts.app-shell title="{{ $hotelName }} - HolidaySage">
    <section class="mb-6">
        <p class="mb-2 text-sm text-slate-500">
            <a href="{{ route('holidays.index') }}" class="inline-flex items-center gap-1 hover:text-slate-900">
                <x-lucide-chevron-left class="h-4 w-4" />
                Back to browse
            </a>
        </p>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="relative h-56 sm:h-72">
                <div class="absolute inset-0 bg-gradient-to-br from-sky-200 via-indigo-200 to-emerald-200"></div>
                @if ($heroImage)
                    <img src="{{ $heroImage }}" alt="{{ $hotelName }}" class="absolute inset-0 h-full w-full object-cover" />
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/40 via-transparent to-transparent"></div>
                @endif
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-3">
                <div class="md:col-span-2">
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-900 md:text-4xl">{{ $hotelName }}</h1>
                    <p class="mt-1 text-base text-slate-600">
                        {{ $destinationName }}@if ($countryName !== ''), {{ $countryName }}@endif
                    </p>
                    @if ($reviewText)
                        <p class="mt-2 inline-flex items-center gap-1.5 text-sm text-slate-600">
                            <x-lucide-star class="h-4 w-4 text-amber-500" />
                            {{ $reviewText }}
                        </p>
                    @endif
                    @if (count($property->providerGroups) > 1)
                        <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-3 py-1 text-xs font-medium text-teal-800">
                            <x-lucide-shuffle class="h-3.5 w-3.5" />
                            Available across {{ count($property->providerGroups) }} providers
                        </p>
                    @endif
                </div>
                <div>
                    @if ($bestScore !== null)
                        <div class="flex items-end gap-1 rounded-lg bg-green-600 px-4 py-3 text-white">
                            <p class="text-4xl font-bold leading-none">{{ $bestScore }}</p>
                            <span class="pb-1 text-xs font-semibold text-green-100">/10</span>
                        </div>
                        <p class="mt-2 text-xs uppercase tracking-wide text-slate-500">HolidaySage score</p>
                    @endif
                    @if ($bestPackage)
                        <div class="mt-4 space-y-1 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Best price right now</p>
                            <p class="text-2xl font-bold text-slate-900">£{{ number_format((float) $bestPackage->price_total, 0) }}</p>
                            @if (is_numeric($bestPackage->price_per_person))
                                <p class="text-sm text-slate-600">£{{ number_format((float) $bestPackage->price_per_person, 0) }} per person</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if ($bestOption)
        <section class="mb-6 grid gap-6 md:grid-cols-3">
            <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Why HolidaySage rates this property</h2>
                @if ($bestOption->recommendation_summary)
                    <p class="mt-3 text-base text-slate-700">{{ $bestOption->recommendation_summary }}</p>
                @endif
                @if ($reasons !== [])
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        @foreach (array_slice($reasons, 0, 6) as $reason)
                            <li class="flex gap-2">
                                <x-lucide-check class="mt-0.5 h-4 w-4 flex-shrink-0 text-teal-600" />
                                <span>{{ $reason }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($warnings !== [])
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-900">Trade-offs to weigh up</p>
                        <ul class="mt-2 space-y-1 text-sm text-amber-900">
                            @foreach ($warnings as $warning)
                                <li class="flex gap-2">
                                    <x-lucide-triangle-alert class="mt-0.5 h-4 w-4 flex-shrink-0" />
                                    <span>{{ $warning }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Score breakdown</h2>
                @if ($scoreDimensions === [])
                    <p class="mt-2 text-sm text-slate-500">Detailed scores not available for this property.</p>
                @else
                    <ul class="mt-3 space-y-2">
                        @foreach ($scoreDimensions as $label => $value)
                            @php $percent = max(0, min(100, (float) $value * 10)); @endphp
                            <li>
                                <div class="flex items-baseline justify-between text-sm">
                                    <span class="font-medium text-slate-700">{{ $label }}</span>
                                    <span class="tabular-nums text-slate-600">{{ number_format($value, 1) }}</span>
                                </div>
                                <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full bg-teal-500" style="width: {{ $percent }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif

    @if ($bestPackage)
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Key facts</h2>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 md:grid-cols-4">
                @if ($bestPackage->airport_code)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Departure airport</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ strtoupper((string) $bestPackage->airport_code) }}</dd>
                    </div>
                @endif
                @if (is_numeric($bestPackage->nights))
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Duration</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ (int) $bestPackage->nights }} nights</dd>
                    </div>
                @endif
                @if ($bestPackage->board_type)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Board</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ BoardBasisDisplay::humanLabel($bestPackage->board_type, $bestPackage->board_recommended) }}</dd>
                    </div>
                @endif
                @if (is_numeric($bestPackage->transfer_minutes))
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Transfer</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ (int) $bestPackage->transfer_minutes }} min</dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    <section class="mb-6">
        <div class="mb-3 flex items-end justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Packages by provider</h2>
            <p class="text-xs text-slate-500">Prices can change. Confirm on the provider's website.</p>
        </div>

        <div class="space-y-6">
            @foreach ($property->providerGroups as $group)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <header class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-base font-semibold text-slate-900">{{ $group->providerName }}</h3>
                        <p class="text-xs text-slate-500">{{ count($group->options) }} {{ count($group->options) === 1 ? 'option' : 'options' }}</p>
                    </header>
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach ($group->options as $row)
                            @php
                                /** @var \App\ViewModels\ResultCardViewModel $vm */
                                $vm = $row['viewModel'];
                                /** @var \App\Models\ScoredHolidayOption $option */
                                $option = $row['option'];
                                $package = $option->holidayPackage;
                                $isHighlight = $highlightPackageId !== null && $package?->id === $highlightPackageId;
                            @endphp
                            <div @class([
                                'rounded-xl border p-4',
                                'border-teal-300 bg-teal-50/40 ring-1 ring-teal-200' => $isHighlight,
                                'border-slate-200 bg-white' => ! $isHighlight,
                            ]) id="package-{{ $package?->id ?? 'unknown' }}">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-lg font-semibold text-slate-900">{{ $vm->priceTotal }}</p>
                                    @if ($vm->pricePerPerson)
                                        <p class="text-sm text-slate-500">{{ $vm->pricePerPerson }}</p>
                                    @endif
                                </div>
                                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1.5 text-xs text-slate-600">
                                    @if ($vm->departureAirport)
                                        <span class="inline-flex items-center gap-1"><x-lucide-plane-takeoff class="h-3.5 w-3.5 text-slate-400" /> {{ $vm->departureAirport }}</span>
                                    @endif
                                    @if ($vm->nights)
                                        <span class="inline-flex items-center gap-1"><x-lucide-calendar-days class="h-3.5 w-3.5 text-slate-400" /> {{ $vm->nights }}</span>
                                    @endif
                                    @if ($vm->boardType)
                                        <span class="inline-flex items-center gap-1"><x-lucide-utensils class="h-3.5 w-3.5 text-slate-400" /> {{ $vm->boardType }}</span>
                                    @endif
                                    @if ($vm->transfer)
                                        <span class="inline-flex items-center gap-1"><x-lucide-clock-3 class="h-3.5 w-3.5 text-slate-400" /> {{ $vm->transfer }}</span>
                                    @endif
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if ($shortlistRoute && $vm->id > 0)
                                        <form method="post" action="{{ $shortlistRoute }}">
                                            @csrf
                                            <input type="hidden" name="scored_holiday_option_id" value="{{ $vm->id }}" />
                                            <input type="hidden" name="return_to" value="{{ url()->full() }}" />
                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                <x-lucide-bookmark class="h-3.5 w-3.5" />
                                                Save
                                            </button>
                                        </form>
                                    @endif
                                    @if ($vm->providerUrl)
                                        <a href="{{ $vm->providerUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700">
                                            <x-lucide-external-link class="h-3.5 w-3.5" />
                                            View on {{ $group->providerName }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    @if ($similarHotels->isNotEmpty())
        <section class="mb-6">
            <h2 class="text-lg font-semibold text-slate-900">Similar alternatives in {{ $destinationName }}</h2>
            <div class="mt-3 grid gap-4 md:grid-cols-3">
                @foreach ($similarHotels as $similar)
                    <a href="{{ route('holidays.show', ['slug' => $similar->canonicalPropertySlugOrFallback()]) }}" class="block rounded-xl border border-slate-200 bg-white p-4 transition hover:border-teal-300 hover:shadow-sm">
                        <p class="font-medium text-slate-900">{{ $similar->hotel_name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $similar->resort_name ?? $similar->destination_name }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app-shell>
