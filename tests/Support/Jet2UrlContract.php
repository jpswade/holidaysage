<?php

namespace Tests\Support;

/**
 * Loads {@see tests/Fixtures/jet2_search_url_contract.json} — single source of truth for
 * real captured Jet2 search URLs used alongside the checked-in API response fixture.
 */
final class Jet2UrlContract
{
    private static ?array $cached = null;

    public static function contractPath(): string
    {
        $path = realpath(__DIR__.'/../Fixtures/jet2_search_url_contract.json');
        if ($path === false || ! is_file($path)) {
            throw new \RuntimeException('Missing tests/Fixtures/jet2_search_url_contract.json (resolved from '.__FILE__.')');
        }

        return $path;
    }

    private static function fixturesDir(): string
    {
        $d = realpath(__DIR__.'/../Fixtures');
        if ($d === false) {
            throw new \RuntimeException('tests/Fixtures directory not found relative to '.__FILE__);
        }

        return $d;
    }

    /**
     * @return array{description: string, api_response_file: string, search_results: array<string, string>}
     */
    public static function decode(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $path = self::contractPath();
        if (! is_file($path)) {
            throw new \RuntimeException('Missing fixture: '.$path);
        }
        $raw = file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            throw new \RuntimeException('Empty fixture: '.$path);
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! isset($data['api_response_file'], $data['search_results']) || ! is_array($data['search_results'])) {
            throw new \RuntimeException('Invalid structure in jet2_search_url_contract.json');
        }

        self::$cached = $data;

        return $data;
    }

    public static function apiResponseFilename(): string
    {
        return (string) self::decode()['api_response_file'];
    }

    public static function apiResponsePath(): string
    {
        $path = self::fixturesDir().DIRECTORY_SEPARATOR.self::apiResponseFilename();
        if (! is_file($path)) {
            throw new \RuntimeException('Missing API fixture: '.$path);
        }

        return $path;
    }

    public static function forCommandAndPrefill(): string
    {
        return self::searchResultsUrl('for_command_and_prefill');
    }

    public static function forImporterWithMultiDepartureAirportIds(): string
    {
        return self::searchResultsUrl('for_importer_with_multi_departure_airport_ids');
    }

    public static function forSingleRoomWithTwoChildAges(): string
    {
        return self::searchResultsUrl('for_single_room_two_child_ages');
    }

    private static function searchResultsUrl(string $key): string
    {
        $url = self::decode()['search_results'][$key] ?? null;
        if (! is_string($url) || $url === '' || ! str_starts_with($url, 'https://')) {
            throw new \RuntimeException("Missing or invalid search_results.{$key} in jet2_search_url_contract.json");
        }

        return $url;
    }
}
