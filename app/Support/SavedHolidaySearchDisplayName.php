<?php

namespace App\Support;

use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Builds a short, human-readable saved-search title from provider + trip parameters.
 */
final class SavedHolidaySearchDisplayName
{
    public static function fromExtracted(array $extracted, ProviderSource $provider): string
    {
        return self::compose(
            self::providerLabel($provider),
            strtoupper((string) ($extracted['departure_airport_code'] ?? 'MAN')),
            self::parseDate($extracted['travel_start_date'] ?? null),
            self::parseDate($extracted['travel_end_date'] ?? null),
            (int) ($extracted['duration_min_nights'] ?? 0),
            (int) ($extracted['duration_max_nights'] ?? 0),
            (int) ($extracted['adults'] ?? 2),
            (int) ($extracted['children'] ?? 0),
            (int) ($extracted['infants'] ?? 0),
            $extracted['destination_preferences'] ?? null,
        );
    }

    public static function fromSavedSearch(SavedHolidaySearch $search, ProviderSource $provider): string
    {
        $prefs = $search->destination_preferences;
        if (! is_array($prefs)) {
            $prefs = null;
        }

        return self::compose(
            self::providerLabel($provider),
            strtoupper((string) $search->departure_airport_code),
            $search->travel_start_date,
            $search->travel_end_date,
            (int) $search->duration_min_nights,
            (int) $search->duration_max_nights,
            (int) $search->adults,
            (int) $search->children,
            (int) $search->infants,
            $prefs,
        );
    }

    /**
     * Names that look auto-generated and should be replaced when a run completes successfully.
     */
    public static function shouldAutoReplaceStoredName(string $name): bool
    {
        $n = trim($name);
        if ($n === '') {
            return true;
        }
        if (preg_match('/^import\b/ui', $n) === 1) {
            return true;
        }
        if (str_contains($n, '(www.')) {
            return true;
        }

        return false;
    }

    /**
     * True when the name is only the generic "{Provider} Search" pattern from URL imports.
     */
    public static function isGenericProviderSearchName(string $name, ProviderSource $provider): bool
    {
        return strcasecmp(trim($name), $provider->name.' Search') === 0;
    }

    private static function providerLabel(ProviderSource $provider): string
    {
        return match ($provider->key) {
            'jet2' => 'Jet2',
            'tui' => 'TUI',
            default => ucfirst($provider->key),
        };
    }

    private static function compose(
        string $providerLabel,
        string $airport,
        mixed $start,
        mixed $end,
        int $minNights,
        int $maxNights,
        int $adults,
        int $children,
        int $infants,
        ?array $destinationPreferences,
    ): string {
        $parts = [$providerLabel, $airport];

        $dest = self::destinationFragment($destinationPreferences);
        if ($dest !== null) {
            $parts[] = $dest;
        }

        $startC = self::parseDate($start);
        $endC = self::parseDate($end);
        if ($startC && $endC && $endC->greaterThan($startC)) {
            $parts[] = $startC->format('j M').'–'.$endC->format('j M Y');
        } elseif ($startC) {
            $parts[] = $startC->format('j M Y');
        }

        if ($minNights > 0) {
            $parts[] = $minNights === $maxNights || $maxNights <= 0
                ? $minNights.' night'.($minNights === 1 ? '' : 's')
                : $minNights.'–'.$maxNights.' nights';
        }

        $party = self::partyFragment($adults, $children, $infants);
        if ($party !== null) {
            $parts[] = $party;
        }

        $out = implode(' · ', array_filter($parts, fn ($p) => $p !== ''));
        if (strlen($out) > 120) {
            return substr($out, 0, 117).'…';
        }

        return $out;
    }

    /**
     * @param  list<mixed>|null  $prefs
     */
    private static function destinationFragment(?array $prefs): ?string
    {
        if ($prefs === null || $prefs === []) {
            return null;
        }
        $labels = [];
        foreach ($prefs as $p) {
            $s = trim((string) $p);
            if ($s === '') {
                continue;
            }
            if (preg_match('/^[0-9_]+$/', $s) === 1) {
                continue;
            }
            $labels[] = $s;
        }
        if ($labels === []) {
            return null;
        }

        return implode(', ', array_slice($labels, 0, 2));
    }

    private static function partyFragment(int $adults, int $children, int $infants): ?string
    {
        if ($adults === 2 && $children === 0 && $infants === 0) {
            return null;
        }
        $bits = [];
        if ($adults > 0) {
            $bits[] = $adults.' adult'.($adults === 1 ? '' : 's');
        }
        if ($children > 0) {
            $bits[] = $children.' child'.($children === 1 ? '' : 'ren');
        }
        if ($infants > 0) {
            $bits[] = $infants.' infant'.($infants === 1 ? '' : 's');
        }

        return $bits === [] ? null : implode(', ', $bits);
    }

    private static function parseDate(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof CarbonInterface) {
            return $value;
        }
        if (is_string($value)) {
            try {
                return Carbon::parse($value)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
