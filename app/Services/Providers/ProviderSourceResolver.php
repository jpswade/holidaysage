<?php

namespace App\Services\Providers;

use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Services\Imports\ImportUrlParserRegistry;
use RuntimeException;

class ProviderSourceResolver
{
    public function __construct(
        private readonly ImportUrlParserRegistry $parsers,
    ) {}

    public function forSearch(SavedHolidaySearch $search): ProviderSource
    {
        $url = $search->provider_import_url;
        if (empty($url)) {
            throw new RuntimeException('Saved holiday search is missing provider_import_url.');
        }

        return $this->forUrl($url);
    }

    public function forUrl(string $url): ProviderSource
    {
        $this->parsers->parserFor($url);

        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            throw new RuntimeException('Invalid URL for provider resolution: '.$url);
        }

        if (preg_match('/jet2holidays\./i', $host) === 1) {
            return $this->byKey('jet2');
        }

        if (preg_match('/(tui\.|firstchoice|tuigroup)/i', $host) === 1) {
            return $this->byKey('tui');
        }

        throw new RuntimeException('No provider could be resolved for host: '.$host);
    }

    public function byKey(string $key): ProviderSource
    {
        $source = ProviderSource::query()->where('key', $key)->first();
        if (! $source) {
            throw new RuntimeException('Unknown provider key: '.$key);
        }

        return $source;
    }
}
