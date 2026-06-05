<?php

namespace App\Services;

use App\Enums\SavedHolidaySearchRunStatus;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Models\ScoredHolidayOption;
use App\ViewModels\ResultCardViewModel;

/**
 * Lightweight diff between the latest two completed runs of a saved search.
 *
 * - "new"      : packages present in the latest run but not the previous one.
 * - "improved" : packages in both runs whose overall_score increased by `$improvedThreshold` or more.
 */
final class SavedHolidaySearchRunDiff
{
    public function __construct(private float $improvedThreshold = 0.1) {}

    /**
     * @return array{
     *     latest: SavedHolidaySearchRun|null,
     *     previous: SavedHolidaySearchRun|null,
     *     latestCards: list<array{viewModel: ResultCardViewModel, displayRank: int}>,
     *     newCards: list<array{viewModel: ResultCardViewModel, displayRank: int}>,
     *     improvedCards: list<array{viewModel: ResultCardViewModel, displayRank: int, previousScore: float, deltaScore: float}>,
     * }
     */
    public function diffForSearch(SavedHolidaySearch $search, int $limit = 6): array
    {
        $completedRuns = $search->runs()
            ->where('status', SavedHolidaySearchRunStatus::Completed)
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        $latest = $completedRuns->get(0);
        $previous = $completedRuns->get(1);

        $latestOptions = $latest
            ? $this->optionsForRun($latest, $limit)
            : collect();

        $latestCards = $this->materialise($latestOptions);

        $newCards = [];
        $improvedCards = [];

        if ($latest && $previous) {
            $previousIndex = $previous->scoredOptions()
                ->get(['id', 'holiday_package_id', 'overall_score'])
                ->keyBy('holiday_package_id');

            $rank = 1;
            foreach ($latestOptions as $option) {
                $prev = $previousIndex->get($option->holiday_package_id);
                if ($prev === null) {
                    $newCards[] = [
                        'viewModel' => ResultCardViewModel::fromModel($option),
                        'displayRank' => $rank++,
                    ];

                    continue;
                }
                $delta = (float) $option->overall_score - (float) $prev->overall_score;
                if ($delta >= $this->improvedThreshold) {
                    $improvedCards[] = [
                        'viewModel' => ResultCardViewModel::fromModel($option),
                        'displayRank' => $rank++,
                        'previousScore' => round((float) $prev->overall_score, 1),
                        'deltaScore' => round($delta, 1),
                    ];
                }
            }
        }

        return [
            'latest' => $latest,
            'previous' => $previous,
            'latestCards' => $latestCards,
            'newCards' => array_slice($newCards, 0, $limit),
            'improvedCards' => array_slice($improvedCards, 0, $limit),
        ];
    }

    private function optionsForRun(SavedHolidaySearchRun $run, int $limit)
    {
        return $run->scoredOptions()
            ->with([
                'search',
                'holidayPackage.hotel.photos',
                'holidayPackage.providerSource',
            ])
            ->orderByRaw('rank_position IS NULL')
            ->orderBy('rank_position')
            ->orderByDesc('overall_score')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  iterable<ScoredHolidayOption>  $options
     * @return list<array{viewModel: ResultCardViewModel, displayRank: int}>
     */
    private function materialise(iterable $options): array
    {
        $out = [];
        $rank = 1;
        foreach ($options as $option) {
            $out[] = [
                'viewModel' => ResultCardViewModel::fromModel($option),
                'displayRank' => $rank++,
            ];
        }

        return $out;
    }
}
