<?php

namespace App\Console\Commands;

use App\Actions\HolidaySearch\CreateSavedHolidaySearchFromUrlAction;
use App\Enums\SavedHolidaySearchRunType;
use App\Jobs\RefreshSavedHolidaySearchJob;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Support\SyncRunProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class HolidaySageRunCommand extends Command
{
    protected $signature = 'holidaysage:run
                            {url? : Provider holiday search or results URL (Jet2 or TUI); omit when using --search}
                            {--search= : Existing saved holiday search ID to refresh (same pipeline as the web Refresh button)}
                            {--sync : Run all queued work on the sync connection (no Horizon or queue worker)}';

    protected $description = 'Create a saved holiday search from a provider URL and start a run, or refresh an existing search by ID (--search).';

    public function handle(CreateSavedHolidaySearchFromUrlAction $action): int
    {
        $searchOption = $this->option('search');
        $urlRaw = $this->argument('url');
        $url = is_string($urlRaw) ? trim($urlRaw) : '';

        if ($searchOption !== null && (string) $searchOption !== '') {
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                $this->error('Use either a provider URL argument or --search=<id>, not both.');

                return self::FAILURE;
            }

            return $this->refreshExistingSearchById((string) $searchOption);
        }

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->error('Provide a valid http(s) URL or use --search=<id> to refresh an existing saved search.');

            return self::FAILURE;
        }

        return $this->runFromNewUrl($action, $url);
    }

    private function refreshExistingSearchById(string $searchOption): int
    {
        if (! ctype_digit($searchOption) || (int) $searchOption < 1) {
            $this->error('--search must be a positive integer saved holiday search ID.');

            return self::FAILURE;
        }

        $searchId = (int) $searchOption;
        $search = SavedHolidaySearch::query()->find($searchId);
        if ($search === null) {
            $this->error("Saved holiday search #{$searchId} was not found.");

            return self::FAILURE;
        }

        $previous = null;
        if ($this->option('sync')) {
            $previous = (string) config('queue.default');
            Config::set('queue.default', 'sync');
        }

        try {
            $this->info("Refreshing saved search #{$search->id} ({$search->name})");
            if ($this->option('sync')) {
                $this->printSyncModeHints();
                $this->line('Sync mode: running pipeline import -> parse -> normalise -> score...');
                SyncRunProgress::begin($this->output->getOutput());
            } else {
                $this->line('Refresh queued. Ensure `php artisan horizon` or `php artisan queue:work` is running.');
            }

            RefreshSavedHolidaySearchJob::dispatch(
                $search->id,
                SavedHolidaySearchRunType::Manual->value
            );

            if ($this->option('sync')) {
                $this->printRunSummary($search->id);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->option('sync')) {
                SyncRunProgress::onFailure($e);
                $this->printRunSummary($search->id);
            }
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if ($this->option('sync') && $previous !== null) {
                Config::set('queue.default', $previous);
            }
        }
    }

    private function runFromNewUrl(CreateSavedHolidaySearchFromUrlAction $action, string $url): int
    {
        $searchId = null;
        $previous = null;
        if ($this->option('sync')) {
            $previous = (string) config('queue.default');
            Config::set('queue.default', 'sync');
        }

        try {
            $search = $action->handle($url, null);
            $searchId = $search->id;
            $this->info("Saved search #{$search->id} ({$search->name})");
            if ($this->option('sync')) {
                $this->printSyncModeHints();
                $this->line('Sync mode: running pipeline stages import -> parse -> normalise -> score...');
                SyncRunProgress::begin($this->output->getOutput());
            } else {
                $this->line('Import pipeline queued. Ensure `php artisan horizon` or `php artisan queue:work` is running.');
            }

            RefreshSavedHolidaySearchJob::dispatch(
                $search->id,
                SavedHolidaySearchRunType::Import->value
            );

            if ($this->option('sync')) {
                $this->printRunSummary($search->id);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->option('sync') && $searchId !== null) {
                SyncRunProgress::onFailure($e);
                $this->printRunSummary($searchId);
            }
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if ($this->option('sync') && $previous !== null) {
                Config::set('queue.default', $previous);
            }
        }
    }

    private function printSyncModeHints(): void
    {
        $jet2 = (array) config('holidaysage.jet2', []);
        $api = (float) ($jet2['api_timeout'] ?? 12.0);
        $connect = (float) ($jet2['connect_timeout'] ?? 5.0);
        $attempts = (int) ($jet2['max_5xx_attempts'] ?? 2);
        $this->line('Sync: work runs in this process. A pipeline progress bar (and a sub bar during detail fetches) shows stage updates.');
        $this->line(sprintf(
            'Sync: each live Jet2 API GET is limited to about %.0fs connect + %.0fs read (and up to %d attempt(s) on 5xx); see config/holidaysage.php.',
            $connect,
            $api,
            $attempts
        ));
    }

    private function printRunSummary(int $savedHolidaySearchId): void
    {
        $run = SavedHolidaySearchRun::query()
            ->where('saved_holiday_search_id', $savedHolidaySearchId)
            ->latest('id')
            ->first();
        if ($run) {
            $this->line(sprintf(
                'Run #%d finished with status=%s (raw=%d, parsed=%d, normalised=%d, scored=%d).',
                $run->id,
                $run->status->value,
                (int) $run->raw_record_count,
                (int) $run->parsed_record_count,
                (int) $run->normalised_record_count,
                (int) $run->scored_record_count
            ));
            if ($run->error_message) {
                $this->error('Run error: '.$run->error_message);
            }
        }
    }
}
