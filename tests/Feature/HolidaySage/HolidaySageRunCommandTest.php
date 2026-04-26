<?php

namespace Tests\Feature\HolidaySage;

use App\Enums\SavedHolidaySearchRunStatus;
use App\Models\SavedHolidaySearch;
use App\Models\SavedHolidaySearchRun;
use App\Models\ScoredHolidayOption;
use App\Services\ProviderImport\Jet2SmartSearchHttpClient;
use Database\Seeders\ProviderSourceSeeder;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HolidaySageRunCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_search_and_runs_full_pipeline_on_sync(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        Config::set('holidaysage.import_use_stub', true);
        $this->fakeJet2HttpForStubPipeline();

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
        $this->fakeJet2Http(new MockHandler([
            new Psr7Response(503, [], 'nope'),
        ]));

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
        $this->fakeJet2Http(new MockHandler([
            new GuzzleConnectException(
                'connection timed out',
                new Psr7Request('GET', 'https://www.jet2holidays.com/api/jet2/smartsearch/search')
            ),
        ]));

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
        $this->fakeJet2Http(new MockHandler([
            new Psr7Response(200, [], json_encode(['results' => []])),
        ]));

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

        $this->fakeJet2Http(new MockHandler([
            new Psr7Response(503, [], 'busy'),
            new Psr7Response(503, [], 'still busy'),
            new Psr7Response(503, [], 'still busy'),
        ]));

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

    public function test_command_refreshes_existing_search_by_id_on_sync(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        Config::set('holidaysage.import_use_stub', true);
        $this->fakeJet2HttpForStubPipeline();

        $this->artisan('holidaysage:run', [
            'url' => $this->searchResultsUrl(),
            '--sync' => true,
        ])->assertSuccessful();

        $search = SavedHolidaySearch::query()->firstOrFail();
        $runsAfterFirst = SavedHolidaySearchRun::query()->count();

        $this->fakeJet2HttpForStubPipeline();

        $this->artisan('holidaysage:run', [
            '--search' => (string) $search->id,
            '--sync' => true,
        ])->assertSuccessful();

        $this->assertGreaterThan($runsAfterFirst, SavedHolidaySearchRun::query()->count());
    }

    public function test_command_rejects_url_and_search_together(): void
    {
        $this->seed(ProviderSourceSeeder::class);

        $this->artisan('holidaysage:run', [
            'url' => $this->searchResultsUrl(),
            '--search' => '1',
        ])->assertFailed();
    }

    public function test_command_rejects_missing_url_and_search(): void
    {
        $this->seed(ProviderSourceSeeder::class);

        $this->artisan('holidaysage:run', [])->assertFailed();
    }

    private function searchResultsUrl(): string
    {
        return 'https://www.jet2holidays.com/search/results?airport=98&date=25-07-2026&duration=10&occupancy=r2c_r2c1_4&destination=39&sortorder=1&page=1&boardbasis=5_2_3';
    }

    private function fakeJet2Http(MockHandler $handler): void
    {
        $this->app->instance(
            Jet2SmartSearchHttpClient::class,
            new Jet2SmartSearchHttpClient(
                new Client(['handler' => HandlerStack::create($handler)])
            )
        );
    }

    /**
     * Stub imports still dispatch detail fetches to Jet2 hotel URLs; without a mock, timeouts
     * would fail the batch now that errors propagate instead of being swallowed.
     */
    private function fakeJet2HttpForStubPipeline(): void
    {
        $minimalHotelHtml = '<html><head><title>Stub</title></head><body></body></html>';
        $this->fakeJet2Http(new MockHandler(array_map(
            static fn () => new Psr7Response(200, [], $minimalHotelHtml),
            range(1, 12)
        )));
    }
}
