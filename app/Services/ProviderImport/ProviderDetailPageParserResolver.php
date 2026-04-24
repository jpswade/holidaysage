<?php

namespace App\Services\ProviderImport;

use App\Contracts\ProviderDetailPageParser;
use App\Models\ProviderSource;
use App\Services\ProviderImport\DetailParsers\Jet2DetailPageParser;
use RuntimeException;

class ProviderDetailPageParserResolver
{
    public function __construct(
        private readonly Jet2DetailPageParser $jet2,
    ) {}

    public function for(ProviderSource $provider): ProviderDetailPageParser
    {
        return match ($provider->key) {
            'jet2' => $this->jet2,
            default => throw new RuntimeException('No detail page parser for provider: '.$provider->key),
        };
    }
}
