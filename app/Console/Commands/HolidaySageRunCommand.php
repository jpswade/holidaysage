<?php

namespace App\Console\Commands;

use App\Actions\HolidaySearch\CreateSavedHolidaySearchFromUrlAction;
use App\Enums\SavedHolidaySearchRunType;
use App\Jobs\RefreshSavedHolidaySearchJob;
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
            $this->info("Saved search #{$search->id} ({$search->name})");
            $this->line('Import pipeline queued. Ensure `php artisan horizon` or `php artisan queue:work` is running, unless you used --sync.');

            RefreshSavedHolidaySearchJob::dispatch(
                $search->id,
                SavedHolidaySearchRunType::Import->value
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if ($this->option('sync') && $previous !== null) {
                Config::set('queue.default', $previous);
            }
        }
    }
}
