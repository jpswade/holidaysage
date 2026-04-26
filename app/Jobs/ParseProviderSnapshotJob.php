<?php

namespace App\Jobs;

use App\Enums\SavedHolidaySearchRunStatus;
use App\Models\ProviderImportSnapshot;
use App\Models\SavedHolidaySearchRun;
use App\Support\SyncQueueLine;
use App\Support\SyncRunProgress;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ParseProviderSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $providerImportSnapshotId,
    ) {}

    public function handle(): void
    {
        $snapshot = ProviderImportSnapshot::query()->with(['run.search', 'providerSource'])->find($this->providerImportSnapshotId);
        if (! $snapshot) {
            return;
        }

        $run = $snapshot->run;
        $search = $run->search;

        try {
            SyncQueueLine::line('Run #'.$run->id.': parsing import snapshot (snapshot #'.$snapshot->id.')…');
            if (! $snapshot->snapshot_path) {
                throw new \RuntimeException('Snapshot has no file path');
            }
            if (! Storage::disk('local')->exists($snapshot->snapshot_path)) {
                throw new \RuntimeException('Snapshot file missing: '.$snapshot->snapshot_path);
            }
            $raw = Storage::disk('local')->get($snapshot->snapshot_path);
            $data = json_decode($raw, true);
            if (! is_array($data)) {
                $data = ['candidates' => []];
            }
            $candidates = is_array($data['candidates'] ?? null) ? $data['candidates'] : [];

            $run->parsed_record_count = count($candidates);
            $run->save();

            Log::info('holidaysage.parse.done', [
                'run_id' => $run->id,
                'candidates' => count($candidates),
            ]);

            SyncRunProgress::next('Run #'.$run->id.': normalising and enriching…');

            if ($candidates === []) {
                SyncQueueLine::line('Run #'.$run->id.': no holiday rows in snapshot; scoring…');
                SyncRunProgress::next('Run #'.$run->id.': scoring options…');
                ScoreHolidayOptionsForSearchJob::dispatch($search->id, $run->id);

                return;
            }

            $providerId = $snapshot->provider_source_id;
            SyncRunProgress::startSubBar(\count($candidates));
            SyncQueueLine::line('Run #'.$run->id.': dispatching '.count($candidates).' detail-lookup job(s) (largest wait is usually here)…');

            $jobs = array_map(
                fn (array $c) => new LookupHolidayDetailJob($run->id, $search->id, $providerId, $c),
                $candidates
            );

            Bus::batch($jobs)
                ->name('detail-enrichment-run-'.$run->id)
                ->allowFailures(false)
                ->onQueue('default')
                ->then(function () use ($search, $run) {
                    SyncRunProgress::next('Run #'.$run->id.': scoring options…');
                    ScoreHolidayOptionsForSearchJob::dispatch($search->id, $run->id);
                })
                ->catch(function (Batch $batch, Throwable $e) use ($run) {
                    SyncRunProgress::onFailure($e);
                    $r = SavedHolidaySearchRun::query()->find($run->id);
                    if ($r) {
                        $r->status = SavedHolidaySearchRunStatus::Failed;
                        $r->finished_at = now();
                        $r->error_message = $e->getMessage();
                        $r->save();
                    }
                    Log::error('holidaysage.normalise_batch.failed', [
                        'run_id' => $run->id,
                        'message' => $e->getMessage(),
                    ]);
                })
                ->dispatch();
        } catch (Throwable $e) {
            SyncRunProgress::onFailure($e);
            $run->status = SavedHolidaySearchRunStatus::Failed;
            $run->finished_at = now();
            $run->error_message = $e->getMessage();
            $run->save();
            Log::error('holidaysage.parse.failed', [
                'run_id' => $run->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
