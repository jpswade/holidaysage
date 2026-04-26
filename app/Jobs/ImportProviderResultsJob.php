<?php

namespace App\Jobs;

use App\Enums\SavedHolidaySearchRunStatus;
use App\Models\ProviderImportSnapshot;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Services\ProviderImport\ProviderHttpImporterResolver;
use App\Services\ProviderImport\ProviderImportResult;
use App\Services\ProviderImport\ProviderSearchBuilderResolver;
use App\Services\ProviderImport\StubSnapshotData;
use App\Services\Providers\ProviderSourceResolver;
use App\Support\SyncQueueLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportProviderResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;

    public function __construct(
        public int $runId,
        public int $searchId,
    ) {
        $this->timeout = (int) config('holidaysage.jet2.import_job_timeout', 90);
    }

    public function handle(
        ProviderSourceResolver $providerResolver,
        ProviderSearchBuilderResolver $builderResolver,
        ProviderHttpImporterResolver $importerResolver,
    ): void {
        $search = SavedHolidaySearch::query()->find($this->searchId);
        $run = SavedHolidaySearchRun::query()->find($this->runId);
        if (! $search || ! $run) {
            return;
        }

        try {
            $provider = $providerResolver->forSearch($search);
            $sourceUrl = $builderResolver->for($provider)->build($search, $provider);

            SyncQueueLine::line('Run #'.$this->runId.': import from '.$provider->key.' (job timeout '.$this->timeout.'s)…');
            if (! (bool) config('holidaysage.import_use_stub', true)) {
                SyncQueueLine::line('Requesting live provider results (this step blocks until the response arrives)…');
            }

            $useStub = (bool) config('holidaysage.import_use_stub', true);
            if ($useStub) {
                $result = new ProviderImportResult(
                    responseStatus: 200,
                    rawBody: 'stub',
                    candidates: StubSnapshotData::forProviderKey($provider->key)['candidates'] ?? [],
                );
            } else {
                $result = $importerResolver->for($provider)->import($sourceUrl, $search, $provider);
            }

            SyncQueueLine::line('Run #'.$this->runId.': import response received, writing snapshot and dispatching parse…');
            $snapshotData = $result->toSnapshotPayload();
            $raw = json_encode($snapshotData, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $hash = hash('sha256', $raw);
            $path = 'holidaysage/snapshots/'.$run->id.'-'.$provider->key.'-'.$hash.'.json';
            Storage::disk('local')->put($path, $raw);

            $candidates = is_array($snapshotData['candidates'] ?? null) ? $snapshotData['candidates'] : [];
            $estimate = count($candidates);

            $snapshot = ProviderImportSnapshot::query()->create([
                'saved_holiday_search_run_id' => $run->id,
                'provider_source_id' => $provider->id,
                'source_url' => $sourceUrl,
                'response_status' => $result->responseStatus,
                'snapshot_path' => $path,
                'snapshot_hash' => $hash,
                'record_count_estimate' => $estimate,
                'fetched_at' => now(),
            ]);

            $run->raw_record_count = $estimate;
            $run->save();

            $search->last_imported_at = now();
            $search->save();

            Log::info('holidaysage.import.done', [
                'run_id' => $run->id,
                'search_id' => $search->id,
                'snapshot_id' => $snapshot->id,
                'candidates' => $estimate,
            ]);

            ParseProviderSnapshotJob::dispatch($snapshot->id);
        } catch (Throwable $e) {
            $this->markFailed($run, $e);
            throw $e;
        }
    }

    private function markFailed(SavedHolidaySearchRun $run, Throwable $e): void
    {
        $run->status = SavedHolidaySearchRunStatus::Failed;
        $run->finished_at = now();
        $run->error_message = $e->getMessage();
        $run->save();
        Log::error('holidaysage.import.failed', [
            'run_id' => $run->id,
            'message' => $e->getMessage(),
        ]);
    }
}
