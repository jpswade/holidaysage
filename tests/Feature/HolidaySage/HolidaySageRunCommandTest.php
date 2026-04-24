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
        Config::set('holidaysage.import_use_stub', false);
        Http::fake([
            'www.jet2holidays.com/*' => Http::response($this->jet2Html(), 200),
        ]);

        $this->artisan('holidaysage:run', [
            'url' => 'https://www.jet2holidays.com/en/holidays?Adults=2&DepartureDate=2025-08-10',
            '--sync' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('saved_holiday_searches', 1);
        $this->assertDatabaseCount('holiday_options', 1);
        $this->assertDatabaseCount('scored_holiday_options', 1);
        $this->assertNotNull(ScoredHolidayOption::query()->first()->rank_position);
    }

    public function test_live_http_non_200_marks_run_failed(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        Config::set('holidaysage.import_use_stub', false);
        Http::fake([
            'www.jet2holidays.com/*' => Http::response('nope', 503),
        ]);

        $this->artisan('holidaysage:run', [
            'url' => 'https://www.jet2holidays.com/en/holidays?Adults=2',
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
            'url' => 'https://www.jet2holidays.com/en/holidays?Adults=2',
            '--sync' => true,
        ])->assertFailed();

        $run = SavedHolidaySearchRun::query()->latest('id')->first();
        $this->assertSame(SavedHolidaySearchRunStatus::Failed, $run->status);
        $this->assertStringContainsString('timed out', (string) $run->error_message);
    }

    public function test_live_http_malformed_payload_marks_run_failed(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        Config::set('holidaysage.import_use_stub', false);
        Http::fake([
            'www.jet2holidays.com/*' => Http::response('<html><body>No ld-json</body></html>', 200),
        ]);

        $this->artisan('holidaysage:run', [
            'url' => 'https://www.jet2holidays.com/en/holidays?Adults=2',
            '--sync' => true,
        ])->assertFailed();

        $run = SavedHolidaySearchRun::query()->latest('id')->first();
        $this->assertSame(SavedHolidaySearchRunStatus::Failed, $run->status);
        $this->assertStringContainsString('did not contain parsable holiday candidates', (string) $run->error_message);
    }

    public function test_live_http_retries_then_succeeds(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        Config::set('holidaysage.import_use_stub', false);

        Http::fake([
            'www.jet2holidays.com/*' => Http::sequence()
                ->push('busy', 503)
                ->push('still busy', 503)
                ->push($this->jet2Html(), 200),
        ]);

        $this->artisan('holidaysage:run', [
            'url' => 'https://www.jet2holidays.com/en/holidays?Adults=2',
            '--sync' => true,
        ])->assertSuccessful();

        Http::assertSentCount(3);
        $this->assertDatabaseCount('scored_holiday_options', 1);
    }

    public function test_rejects_non_url_input(): void
    {
        $this->seed(ProviderSourceSeeder::class);

        $this->artisan('holidaysage:run', [
            'url' => 'not-a-valid-url',
        ])->assertFailed();
    }

    private function jet2Html(): string
    {
        return <<<'HTML'
<html>
<head>
  <script type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"Hotel",
    "name":"Sunrise Family Resort",
    "url":"https://www.jet2holidays.com/hotel/sunrise-family-resort",
    "aggregateRating":{"ratingValue":4.6,"reviewCount":211},
    "offers":{"price":"1899.00","priceCurrency":"GBP"}
  }
  </script>
</head>
<body>ok</body>
</html>
HTML;
    }
}
