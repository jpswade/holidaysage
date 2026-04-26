<?php

namespace App\Jobs;

use App\Enums\SavedHolidaySearchRunStatus;
use App\Enums\SavedHolidaySearchRunType;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Support\SyncQueueLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class RefreshSavedHolidaySearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $savedHolidaySearchId,
        public string $runType,
    ) {
        if (SavedHolidaySearchRunType::tryFrom($this->runType) === null) {
            throw new InvalidArgumentException('Invalid run type: '.$this->runType);
        }
    }

    public function handle(): void
    {
        $search = SavedHolidaySearch::query()->find($this->savedHolidaySearchId);
        if (! $search) {
            return;
        }

        $run = SavedHolidaySearchRun::query()->create([
            'saved_holiday_search_id' => $search->id,
            'run_type' => SavedHolidaySearchRunType::from($this->runType),
            'status' => SavedHolidaySearchRunStatus::Running,
            'provider_count' => 1,
            'started_at' => now(),
        ]);

        Log::info('holidaysage.refresh.started', [
            'search_id' => $search->id,
            'run_id' => $run->id,
            'run_type' => $this->runType,
        ]);

        SyncQueueLine::line('Run #'.$run->id.' created: queuing import job…');
        ImportProviderResultsJob::dispatch($run->id, $search->id);
    }
}
