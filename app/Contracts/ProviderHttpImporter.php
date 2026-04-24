<?php

namespace App\Contracts;

use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Services\ProviderImport\ProviderImportResult;

interface ProviderHttpImporter
{
    public function providerKey(): string;

    public function import(string $url, SavedHolidaySearch $search, ProviderSource $provider): ProviderImportResult;
}
