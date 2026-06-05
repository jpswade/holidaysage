<?php

namespace App\Services\Normalisation;

use App\Models\HolidayPackage;
use App\Models\Hotel;
use App\Models\ProviderSource;
use Illuminate\Support\Str;

class HolidayOptionNormaliser
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normaliseAndSign(array $raw, ProviderSource $provider): array
    {
        $data = $this->applyBoardAliases($raw);
        if (empty($data['provider_url']) || ! is_string($data['provider_url'])) {
            $data['provider_url'] = $provider->base_url;
        }
        if (empty($data['currency'])) {
            $data['currency'] = 'GBP';
        }

        return $data;
    }

    public function upsert(ProviderSource $provider, array $normalised, ?\DateTimeInterface $now = null): HolidayPackage
    {
        $now = $now ?? now();
        [$hotelData, $packageData] = $this->splitHotelAndPackageData($normalised);
        $hotelData['provider_source_id'] = $provider->id;
        $packageData['provider_source_id'] = $provider->id;

        $hotelIdentityHash = $this->buildHotelIdentityHash($provider, $hotelData);
        $hotelData['hotel_identity_hash'] = $hotelIdentityHash;
        $hotel = Hotel::query()->where([
            'provider_source_id' => $provider->id,
            'hotel_identity_hash' => $hotelIdentityHash,
        ])->first();
        if (! $hotel) {
            $hotelData['first_seen_at'] = $now;
        }
        $hotelData['last_seen_at'] = $now;

        // Assign or reuse a canonical_property_slug so the same real-world property is
        // represented by one slug across providers. Match rule: same hotel_slug AND
        // the same destination_name + destination_country (case-insensitive), checked
        // against pre-existing rows from any provider.
        if (empty($hotelData['canonical_property_slug'])) {
            $hotelData['canonical_property_slug'] = $this->resolveCanonicalPropertySlug($hotelData, $hotel);
        }

        $hotelFill = array_intersect_key(
            $hotelData,
            array_flip((new Hotel)->getFillable())
        );
        if (! $hotel) {
            $hotel = Hotel::query()->create($hotelFill);
        } else {
            $hotel->fill($hotelFill);
            $hotel->save();
        }

        $packageData['hotel_id'] = $hotel->id;
        $packageSignature = $this->buildPackageSignature($provider, $hotel, $packageData);
        $packageData['signature_hash'] = $packageSignature;
        $package = HolidayPackage::query()->where([
            'provider_source_id' => $provider->id,
            'signature_hash' => $packageSignature,
        ])->first();
        if (! $package) {
            $packageData['first_seen_at'] = $now;
        }
        $packageData['last_seen_at'] = $now;
        $packageFill = array_intersect_key(
            $packageData,
            array_flip((new HolidayPackage)->getFillable())
        );
        if (! $package) {
            return HolidayPackage::query()->create($packageFill);
        }
        $package->fill($packageFill);
        $package->save();

        return $package;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildHotelIdentityHash(ProviderSource $provider, array $data): string
    {
        $providerHotelId = trim((string) ($data['provider_hotel_id'] ?? ''));
        if ($providerHotelId !== '') {
            return hash('sha256', $provider->id.'|'.$providerHotelId);
        }

        $parts = [
            (string) $provider->id,
            Str::lower(trim((string) ($data['hotel_name'] ?? ''))),
            Str::lower(trim((string) ($data['destination_name'] ?? ''))),
            Str::lower(trim((string) ($data['destination_country'] ?? ''))),
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Resolve the canonical_property_slug for an incoming hotel record so the same
     * real-world property is shared across providers. Falls back to the row's hotel_slug.
     *
     * @param  array<string, mixed>  $hotelData
     */
    private function resolveCanonicalPropertySlug(array $hotelData, ?Hotel $existing): string
    {
        if ($existing !== null && is_string($existing->canonical_property_slug) && trim((string) $existing->canonical_property_slug) !== '') {
            return (string) $existing->canonical_property_slug;
        }

        $hotelSlug = trim((string) ($hotelData['hotel_slug'] ?? ''));
        if ($hotelSlug === '') {
            $hotelSlug = Str::slug((string) ($hotelData['hotel_name'] ?? 'hotel'));
        }
        if ($hotelSlug === '') {
            $hotelSlug = 'hotel-'.Str::random(8);
        }

        $destination = strtolower(trim((string) ($hotelData['destination_name'] ?? '')));
        $country = strtolower(trim((string) ($hotelData['destination_country'] ?? '')));

        $match = Hotel::query()
            ->whereRaw('LOWER(hotel_slug) = ?', [strtolower($hotelSlug)])
            ->whereNotNull('canonical_property_slug')
            ->when($destination !== '', function ($q) use ($destination): void {
                $q->whereRaw('LOWER(destination_name) = ?', [$destination]);
            })
            ->when($country !== '', function ($q) use ($country): void {
                $q->whereRaw('LOWER(COALESCE(destination_country, "")) = ?', [$country]);
            })
            ->orderBy('id')
            ->first();

        if ($match !== null) {
            return (string) $match->canonical_property_slug;
        }

        return $hotelSlug;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildPackageSignature(ProviderSource $provider, Hotel $hotel, array $data): string
    {
        $parts = [
            (string) $provider->id,
            (string) $hotel->id,
            (string) ($data['departure_date'] ?? ''),
            (string) ($data['nights'] ?? ''),
            strtoupper((string) ($data['airport_code'] ?? '')),
            (string) ($data['board_type'] ?? ''),
            (string) ($data['adults'] ?? '').'-'.(string) ($data['children'] ?? '').'-'.(string) ($data['infants'] ?? ''),
        ];
        $payload = implode('|', $parts);

        return hash('sha256', $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyBoardAliases(array $data): array
    {
        if (! isset($data['board_type']) || ! is_string($data['board_type'])) {
            return $data;
        }
        $b = strtoupper(trim($data['board_type']));
        if (in_array($b, ['AI', 'ALL INCLUSIVE', 'ALL-IN', 'ALL_INCLUSIVE', 'AL'], true) || str_contains($b, 'ALL INC')) {
            $data['board_type'] = 'all_inclusive';
        } elseif (in_array($b, ['HB', 'HALF BOARD', 'HALF_BOARD', 'H/B'], true) || str_contains($b, 'HALF BO')) {
            $data['board_type'] = 'half_board';
        } elseif (in_array($b, ['SC', 'SELF CATERING', 'SELF_CATERING', 'S/C'], true) || str_contains($b, 'SELF CAT')) {
            $data['board_type'] = 'self_catering';
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $normalised
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function splitHotelAndPackageData(array $normalised): array
    {
        $hotelKeys = [
            'provider_hotel_id',
            'hotel_name',
            'hotel_slug',
            'canonical_property_slug',
            'resort_name',
            'destination_name',
            'destination_country',
            'star_rating',
            'review_score',
            'review_count',
            'is_family_friendly',
            'has_kids_club',
            'kids_club_age_min',
            'has_waterpark',
            'has_family_rooms',
            'play_area',
            'evening_entertainment',
            'kids_disco',
            'gym',
            'spa',
            'adults_only_area',
            'promenade',
            'near_shops',
            'distance_to_shops_meters',
            'cafes_bars',
            'distance_to_cafes_bars_meters',
            'harbour',
            'has_lift',
            'ground_floor_available',
            'accessibility_issues',
            'steps_count',
            'accessibility_notes',
            'cots_available',
            'introduction_snippet',
            'style_keywords',
            'distance_to_beach_meters',
            'distance_to_centre_meters',
            'distance_to_airport_km',
            'rooms_count',
            'blocks_count',
            'floors_count',
            'restaurants_count',
            'bars_count',
            'pools_count',
            'sports_leisure_count',
            'latitude',
            'longitude',
        ];
        $packageKeys = [
            'provider_option_id',
            'provider_url',
            'airport_code',
            'departure_date',
            'return_date',
            'nights',
            'adults',
            'children',
            'infants',
            'board_type',
            'board_recommended',
            'price_total',
            'price_per_person',
            'currency',
            'flight_outbound_duration_minutes',
            'flight_inbound_duration_minutes',
            'transfer_minutes',
            'flight_time_hours_est',
            'transfer_type',
            'outbound_flight_time_text',
            'inbound_flight_time_text',
            'local_beer_price',
            'three_course_meal_for_two_price',
        ];

        $hotelData = [];
        foreach ($hotelKeys as $key) {
            if (array_key_exists($key, $normalised)) {
                $hotelData[$key] = $normalised[$key];
            }
        }
        if (array_key_exists('images', $normalised) && is_array($normalised['images']) && $normalised['images'] !== []) {
            $hotelData['images'] = $normalised['images'];
        }
        $packageData = [];
        foreach ($packageKeys as $key) {
            if (array_key_exists($key, $normalised)) {
                $packageData[$key] = $normalised[$key];
            }
        }
        $raw = is_array($normalised['raw_attributes'] ?? null) ? $normalised['raw_attributes'] : [];
        $hotelRaw = [];
        $packageRaw = [];
        foreach ($raw as $key => $value) {
            if (in_array((string) $key, ['property', 'features', 'keySellingPoints', 'distance_to_airport_km', 'introduction_text'], true)) {
                $hotelRaw[$key] = $value;

                continue;
            }
            if (in_array((string) $key, ['accommodation_options', 'outbound_flight', 'inbound_flight'], true)) {
                $packageRaw[$key] = $value;

                continue;
            }
            $hotelRaw[$key] = $value;
            $packageRaw[$key] = $value;
        }
        if ($hotelRaw !== []) {
            $hotelData['raw_attributes'] = ['hotel_extra' => $hotelRaw];
        }
        $packageData['raw_attributes'] = $packageRaw === [] ? null : ['package_extra' => $packageRaw];
        if (($packageData['board_type'] ?? null) === null) {
            $packageData['board_type'] = '';
        }

        return [$hotelData, $packageData];
    }
}
