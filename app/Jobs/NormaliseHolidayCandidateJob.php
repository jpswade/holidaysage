<?php

namespace App\Jobs;

use App\Models\ProviderSource;
use App\Models\SavedHolidaySearchRun;
use App\Services\Normalisation\HolidayOptionNormaliser;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class NormaliseHolidayCandidateJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $candidate
     */
    public function __construct(
        public int $runId,
        public int $searchId,
        public int $providerSourceId,
        public array $candidate,
    ) {
        if ($this->candidate === []) {
            throw new InvalidArgumentException('Candidate data cannot be empty');
        }
    }

    public function handle(HolidayOptionNormaliser $normaliser): void
    {
        if ($this->batch() !== null && $this->batch()->cancelled()) {
            return;
        }

        $provider = ProviderSource::query()->findOrFail($this->providerSourceId);

        try {
            $signed = $normaliser->normaliseAndSign($this->candidate, $provider);
            $option = $normaliser->upsert($provider, $signed);

            DB::transaction(function () use ($option): void {
                $run = SavedHolidaySearchRun::query()->lockForUpdate()->find($this->runId);
                if (! $run) {
                    return;
                }
                $ids = $run->imported_holiday_package_ids ?? [];
                $ids[] = $option->id;
                $run->imported_holiday_package_ids = $ids;
                $run->normalised_record_count = count($ids);
                $run->save();
            });

            Log::info('holidaysage.normalise.option', [
                'run_id' => $this->runId,
                'holiday_package_id' => $option->id,
            ]);
        } catch (Throwable $e) {
            Log::error('holidaysage.normalise.failed', [
                'run_id' => $this->runId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
