<?php

namespace App\Contracts;

use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;

interface ProviderSearchBuilder
{
    /**
     * Returns the full URL the importer should request for this search and provider.
     */
    public function build(SavedHolidaySearch $search, ProviderSource $provider): string;
}
