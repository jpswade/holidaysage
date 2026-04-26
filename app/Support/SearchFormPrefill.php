<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Whitelisted, validated query-string values for create/edit search forms.
 */
final class SearchFormPrefill
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(Request $request): array
    {
        $allowedFeatures = [
            'family_friendly',
            'near_beach',
            'walkable',
            'swimming_pool',
            'kids_club',
            'adults_only',
            'all_inclusive',
            'quiet_relaxing',
            'near_nightlife',
            'spa_wellness',
        ];

        $out = [];

        $code = $request->query('departure_airport_code');
        if (is_string($code) && $code !== '') {
            $normalised = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $code), 0, 8));
            if ($normalised !== '') {
                $out['departure_airport_code'] = $normalised;
            }
        }

        foreach (['travel_start_date', 'travel_end_date'] as $key) {
            $v = $request->query($key);
            if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1) {
                $out[$key] = $v;
            }
        }

        if (! isset($out['travel_start_date'])) {
            $dateQ = $request->query('date');
            if (is_string($dateQ) && $dateQ !== '') {
                $iso = self::jet2DdMmYyyyToIso($dateQ);
                if ($iso !== null) {
                    $out['travel_start_date'] = $iso;
                }
            }
        }

        $flex = $request->query('travel_date_flexibility_days');
        if (is_numeric($flex)) {
            $n = (int) $flex;
            if ($n >= 0 && $n <= 14) {
                $out['travel_date_flexibility_days'] = $n;
            }
        }

        foreach (['duration_min_nights', 'duration_max_nights'] as $key) {
            $v = $request->query($key);
            if (! is_numeric($v)) {
                continue;
            }
            $n = (int) $v;
            if ($n >= 1 && $n <= 30) {
                $out[$key] = $n;
            }
        }

        if (isset($out['duration_min_nights'], $out['duration_max_nights']) && $out['duration_max_nights'] < $out['duration_min_nights']) {
            unset($out['duration_min_nights'], $out['duration_max_nights']);
        }

        foreach (['adults', 'children', 'infants'] as $key) {
            $v = $request->query($key);
            if (! is_numeric($v)) {
                continue;
            }
            $n = (int) $v;
            $min = $key === 'adults' ? 1 : 0;
            if ($n >= $min && $n <= 10) {
                $out[$key] = $n;
            }
        }

        $occ = $request->query('occupancy');
        $occProvider = strtolower(trim((string) $request->query('occupancy_provider', 'jet2')));
        if (! preg_match('/^[a-z0-9_-]+$/i', $occProvider)) {
            $occProvider = 'jet2';
        }
        if (is_string($occ) && $occ !== '') {
            $occMap = is_array($out['provider_occupancy'] ?? null) ? $out['provider_occupancy'] : [];
            $occMap[$occProvider] = $occ;
            $out['provider_occupancy'] = $occMap;
            if ($occProvider === 'jet2' && $totals = Jet2OccupancyQuery::totals($occ)) {
                if ($totals['adults'] >= 1 && $totals['adults'] <= 10) {
                    $out['adults'] = $totals['adults'];
                }
                if ($totals['children'] >= 0 && $totals['children'] <= 10) {
                    $out['children'] = $totals['children'];
                }
                if ($totals['infants'] >= 0 && $totals['infants'] <= 10) {
                    $out['infants'] = $totals['infants'];
                }
            }
        }

        $budget = $request->query('budget_total');
        if (is_numeric($budget)) {
            $b = (float) $budget;
            if ($b >= 0 && $b <= 1_000_000_000) {
                $out['budget_total'] = $b;
            }
        }

        foreach (['max_flight_minutes', 'max_transfer_minutes'] as $key) {
            $v = $request->query($key);
            if (! is_numeric($v)) {
                continue;
            }
            $n = (int) $v;
            if ($key === 'max_flight_minutes' && $n >= 30 && $n <= 1440) {
                $out[$key] = $n;
            }
            if ($key === 'max_transfer_minutes' && $n >= 0 && $n <= 600) {
                $out[$key] = $n;
            }
        }

        $features = $request->query('feature_preferences');
        if (is_string($features)) {
            $features = [$features];
        } elseif (! is_array($features)) {
            $features = [];
        }
        $filteredFeatures = [];
        foreach ($features as $f) {
            if (! is_string($f)) {
                continue;
            }
            if (in_array($f, $allowedFeatures, true)) {
                $filteredFeatures[] = $f;
            }
        }
        $filteredFeatures = array_values(array_unique($filteredFeatures));
        if ($filteredFeatures !== []) {
            $out['feature_preferences'] = $filteredFeatures;
        }

        $destinations = $request->query('destination_preferences');
        if (is_string($destinations)) {
            $destinations = [$destinations];
        } elseif (! is_array($destinations)) {
            $destinations = [];
        }
        $destOut = [];
        foreach (array_slice($destinations, 0, 10) as $d) {
            if (! is_string($d)) {
                continue;
            }
            $t = trim(substr($d, 0, 80));
            if ($t !== '') {
                $destOut[] = $t;
            }
        }
        $destOut = array_values(array_unique($destOut));
        if ($destOut !== []) {
            $out['destination_preferences'] = $destOut;
        }

        $destParam = $request->query('destination');
        $destProvider = strtolower(trim((string) $request->query('destination_provider', 'jet2')));
        if (! preg_match('/^[a-z0-9_-]+$/i', $destProvider)) {
            $destProvider = 'jet2';
        }
        if (is_string($destParam) && $destParam !== '' && $destProvider === 'jet2') {
            $ids = Jet2DestinationAreaIdList::parse($destParam);
            if ($ids !== []) {
                $map = is_array($out['provider_destination_ids'] ?? null) ? $out['provider_destination_ids'] : [];
                $map['jet2'] = $ids;
                $out['provider_destination_ids'] = $map;
            }
        }

        $importUrl = $request->query('provider_import_url');
        if (is_string($importUrl) && $importUrl !== '') {
            $len = strlen($importUrl);
            if ($len <= 2048 && filter_var($importUrl, FILTER_VALIDATE_URL)) {
                $out['provider_import_url'] = $importUrl;
            }
        }

        $rawPup = $request->query('provider_url_params');
        if (is_array($rawPup)) {
            $sanitisedPup = [];
            foreach (array_slice($rawPup, 0, 8, true) as $pKey => $inner) {
                if (! is_string($pKey) || ! preg_match('/^[a-z0-9_-]+$/i', $pKey)) {
                    continue;
                }
                if (! is_array($inner)) {
                    continue;
                }
                $innerOut = [];
                foreach (array_slice($inner, 0, 32, true) as $ik => $iv) {
                    if (! is_string($ik) || $ik === '' || ! is_string($iv) || $iv === '') {
                        continue;
                    }
                    if (! preg_match('/^[a-z0-9_]+$/i', $ik)) {
                        continue;
                    }
                    $t = trim($iv);
                    if ($t === '' || strlen($t) > 2048) {
                        continue;
                    }
                    $innerOut[$ik] = $t;
                }
                if ($innerOut !== []) {
                    $sanitisedPup[$pKey] = $innerOut;
                }
            }
            if ($sanitisedPup !== []) {
                $out['provider_url_params'] = $sanitisedPup;
            }
        }

        return $out;
    }

    private static function jet2DdMmYyyyToIso(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m) === 1) {
            $d = (int) $m[1];
            $mon = (int) $m[2];
            $y = (int) $m[3];
            if (! checkdate($mon, $d, $y)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', $y, $mon, $d);
        }

        return null;
    }
}
