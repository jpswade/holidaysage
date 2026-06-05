<?php

namespace App\Services;

use App\Models\ScoredHolidayOption;

/**
 * Validates that a ScoredHolidayOption is currently the canonical row for its
 * holiday_package_id (the "best row per package" used by global browse).
 *
 * Prevents shortlist staging of arbitrary historical ids users may have lying around.
 */
final class CanonicalScoredOptionGuard
{
    public function isCanonical(ScoredHolidayOption $option): bool
    {
        if ($option->holiday_package_id === null) {
            return false;
        }

        $canonicalId = ScoredHolidayOption::query()
            ->where('holiday_package_id', $option->holiday_package_id)
            ->orderByDesc('overall_score')
            ->orderByDesc('id')
            ->limit(1)
            ->value('id');

        return $canonicalId === $option->id;
    }
}
