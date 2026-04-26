<?php

namespace App\Services\Imports\Parsers;

use App\Contracts\ImportUrlParser;
use App\Services\Imports\Parsers\Concerns\NormalisesQueryParams;
use Carbon\Carbon;

/**
 * Extracts search criteria from tui.co.uk, firstchoice.co.uk, tuiholidapackages style URLs.
 */
class TuiImportUrlParser implements ImportUrlParser
{
    use NormalisesQueryParams;

    public function supports(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return false;
        }

        return (bool) preg_match('/(tui\.co\.uk|firstchoice\.co\.uk|tui\.|tuigroup\.|firstchoice)/i', $host);
    }

    public function parse(string $url): array
    {
        $query = $this->mergeUrlQueries($url);
        $q = $this->lowerCaseKeyMap($query);

        $criteria = [];

        $depAirport = $this->getQueryValue($q, [
            'airportid', 'depairportiata', 'outboundarrival', 'departfrom', 'fromairport', 'iataout',
            'depapt', 'flyingfrom', 'departureairport', 'flying', 'flyingto',
        ]);
        if ($depAirport && preg_match('/^[A-Z]{3}$/i', $depAirport)) {
            $criteria['departure_airport_code'] = strtoupper($depAirport);
        }

        $adults = $this->intFromQuery($q, ['adult', 'adults', 'noadult', 'noofadult', 'noadul']);
        if ($adults >= 1) {
            $criteria['adults'] = $adults;
        }

        $children = $this->intFromQuery($q, ['child', 'children', 'noofchild', 'nokids', 'kds']);
        if ($children !== null) {
            $criteria['children'] = $children;
        }

        $dep = $this->getQueryValue($q, [
            'outbound', 'outbounddate', 'depdate', 'departdate', 'outdate', 'fromdate', 'holidate',
            'departs', 'holidatetime1', 'departuredateout',
        ]);
        if ($dep && $date = $this->tryParseDate($dep)) {
            $criteria['travel_start_date'] = $date;
        }

        $ret = $this->getQueryValue($q, [
            'inbound', 'inbounddate', 'returndate', 'return', 'homedatetime1', 'arrival', 'homedatetime2',
        ]);
        if ($ret && $date = $this->tryParseDate($ret)) {
            $criteria['travel_end_date'] = $date;
        }

        $nights = $this->intFromQuery($q, [
            'nights', 'night', 'nights1', 'durationnights', 'du', 'duration', 'durations',
        ]);
        if ($nights >= 1) {
            $criteria['duration_min_nights'] = $nights;
            $criteria['duration_max_nights'] = $nights;
        }

        $flex = $this->intFromQuery($q, ['dateflex', 'dateflexible', 'flexibleday', 'flexibledur']);
        if ($flex !== null) {
            $criteria['travel_date_flexibility_days'] = $flex;
        }

        $dest = $this->getQueryValue($q, [
            'destinationid', 'dest', 'holidateto', 'arrivalid', 'to', 'holidateto1', 'location', 'toresortid',
        ]);
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
        if (preg_match('/^(\d{1,2})[-.\/](\d{1,2})[-.\/](\d{4})$/', $value, $m)) {
            try {
                return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
