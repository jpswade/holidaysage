<?php

namespace App\Services\ProviderImport;

use App\Contracts\ProviderSearchBuilder;
use App\Models\ProviderSource;
use App\Services\Providers\Jet2\Jet2ProviderSearchBuilder;
use App\Services\Providers\Tui\TuiProviderSearchBuilder;
use RuntimeException;

class ProviderSearchBuilderResolver
{
    public function __construct(
        private readonly Jet2ProviderSearchBuilder $jet2,
        private readonly TuiProviderSearchBuilder $tui,
    ) {}

    public function for(ProviderSource $source): ProviderSearchBuilder
    {
        return match ($source->key) {
            'jet2' => $this->jet2,
            'tui' => $this->tui,
            default => throw new RuntimeException('No search builder for provider: '.$source->key),
        };
    }
}
