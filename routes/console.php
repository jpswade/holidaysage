<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 6 (HolidaySage): wire scheduled refresh here, for example:
// use App\Jobs\RefreshDueSearchesJob;
// Schedule::job(new RefreshDueSearchesJob)->hourly();
