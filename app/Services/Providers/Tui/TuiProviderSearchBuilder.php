<?php

namespace App\Services\Providers\Tui;

use App\Contracts\ProviderSearchBuilder;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;

class TuiProviderSearchBuilder implements ProviderSearchBuilder
{
    public function build(SavedHolidaySearch $search, ProviderSource $provider): string
    {
        if ($search->provider_import_url) {
            $host = (string) parse_url($search->provider_import_url, PHP_URL_HOST);
            if (str_contains($host, 'tui') || str_contains($host, 'firstchoice')) {
                return $search->provider_import_url;
            }
        }

        return rtrim($provider->base_url, '/').'/holidays';
    }
}
