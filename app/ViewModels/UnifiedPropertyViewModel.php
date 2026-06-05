<?php

namespace App\ViewModels;

use App\Models\Hotel;

/**
 * One unified property merged across providers, rendered on /holidays/{slug}.
 */
final class UnifiedPropertyViewModel
{
    /**
     * @param  list<UnifiedPropertyProviderGroupViewModel>  $providerGroups
     */
    public function __construct(
        public readonly string $canonicalPropertySlug,
        public readonly Hotel $leadHotel,
        public readonly array $providerGroups,
    ) {}

    /** @return list<\App\Models\ScoredHolidayOption> */
    public function allOptions(): array
    {
        $out = [];
        foreach ($this->providerGroups as $group) {
            foreach ($group->options as $row) {
                $out[] = $row['option'];
            }
        }

        return $out;
    }

    public function bestOption(): ?\App\Models\ScoredHolidayOption
    {
        $options = $this->allOptions();
        if ($options === []) {
            return null;
        }
        usort($options, function (\App\Models\ScoredHolidayOption $a, \App\Models\ScoredHolidayOption $b): int {
            $aScore = (float) ($a->overall_score ?? 0);
            $bScore = (float) ($b->overall_score ?? 0);

            return $bScore <=> $aScore;
        });

        return $options[0] ?? null;
    }
}
