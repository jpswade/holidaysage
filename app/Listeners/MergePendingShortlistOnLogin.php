<?php

namespace App\Listeners;

use App\Models\HolidayShortlist;
use App\Models\ScoredHolidayOption;
use App\Models\User;
use App\Services\CanonicalScoredOptionGuard;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Session\Session;

/**
 * On successful login, fold any guest-staged shortlist option ids into the user's
 * persisted shortlist (deduped, canonical-validated).
 */
class MergePendingShortlistOnLogin
{
    public function __construct(
        private readonly Session $session,
        private readonly CanonicalScoredOptionGuard $canonicalGuard,
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $pending = $this->session->pull('pending_shortlist_option_ids', []);
        if (! is_array($pending) || $pending === []) {
            return;
        }

        $ids = array_values(array_unique(array_map(static fn ($i) => (int) $i, $pending)));
        $options = ScoredHolidayOption::query()->whereIn('id', $ids)->get();
        if ($options->isEmpty()) {
            return;
        }

        $shortlist = HolidayShortlist::query()->firstOrCreate(['user_id' => $user->id]);
        $position = (int) ($shortlist->items()->max('position') ?? 0);
        foreach ($options as $option) {
            if (! $this->canonicalGuard->isCanonical($option)) {
                continue;
            }
            $shortlist->items()->firstOrCreate(
                ['scored_holiday_option_id' => $option->id],
                ['position' => ++$position],
            );
        }
    }
}
