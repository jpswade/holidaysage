<?php

namespace Tests\Feature\HolidaySage;

use App\Enums\SavedHolidaySearchRunStatus;
use App\Models\SavedHolidaySearchRun;
use App\Models\ScoredHolidayOption;
use Database\Seeders\ProviderSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HolidaySageRunCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_search_and_runs_full_pipeline_on_sync(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        Config::set('holidaysage.import_use_stub', true);

        $this->artisan('holidaysage:run', [
            'url' => $this->searchResultsUrl(),
            '--sync' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('saved_holiday_searches', 1);
        $this->assertDatabaseCount('hotels', 1);
        $this->assertDatabaseCount('holiday_packages', 1);
        $this->assertDatabaseCount('scored_holiday_options', 1);
        $this->assertNotNull(ScoredHolidayOption::query()->first());
    }

    public function test_live_http_non_200_marks_run_failed(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        Config::set('holidaysage.import_use_stub', false);
        Http::fake([
            'www.jet2holidays.com/api/jet2/smartsearch/search*' => Http::response('nope', 503),
        ]);

        $this->artisan('holidaysage:run', [
            'url' => $this->searchResultsUrl(),
            '--sync' => true,
        ])->assertFailed();

        $this->assertSame(
            SavedHolidaySearchRunStatus::Failed,
            SavedHolidaySearchRun::query()->latest('id')->first()->status
        );
    }

    public function test_live_http_timeout_marks_run_failed(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        Config::set('holidaysage.import_use_stub', false);
        Http::fake(function () {
            throw new ConnectionException('connection timed out');
        });

        $this->artisan('holidaysage:run', [
            'url' => $this->searchResultsUrl(),
            '--sync' => true,
        ])->assertFailed();

        $run = SavedHolidaySearchRun::query()->latest('id')->first();
        $this->assertSame(SavedHolidaySearchRunStatus::Failed, $run->status);
        $this->assertNotSame('', trim((string) $run->error_message));
    }

    public function test_live_http_malformed_payload_marks_run_failed(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        Config::set('holidaysage.import_use_stub', false);
        Http::fake([
            'www.jet2holidays.com/api/jet2/smartsearch/search*' => Http::response(json_encode(['results' => []]), 200),
        ]);

        $this->artisan('holidaysage:run', [
            'url' => $this->searchResultsUrl(),
            '--sync' => true,
        ])->assertFailed();

        $run = SavedHolidaySearchRun::query()->latest('id')->first();
        $this->assertSame(SavedHolidaySearchRunStatus::Failed, $run->status);
        $this->assertStringContainsString('did not contain holiday candidates', (string) $run->error_message);
    }

    public function test_live_http_retries_then_fails_after_exhausting_attempts(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        Config::set('holidaysage.import_use_stub', false);

        // Jet2LiveImporter uses Http::retry([300, 1000, 2000], ...): four attempts; all must be non-2xx to fail.
        Http::fake([
            'www.jet2holidays.com/api/jet2/smartsearch/search*' => Http::sequence()
                ->push('busy', 503)
                ->push('still busy', 503)
                ->push('still busy', 503)
                ->push('still busy', 503),
        ]);

        $this->artisan('holidaysage:run', [
            'url' => $this->searchResultsUrl(),
            '--sync' => true,
        ])->assertFailed();

        $this->assertSame(
            SavedHolidaySearchRunStatus::Failed,
            SavedHolidaySearchRun::query()->latest('id')->first()->status
        );
    }

    public function test_rejects_non_url_input(): void
    {
        $this->seed(ProviderSourceSeeder::class);

        $this->artisan('holidaysage:run', [
            'url' => 'not-a-valid-url',
        ])->assertFailed();
    }

    private function searchResultsUrl(): string
    {
        return 'https://www.jet2holidays.com/search/results?airport=98&date=25-07-2026&duration=10&occupancy=r2c_r2c1_4&destination=39&sortorder=1&page=1&boardbasis=5_2_3';
    }
}
