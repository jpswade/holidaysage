<?php

namespace App\Providers;

use App\Contracts\HolidayScorer;
use App\Listeners\MergePendingShortlistOnLogin;
use App\Models\HolidayShortlist;
use App\Policies\HolidayShortlistPolicy;
use App\Services\ProviderImport\Jet2SmartSearchHttpClient;
use App\Services\Scoring\DefaultHolidayScorer;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/holidaysage.php',
            'holidaysage'
        );

        $this->app->bind(
            HolidayScorer::class,
            DefaultHolidayScorer::class
        );

        $this->app->singleton(Jet2SmartSearchHttpClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();

        Gate::policy(HolidayShortlist::class, HolidayShortlistPolicy::class);

        Event::listen(Login::class, MergePendingShortlistOnLogin::class);

        if (filter_var(env('TRUSTED_PROXY_ALL', false), FILTER_VALIDATE_BOOL)) {
            Request::setTrustedProxies(
                ['*'],
                Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
                | Request::HEADER_X_FORWARDED_AWS_ELB
            );
        }

        // Caddy -> nginx -> PHP is HTTP, so the request looks like http to Laravel. useAssetOrigin forces
        // Vite / asset() to use APP_URL; forceRootUrl + forceScheme cover route(), redirects, etc.
        if (str_starts_with((string) config('app.url', ''), 'https://')) {
            $root = rtrim((string) config('app.url'), '/');
            URL::useAssetOrigin($root);
            URL::forceRootUrl($root);
            URL::forceScheme('https');
        }
    }
}
