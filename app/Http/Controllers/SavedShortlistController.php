<?php

namespace App\Http\Controllers;

use App\Models\HolidayShortlist;
use App\Models\HolidayShortlistItem;
use App\Models\ScoredHolidayOption;
use App\Services\CanonicalScoredOptionGuard;
use App\ViewModels\ResultCardViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SavedShortlistController extends Controller
{
    public function __construct(private readonly CanonicalScoredOptionGuard $canonicalGuard) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if ($user === null) {
            return view('saved.index', [
                'shortlist' => null,
                'cards' => [],
                'isGuest' => true,
            ]);
        }

        $shortlist = HolidayShortlist::query()->firstOrCreate(['user_id' => $user->id]);
        $shortlist->load([
            'items.scoredHolidayOption.search',
            'items.scoredHolidayOption.holidayPackage.hotel.photos',
            'items.scoredHolidayOption.holidayPackage.providerSource',
        ]);

        $cards = [];
        foreach ($shortlist->items as $item) {
            $option = $item->scoredHolidayOption;
            if ($option === null || $option->holidayPackage === null) {
                continue;
            }
            $cards[] = [
                'item' => $item,
                'viewModel' => ResultCardViewModel::fromModel($option),
            ];
        }

        return view('saved.index', [
            'shortlist' => $shortlist,
            'cards' => $cards,
            'isGuest' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scored_holiday_option_id' => ['required', 'integer', 'exists:scored_holiday_options,id'],
            'return_to' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        if ($user === null) {
            // Stage the option in a session bucket; we'll merge into the user's shortlist on login.
            $bucket = $request->session()->get('pending_shortlist_option_ids', []);
            if (! is_array($bucket)) {
                $bucket = [];
            }
            $optionId = (int) $data['scored_holiday_option_id'];
            if (! in_array($optionId, $bucket, true)) {
                $bucket[] = $optionId;
            }
            $request->session()->put('pending_shortlist_option_ids', $bucket);

            return redirect()
                ->route('login')
                ->with('status', 'Sign in to save holidays to your shortlist.');
        }

        $option = ScoredHolidayOption::query()->findOrFail((int) $data['scored_holiday_option_id']);
        if (! $this->canonicalGuard->isCanonical($option)) {
            return redirect()
                ->to($this->safeReturnTo($data['return_to'] ?? null) ?? route('holidays.index'))
                ->with('status', 'That option is no longer current. Try a similar one.');
        }

        $shortlist = HolidayShortlist::query()->firstOrCreate(['user_id' => $user->id]);
        $shortlist->items()->firstOrCreate(
            ['scored_holiday_option_id' => $option->id],
            ['position' => ($shortlist->items()->max('position') ?? 0) + 1],
        );

        return redirect()
            ->to($this->safeReturnTo($data['return_to'] ?? null) ?? route('saved.index'))
            ->with('status', 'Added to your shortlist.');
    }

    public function destroy(Request $request, HolidayShortlistItem $item): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $this->authorize('update', $item->shortlist);
        $item->delete();

        return redirect()
            ->route('saved.index')
            ->with('status', 'Removed from your shortlist.');
    }

    public function sharing(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $data = $request->validate([
            'action' => ['required', 'in:enable,disable,rotate'],
        ]);

        $shortlist = HolidayShortlist::query()->firstOrCreate(['user_id' => $user->id]);
        $this->authorize('update', $shortlist);

        match ($data['action']) {
            'enable' => $shortlist->enableSharing(),
            'disable' => $shortlist->disableSharing(),
            'rotate' => $shortlist->rotateShareToken(),
        };

        return redirect()->route('saved.index');
    }

    private function safeReturnTo(?string $returnTo): ?string
    {
        if (! is_string($returnTo) || $returnTo === '') {
            return null;
        }
        $parsed = parse_url($returnTo);
        $appHost = parse_url(config('app.url') ?? '', PHP_URL_HOST);
        $returnHost = $parsed['host'] ?? null;
        if ($returnHost !== null && $appHost !== null && $returnHost !== $appHost) {
            return null;
        }

        return $returnTo;
    }
}
