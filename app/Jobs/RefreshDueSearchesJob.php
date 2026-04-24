<?php

namespace App\Jobs;

use App\Enums\SavedHolidaySearchRunType;
use App\Enums\SavedHolidaySearchStatus;
use App\Models\SavedHolidaySearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshDueSearchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $query = SavedHolidaySearch::query()
            ->where('status', SavedHolidaySearchStatus::Active)
            ->whereNotNull('next_refresh_due_at')
            ->where('next_refresh_due_at', '<=', now());

        $count = 0;
        $query->chunkById(100, function ($searches) use (&$count) {
            foreach ($searches as $search) {
                RefreshSavedHolidaySearchJob::dispatch(
                    $search->id,
                    SavedHolidaySearchRunType::Scheduled->value
                );
                $count++;
            }
        });

        Log::info('holidaysage.refresh_due.dispatched', ['count' => $count]);
    }
}
