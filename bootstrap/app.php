<?php

use App\Jobs\RefreshDueSearchesJob;
use App\Models\Airport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job(new RefreshDueSearchesJob)->hourly();

        // Airport reference data: self-heal empty DB after deploy, then refresh from upstream weekly.
        $schedule->command('holidaysage:airports:import')
            ->hourly()
            ->when(fn (): bool => Airport::query()->count() === 0)
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/airport-import.log'));

        $schedule->command('holidaysage:airports:import --refresh')
            ->weeklyOn(0, '3:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/airport-import.log'));
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
