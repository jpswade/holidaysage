<?php

namespace App\Console\Commands;

use App\Actions\HolidaySearch\CreateSavedHolidaySearchFromUrlAction;
use App\Enums\SavedHolidaySearchRunType;
use App\Jobs\RefreshSavedHolidaySearchJob;
use App\Models\SavedHolidaySearchRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class HolidaySageRunCommand extends Command
{
    protected $signature = 'holidaysage:run
                            {url : Provider holiday search or results URL (Jet2 or TUI)}
                            {--sync : Run all queued work on the sync connection (no Horizon or queue worker)}';

    protected $description = 'Create a saved holiday search from a provider URL and start an import, parse, normalise, and score run.';

    public function handle(CreateSavedHolidaySearchFromUrlAction $action): int
    {
        $searchId = null;
        $raw = $this->argument('url');
        $url = is_string($raw) ? trim($raw) : '';
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->error('A valid http(s) URL is required.');

            return self::FAILURE;
        }

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
                $this->line('Sync mode: running pipeline stages import -> parse -> normalise -> score...');
            } else {
                $this->line('Import pipeline queued. Ensure `php artisan horizon` or `php artisan queue:work` is running.');
            }

            RefreshSavedHolidaySearchJob::dispatch(
                $search->id,
                SavedHolidaySearchRunType::Import->value
            );

            if ($this->option('sync')) {
                $run = SavedHolidaySearchRun::query()
                    ->where('saved_holiday_search_id', $search->id)
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

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->option('sync') && $searchId !== null) {
                $run = SavedHolidaySearchRun::query()
                    ->where('saved_holiday_search_id', $searchId)
                    ->latest('id')
                    ->first();
                if ($run) {
                    $this->line(sprintf(
                        'Run #%d status=%s (raw=%d, parsed=%d, normalised=%d, scored=%d).',
                        $run->id,
                        $run->status->value,
                        (int) $run->raw_record_count,
                        (int) $run->parsed_record_count,
                        (int) $run->normalised_record_count,
                        (int) $run->scored_record_count
                    ));
                }
            }
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if ($this->option('sync') && $previous !== null) {
                Config::set('queue.default', $previous);
            }
        }
    }
}
