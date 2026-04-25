<?php

namespace App\Services\Airports;

use App\Models\Airport;

class AirportLookupService
{
    /** @var array<string, array{lat: float, lng: float}|null> */
    private array $cache = [];

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function coordinatesForIata(string $iataCode): ?array
    {
        $code = strtoupper(trim($iataCode));
        if ($code === '') {
            return null;
        }

        if (array_key_exists($code, $this->cache)) {
            return $this->cache[$code];
        }

        $airport = Airport::query()
            ->select(['latitude', 'longitude'])
            ->where('iata_code', $code)
            ->first();

        if (! $airport) {
            $this->cache[$code] = null;

            return null;
        }

        $this->cache[$code] = [
            'lat' => (float) $airport->latitude,
            'lng' => (float) $airport->longitude,
        ];

        return $this->cache[$code];
    }
}
