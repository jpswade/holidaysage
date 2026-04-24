<?php

namespace App\Jobs;

use App\Contracts\HolidayScorer;
use App\Enums\SavedHolidaySearchRunStatus;
use App\Models\HolidayPackage;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Models\ScoredHolidayOption;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScoreHolidayOptionsForSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $searchId,
        public int $runId,
    ) {}

    public function handle(HolidayScorer $scorer): void
    {
        $search = SavedHolidaySearch::query()->find($this->searchId);
        $run = SavedHolidaySearchRun::query()->find($this->runId);
        if (! $search || ! $run) {
            return;
        }

        try {
            ScoredHolidayOption::query()
                ->where('saved_holiday_search_run_id', $run->id)
                ->delete();

            $ids = is_array($run->imported_holiday_package_ids) ? $run->imported_holiday_package_ids : [];
            $rows = [];
            foreach ($ids as $id) {
                $opt = HolidayPackage::query()->with('hotel')->find($id);
                if (! $opt) {
                    continue;
                }
                $breakdown = $scorer->score($search, $opt);
                $rows[] = [
                    'model' => $opt,
                    'breakdown' => $breakdown,
                ];
            }

            $sortable = $rows;
            usort($sortable, function (array $a, array $b): int {
                $da = $a['breakdown'];
                $db = $b['breakdown'];
                if ($da->isDisqualified !== $db->isDisqualified) {
                    return $da->isDisqualified <=> $db->isDisqualified;
                }
                if (abs($db->overallScore - $da->overallScore) > 0.0001) {
                    return $db->overallScore <=> $da->overallScore;
                }
                if ($a['model']->price_total != $b['model']->price_total) {
                    return $a['model']->price_total <=> $b['model']->price_total;
                }
                $ta = (int) ($a['model']->transfer_minutes ?? 99999);
                $tb = (int) ($b['model']->transfer_minutes ?? 99999);

                return $ta <=> $tb;
            });

            $rank = 0;
            foreach ($sortable as $item) {
                $b = $item['breakdown'];
                $opt = $item['model'];
                $pos = null;
                if (! $b->isDisqualified) {
                    $rank++;
                    $pos = $rank;
                }
                ScoredHolidayOption::query()->create([
                    'saved_holiday_search_id' => $search->id,
                    'saved_holiday_search_run_id' => $run->id,
                    'holiday_package_id' => $opt->id,
                    'overall_score' => $b->overallScore,
                    'travel_score' => $b->travelScore,
                    'value_score' => $b->valueScore,
                    'family_fit_score' => $b->familyFitScore,
                    'location_score' => $b->locationScore,
                    'board_score' => $b->boardScore,
                    'price_score' => $b->priceScore,
                    'is_disqualified' => $b->isDisqualified,
                    'disqualification_reasons' => $b->disqualificationReasons,
                    'warning_flags' => $b->warningFlags,
                    'recommendation_summary' => $b->recommendationSummary,
                    'recommendation_reasons' => $b->recommendationReasons,
                    'rank_position' => $pos,
                ]);
            }

            $run->scored_record_count = count($rows);
            $run->status = SavedHolidaySearchRunStatus::Completed;
            $run->finished_at = now();
            $run->save();

            $search->last_scored_at = now();
            if (! $search->last_imported_at) {
                $search->last_imported_at = now();
            }
            $search->next_refresh_due_at = now()->addDay();
            $search->save();

            Log::info('holidaysage.score.done', [
                'run_id' => $run->id,
                'scored' => count($rows),
            ]);
        } catch (Throwable $e) {
            $run->status = SavedHolidaySearchRunStatus::Failed;
            $run->finished_at = now();
            $run->error_message = $e->getMessage();
            $run->save();
            Log::error('holidaysage.score.failed', [
                'run_id' => $run->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
