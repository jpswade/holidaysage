<?php

namespace App\Support;

use App\Models\SavedHolidaySearch;

/**
 * Parses Jet2’s `destination` query **wire format** (underscore-separated numeric area ids).
 * Normalised storage is {@see SavedHolidaySearch::$provider_destination_ids} as
 * `['jet2' => ['39', …]]` — other providers can use their own parsers and provider keys.
 */
final class Jet2DestinationAreaIdList
{
    /**
     * @return list<string>
     */
    public static function parse(string $param): array
    {
        $param = trim($param);
        if ($param === '') {
            return [];
        }
        $out = [];
        foreach (explode('_', $param) as $segment) {
            if ($segment === '') {
                return [];
            }
            if (preg_match('/^\d+$/', $segment) !== 1) {
                return [];
            }
            $out[] = $segment;
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     */
    public static function toQueryParam(array $ids): string
    {
        return implode('_', $ids);
    }
}
