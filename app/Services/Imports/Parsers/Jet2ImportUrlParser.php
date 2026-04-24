<?php

namespace App\Services\Imports\Parsers;

use App\Contracts\ImportUrlParser;
use App\Services\Imports\Parsers\Concerns\NormalisesQueryParams;
use Carbon\Carbon;

/**
 * Extracts search criteria from jet2holidays.com / jet2holidays.co.uk style URLs
 * (query and hash-query patterns used by their search and deeplinks).
 */
class Jet2ImportUrlParser implements ImportUrlParser
{
    use NormalisesQueryParams;

    public function supports(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return $host !== '' && preg_match('/jet2holidays\./i', $host) === 1;
    }

    public function parse(string $url): array
    {
        $query = $this->mergeUrlQueries($url);
        $q = $this->lowerCaseKeyMap($query);

        $criteria = [];

        $depAirport = $this->getQueryValue($q, [
            'depairportiata', 'deptairport', 'outbounddepartureairportid', 'outboundarrivalairportid',
            'arrival', 'iata', 'airportiata', 'from', 'deptap',
        ]);
        if ($depAirport && preg_match('/^[A-Z]{3}$/i', $depAirport)) {
            $criteria['departure_airport_code'] = strtoupper($depAirport);
        }

        $adults = $this->intFromQuery($q, ['adults', 'adult', 'noadults', 'noofadults', 'noadult']);
        if ($adults >= 1) {
            $criteria['adults'] = $adults;
        }

        $children = $this->intFromQuery($q, ['children', 'nochildren', 'noofchild', 'nokids']);
        if ($children !== null) {
            $criteria['children'] = $children;
        }

        $infants = $this->intFromQuery($q, ['infants', 'noinfants', 'noinfant']);
        if ($infants !== null) {
            $criteria['infants'] = $infants;
        }

        $dep = $this->getQueryValue($q, [
            'outbounddate', 'departuredate', 'outbound', 'outdate', 'depdate', 'fromdate', 'outboundfromdate', 'inboundoutbound', 'journeydate', 'holidate',
        ]);
        if ($dep && $date = $this->tryParseDate($dep)) {
            $criteria['travel_start_date'] = $date;
        }

        $ret = $this->getQueryValue($q, [
            'inbounddate', 'returndate', 'inbound', 'enddate', 'indate', 'till', 'inboundtill', 'homedate', 'homedatetime', 'homedatetime1',
        ]);
        if ($ret && $date = $this->tryParseDate($ret)) {
            $criteria['travel_end_date'] = $date;
        }

        $flex = $this->intFromQuery($q, ['flexibledate', 'flexibledurn', 'datesflex', 'outbounddateflex', 'pfs']);
        if ($flex !== null && $flex >= 0) {
            $criteria['travel_date_flexibility_days'] = $flex;
        }

        $duration = $this->intFromQuery($q, [
            'duration', 'nights', 'nights1', 'durationholidays', 'holidur', 'holidur1', 'durationinnights', 'durations',
        ]);
        if ($duration >= 1) {
            $criteria['duration_min_nights'] = $duration;
            $criteria['duration_max_nights'] = $duration;
        }

        $room = $this->getQueryValue($q, ['roomtypeid', 'roomid', 'board', 'room']);
        if ($room !== null && $room !== '') {
            $criteria['board_preferences'] = $this->boardHintsFromString($room);
        }

        $dest = $this->getQueryValue($q, ['destinationid', 'resort', 'arrivalid', 'location', 'holidateto']);
        if ($dest !== null && $dest !== '') {
            $criteria['destination_preferences'] = [$dest];
        }

        return $criteria;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function mergeUrlQueries(string $url): array
    {
        $q = [];
        $parts = parse_url($url);
        if (! empty($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $q1);
            $q = array_merge($q, $q1);
        }
        if (! empty($parts['fragment']) && is_string($parts['fragment']) && str_contains($parts['fragment'], '=')) {
            parse_str($parts['fragment'], $q2);
            $q = array_merge($q, $q2);
        }

        return $q;
    }

    /**
     * @param  list<string>  $names
     */
    private function intFromQuery(array $lowerQuery, array $names): ?int
    {
        $v = $this->getQueryValue($lowerQuery, $names);
        if ($v === null || $v === '') {
            return null;
        }

        return max(0, (int) $v);
    }

    private function tryParseDate(string $value): ?string
    {
        $value = urldecode($value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }
        if (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/', $value, $m)) {
            return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
        }
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $m)) {
            return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function boardHintsFromString(string $value): array
    {
        $l = strtoupper($value);
        if (str_contains($l, 'ALL') && str_contains($l, 'INC')) {
            return ['all_inclusive_preferred'];
        }
        if (str_contains($l, 'HB') || str_contains($l, 'HALF')) {
            return ['half_board_preferred'];
        }
        if (str_contains($l, 'SC') || str_contains($l, 'SELF')) {
            return ['self_catering_preferred'];
        }

        return [];
    }
}
