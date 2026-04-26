<?php

namespace App\Support;

use App\Models\SavedHolidaySearch;

/**
 * Jet2 "results" search **wire** format. Stored on {@see SavedHolidaySearch::$provider_occupancy} as
 * e.g. `['jet2' => 'r2c_r2c1_4']` (other providers can add their own keys in that map).
 * Normalised `adults` / `children` / `infants` are also derived for search criteria.
 * Jet2 "results" search URLs use an occupancy string such as
 * <code>r2c</code> (2 adults) or <code>r2c1_4</code> (2 adults + 2 children aged 1 and 4), or
 * <code>r2c_r2c1_4</code> (2 rooms, each segment uses "r" as a delimiter).
 */
final class Jet2OccupancyQuery
{
    /**
     * Sums room-level adults/children for a Jet2 occupancy string.
     *
     * @return array{adults: int, children: int, infants: int}|null
     */
    public static function totals(string $s): ?array
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }

        $adults = 0;
        $ages = [];
        $rooms = array_map(static fn (string $r) => rtrim($r, '_'), array_filter(explode('r', $s)));

        foreach ($rooms as $room) {
            if ($room === '') {
                continue;
            }
            if (! preg_match('/^(\d+)c(.*)$/A', $room, $m)) {
                return null;
            }
            $adults += (int) $m[1];
            $rest = ltrim((string) $m[2], '_');
            if ($rest === '') {
                continue;
            }
            foreach (preg_split('/_+/', $rest) ?: [] as $part) {
                if ($part === '' || ! is_numeric($part)) {
                    return null;
                }
                $ages[] = (int) $part;
            }
        }

        if ($adults < 1) {
            return null;
        }

        $children = count($ages);
        $infants = 0;

        return [
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
        ];
    }
}
