<?php

namespace App\Services\ProviderImport;

use App\Contracts\ProviderHttpImporter;
use App\Models\ProviderSource;
use App\Services\ProviderImport\Importers\Jet2LiveImporter;
use App\Services\ProviderImport\Importers\TuiLiveImporter;
use RuntimeException;

class ProviderHttpImporterResolver
{
    public function __construct(
        private readonly Jet2LiveImporter $jet2,
        private readonly TuiLiveImporter $tui,
    ) {}

    public function for(ProviderSource $provider): ProviderHttpImporter
    {
        return match ($provider->key) {
            'jet2' => $this->jet2,
            'tui' => $this->tui,
            default => throw new RuntimeException('No live importer for provider: '.$provider->key),
        };
    }
}
