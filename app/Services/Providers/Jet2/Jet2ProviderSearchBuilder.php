<?php

namespace App\Services\Providers\Jet2;

use App\Contracts\ProviderSearchBuilder;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;

class Jet2ProviderSearchBuilder implements ProviderSearchBuilder
{
    public function build(SavedHolidaySearch $search, ProviderSource $provider): string
    {
        if ($search->provider_import_url && str_contains($search->provider_import_url, 'jet2holidays')) {
            return $search->provider_import_url;
        }

        $base = rtrim($provider->base_url, '/').'/en/holidays';

        return $base;
    }
}
