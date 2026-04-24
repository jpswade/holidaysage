<?php

namespace Tests\Feature\HolidaySage;

use App\Enums\SavedHolidaySearchStatus;
use App\Jobs\RefreshDueSearchesJob;
use App\Jobs\RefreshSavedHolidaySearchJob;
use App\Models\SavedHolidaySearch;
use Database\Seeders\ProviderSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RefreshDueSearchesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_refresh_for_due_searches(): void
    {
        $this->seed(ProviderSourceSeeder::class);
        Queue::fake();

        SavedHolidaySearch::query()->create([
            'name' => 'Test',
            'slug' => 'test-due-'.uniqid(),
            'departure_airport_code' => 'MAN',
            'duration_min_nights' => 7,
            'duration_max_nights' => 7,
            'adults' => 2,
            'status' => SavedHolidaySearchStatus::Active,
            'next_refresh_due_at' => now()->subHour(),
        ]);

        (new RefreshDueSearchesJob)->handle();

        Queue::assertPushed(RefreshSavedHolidaySearchJob::class, 1);
    }
}
