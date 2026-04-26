<?php

namespace App\Support;

/**
 * Maps Jet2 `/search/results` filter-related query values to the
 * `filters=…` segment on the smartsearch API (exclamation delimited).
 *
 * @see \App\Services\ProviderImport\Importers\Jet2LiveImporter::buildSmartSearchApiUrl
 */
final class Jet2SmartsearchFilters
{
    public const DEFAULT_INBOUND_FLIGHT_TIME_FILTER = 'inboundflighttimes_2-3';

    public const DEFAULT_OUTBOUND_FLIGHT_TIME_FILTER = 'outboundflighttimes_2-3';

    /**
     * @param  array<string, mixed>  $q  Merged results-URL query (keys are normalised to lowercase).
     * @return list<string> Ordered filter slugs (without `!` delimiters)
     */
    public static function filterSlugsFromResultsQuery(array $q): array
    {
        $q = array_change_key_case($q, CASE_LOWER);
        $filters = [
            'boardbasis_'.str_replace('_', '-', (string) ($q['boardbasis'] ?? '5_2_3')),
            'starrating_'.str_replace('_', '-', (string) ($q['starrating'] ?? '4')),
        ];
        $facility = str_replace('_', '-', (string) ($q['facility'] ?? ''));
        if ($facility !== '') {
            $filters[] = 'facility_'.$facility;
        }
        $feature = str_replace('_', '-', (string) ($q['feature'] ?? ''));
        if ($feature !== '') {
            $filters[] = 'feature_'.$feature;
        }
        $filters[] = self::flightTimesFilterSlug('inboundflighttimes', (string) ($q['inboundflighttimes'] ?? ''))
            ?? self::DEFAULT_INBOUND_FLIGHT_TIME_FILTER;
        $filters[] = self::flightTimesFilterSlug('outboundflighttimes', (string) ($q['outboundflighttimes'] ?? ''))
            ?? self::DEFAULT_OUTBOUND_FLIGHT_TIME_FILTER;

        return $filters;
    }

    /**
     * @param  'inboundflighttimes'|'outboundflighttimes'  $prefix
     */
    public static function flightTimesFilterSlug(string $prefix, string $uiValue): ?string
    {
        $t = trim($uiValue);
        if ($t === '') {
            return null;
        }
        // Jet2 smartsearch API expects bucket ids (e.g. `2-3`), while UI URLs often carry
        // clock windows (`07:00-09:59,10:00-13:59`). Keep API contract-safe by collapsing
        // any window string back to the default bucket range.
        if (str_contains($t, ':')) {
            return $prefix.'_2-3';
        }

        $slug = str_replace([',', ':', ' '], ['_', '-', ''], $t);
        if ($slug === '') {
            return null;
        }

        return $prefix.'_'.$slug;
    }
}
