@php
    /** @var \App\Models\HolidayShortlist $shortlist */
    /** @var array<int, array{item: \App\Models\HolidayShortlistItem, viewModel: \App\ViewModels\ResultCardViewModel}> $cards */
    $ownerName = $shortlist->user?->name ? trim((string) $shortlist->user->name) : '';
@endphp

<x-layouts.app-shell title="Shared shortlist - HolidaySage">
    <section class="mb-6">
        <p class="text-xs uppercase tracking-wide text-slate-500">Shared shortlist</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
            @if ($ownerName !== '')
                {{ $ownerName }}'s holiday shortlist
            @else
                A shared shortlist
            @endif
        </h1>
        <p class="mt-1 text-sm text-slate-600">A read-only view of the holidays in this shortlist. Open each one for more detail.</p>
    </section>

    @if (empty($cards))
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="text-base font-medium text-slate-800">Nothing on this shortlist yet</p>
            <p class="mt-1 text-sm text-slate-600">Ask the owner to add a few options.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @foreach ($cards as $row)
                @php
                    $vm = $row['viewModel'];
                    $detailUrl = $vm->canonicalPropertySlug !== null
                        ? route('holidays.show', ['slug' => $vm->canonicalPropertySlug, 'p' => $vm->id])
                        : route('holidays.index');
                @endphp
                <article class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex-1 p-5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-lg font-semibold text-slate-900"><a href="{{ $detailUrl }}" class="hover:underline">{{ $vm->hotelName }}</a></h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $vm->destinationName }}</p>
                                <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">{{ $vm->providerName }}</p>
                            </div>
                            <div class="flex flex-shrink-0 items-end gap-0.5 rounded-lg bg-green-600 px-2 py-1.5 text-white">
                                <p class="text-xl font-bold leading-none">{{ number_format($vm->overallScore, 1) }}</p>
                                <span class="pb-0.5 text-[10px] font-semibold text-green-100">/10</span>
                            </div>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-slate-900">{{ $vm->priceTotal }}</p>
                        @if ($vm->pricePerPerson)
                            <p class="text-sm text-slate-600">{{ $vm->pricePerPerson }}</p>
                        @endif
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3">
                        <a href="{{ $detailUrl }}" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700">View details</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.app-shell>
