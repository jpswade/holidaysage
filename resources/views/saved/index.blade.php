@php
    /** @var \App\Models\HolidayShortlist|null $shortlist */
    /** @var array<int, array{item: \App\Models\HolidayShortlistItem, viewModel: \App\ViewModels\ResultCardViewModel}> $cards */
    $isGuest = (bool) ($isGuest ?? false);
    $shareUrl = null;
    if (! $isGuest && $shortlist && $shortlist->sharing_enabled && is_string($shortlist->share_token) && $shortlist->share_token !== '') {
        $shareUrl = route('shortlists.shared.show', ['token' => $shortlist->share_token]);
    }
@endphp

<x-layouts.app-shell title="Your shortlist - HolidaySage">
    <section class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">Your shortlist</h1>
            <p class="mt-1 text-base text-slate-600">A calm space to compare the few holidays you actually like.</p>
        </div>
        @if (! $isGuest && ! empty($cards))
            <a href="{{ route('holidays.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <x-lucide-search class="h-4 w-4" />
                Find more holidays
            </a>
        @endif
    </section>

    @if (session('status'))
        <p class="mb-4 rounded-lg bg-teal-50 px-4 py-2 text-sm text-teal-900">{{ session('status') }}</p>
    @endif

    @if ($isGuest)
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="text-base font-medium text-slate-900">Sign in to start a shortlist</p>
            <p class="mt-1 text-sm text-slate-600">Your shortlist is saved to your account so you can come back to it on any device.</p>
            <div class="mt-4 flex flex-wrap justify-center gap-2">
                <a href="{{ route('login') }}" class="inline-flex items-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">Sign in</a>
                <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Create account</a>
            </div>
        </div>
    @elseif (empty($cards))
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="text-base font-medium text-slate-900">Nothing saved yet</p>
            <p class="mt-1 text-sm text-slate-600">Use the Save button on any holiday to keep a shortlist of options worth considering.</p>
            <a href="{{ route('holidays.index') }}" class="mt-4 inline-flex items-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">Browse holidays</a>
        </div>
    @else
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Share with friends and family</h2>
                    <p class="mt-1 text-sm text-slate-600">A read-only link people can open without an account.</p>
                </div>
                <form method="post" action="{{ route('saved.sharing.update') }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    @method('PATCH')
                    @if ($shortlist->sharing_enabled)
                        <button type="submit" name="action" value="rotate" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <x-lucide-refresh-cw class="h-3.5 w-3.5" />
                            New link
                        </button>
                        <button type="submit" name="action" value="disable" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Stop sharing
                        </button>
                    @else
                        <button type="submit" name="action" value="enable" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700">
                            <x-lucide-link class="h-3.5 w-3.5" />
                            Enable sharing
                        </button>
                    @endif
                </form>
            </div>
            @if ($shareUrl)
                <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                    <input type="text" value="{{ $shareUrl }}" readonly class="w-full border-0 bg-transparent p-0 font-mono text-xs text-slate-700 focus:outline-none" onclick="this.select()" />
                </div>
            @endif
        </section>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @foreach ($cards as $row)
                @php
                    $vm = $row['viewModel'];
                    $item = $row['item'];
                    $detailUrl = $vm->canonicalPropertySlug !== null
                        ? route('holidays.show', ['slug' => $vm->canonicalPropertySlug, 'p' => $vm->id])
                        : route('holidays.index');
                @endphp
                <article class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex-1 p-5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-lg font-semibold leading-tight text-slate-900"><a href="{{ $detailUrl }}" class="hover:underline">{{ $vm->hotelName }}</a></h3>
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
                    <div class="flex items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3">
                        <form method="post" action="{{ route('saved.items.destroy', ['item' => $item->id]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1 text-xs font-medium text-slate-600 hover:text-red-700">
                                <x-lucide-x class="h-3.5 w-3.5" />
                                Remove
                            </button>
                        </form>
                        <a href="{{ $detailUrl }}" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700">View details</a>
                    </div>
                </article>
            @endforeach
        </div>

        <p class="mt-6 text-sm text-slate-500">Tip: open each one in a tab to compare them later.</p>
    @endif
</x-layouts.app-shell>
