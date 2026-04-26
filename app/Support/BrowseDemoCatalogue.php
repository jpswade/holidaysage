<?php

namespace App\Support;

class BrowseDemoCatalogue
{
    /**
     * Illustrative example holidays aligned with the v0 marketing page (static content, not live results).
     *
     * @return list<array<string, mixed>>
     */
    public static function items(): array
    {
        $rows = self::rawRows();
        $out = [];
        foreach ($rows as $i => $row) {
            $row['rank'] = $i + 1;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  'all'|'jet2'|'tui'  $provider
     * @param  'all'|'all_inclusive'|'half_board'|'bed_breakfast'|'self_catering'  $board
     * @return list<array<string, mixed>>
     */
    public static function filter(array $items, string $provider, string $board, string $q): array
    {
        $provider = strtolower($provider) ?: 'all';
        $board = $board !== '' ? $board : 'all';
        $q = strtolower(trim($q));

        $out = array_values(array_filter(
            $items,
            function (array $h) use ($provider, $board, $q): bool {
                if ($provider !== 'all' && strtolower((string) ($h['provider_key'] ?? '')) !== $provider) {
                    return false;
                }
                if ($board !== 'all' && (string) ($h['board_key'] ?? 'other') !== $board) {
                    return false;
                }
                if ($q !== '') {
                    $hay = strtolower((string) $h['hotel'].' '.(string) $h['destination'].' '.(string) $h['board']);
                    if (! str_contains($hay, $q)) {
                        return false;
                    }
                }

                return true;
            }
        ));

        foreach ($out as $i => $row) {
            $out[$i]['display_rank'] = $i + 1;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rawRows(): array
    {
        $q = static fn (array $destinationPreferences, array $featurePreferences = []): array => array_filter([
            'destination_preferences' => $destinationPreferences,
            'feature_preferences' => $featurePreferences !== [] ? $featurePreferences : null,
        ]);

        return [
            self::row(
                'tui',
                'Ikos Dassia',
                'Dassia, Corfu',
                9.5,
                '3h 10m',
                '20 min',
                'all_inclusive',
                'All Inclusive',
                ['Infinite Lifestyle', 'Michelin dining', 'Kids clubs', 'Beach butler'],
                'The gold standard of all-inclusive resorts. Michelin-starred dining, premium drinks, and exceptional service throughout.',
                2456,
                1228,
                [],
                $q(['Corfu', 'Dassia'], ['all_inclusive', 'spa_wellness']),
            ),
            self::row(
                'jet2',
                'Secrets Lanzarote Resort & Spa',
                'Puerto Calero, Lanzarote',
                9.4,
                '4h 15m',
                '20 min',
                'all_inclusive',
                'All Inclusive',
                ['Adults Only', '50m from beach', 'Spa included', '5-star'],
                'Exceptional value for a luxury adults-only resort. The beachfront location and included spa treatments make this a standout choice for couples.',
                1847,
                924,
                [],
                $q(['Lanzarote', 'Puerto Calero'], ['adults_only', 'spa_wellness', 'near_beach']),
            ),
            self::row(
                'jet2',
                'Regnum Carya Golf & Spa',
                'Belek, Turkey',
                9.4,
                '4h 00m',
                '30 min',
                'all_inclusive',
                'All Inclusive',
                ['Championship golf', 'Huge pools', 'Kids paradise', 'Premium brands'],
                "One of Turkey's finest resorts. Exceptional facilities, premium all-inclusive with top-shelf drinks included.",
                1876,
                938,
                [],
                $q(['Belek', 'Turkey'], ['all_inclusive', 'family_friendly', 'swimming_pool']),
            ),
            self::row(
                'tui',
                'Pine Cliffs Resort',
                'Albufeira, Algarve',
                9.3,
                '2h 50m',
                '35 min',
                'bed_breakfast',
                'Bed & Breakfast',
                ['Cliff-top location', 'Golf course', 'Kids academy', 'Private beach'],
                'Stunning clifftop resort with world-class facilities. The private beach elevator and golf course make this truly special.',
                2134,
                1067,
                [],
                $q(['Albufeira', 'Algarve'], ['near_beach', 'swimming_pool']),
            ),
            self::row(
                'jet2',
                'Iberostar Playa de Muro',
                'Playa de Muro, Mallorca',
                9.2,
                '2h 20m',
                '45 min',
                'all_inclusive',
                'All Inclusive',
                ['Beachfront', 'Kids Club', 'Spa', '4 restaurants'],
                "A magical experience for families. Direct beach access to one of Mallorca's best beaches with excellent facilities.",
                1876,
                938,
                [],
                $q(['Mallorca', 'Playa de Muro'], ['family_friendly', 'kids_club', 'near_beach']),
            ),
            self::row(
                'jet2',
                'Iberostar Selection Anthelia',
                'Costa Adeje, Tenerife',
                9.1,
                '4h 20m',
                '15 min',
                'half_board',
                'Half Board',
                ['Walkable to town', 'Infinity pool', 'Sea views', 'Premium dining'],
                'Outstanding location with easy access to local restaurants and shops. The infinity pool with ocean views is spectacular at sunset.',
                1689,
                845,
                [],
                $q(['Tenerife', 'Costa Adeje'], ['walkable', 'swimming_pool']),
            ),
            self::row(
                'tui',
                'Lindos Blu',
                'Lindos, Rhodes',
                9.1,
                '3h 55m',
                '50 min',
                'half_board',
                'Half Board',
                ['Adults Only', 'Infinity pool', 'Lindos views', 'Boutique feel'],
                'Romantic adults-only hotel with breathtaking views of Lindos. The infinity pool overlooking the bay is Instagram-worthy.',
                1789,
                895,
                ['Longer transfer from airport'],
                $q(['Rhodes', 'Lindos'], ['adults_only', 'swimming_pool', 'quiet_relaxing']),
            ),
            self::row(
                'tui',
                'Don Carlos Resort & Spa',
                'Marbella, Costa del Sol',
                9.0,
                '2h 45m',
                '25 min',
                'half_board',
                'Half Board',
                ['Private beach', 'Spa', 'Golf nearby', 'Fine dining'],
                'Luxury beachfront resort in prestigious Marbella. The Thai spa and private beach club are exceptional.',
                1945,
                973,
                [],
                $q(['Marbella', 'Costa del Sol'], ['spa_wellness', 'near_beach', 'swimming_pool']),
            ),
            self::row(
                'tui',
                'Amare Beach Hotel Ibiza',
                'San Antonio, Ibiza',
                8.9,
                '2h 15m',
                '20 min',
                'half_board',
                'Half Board',
                ['Adults Only', 'Rooftop pool', 'DJ sessions', 'Sunset views'],
                'Stylish adults-only hotel with a vibrant atmosphere. Perfect balance of relaxation and nightlife access.',
                1654,
                827,
                [],
                $q(['Ibiza', 'San Antonio'], ['adults_only', 'near_nightlife', 'near_beach']),
            ),
            self::row(
                'jet2',
                'Olympic Lagoon Paphos',
                'Paphos, Cyprus',
                8.9,
                '4h 30m',
                '20 min',
                'all_inclusive',
                'All Inclusive',
                ['Adults section', 'Splash park', 'A la carte dining', 'Entertainment'],
                'Versatile resort with separate adults area. Perfect compromise for families wanting quality time together and apart.',
                1654,
                827,
                [],
                $q(['Paphos', 'Cyprus'], ['family_friendly', 'all_inclusive', 'kids_club']),
            ),
            self::row(
                'tui',
                'ClubHotel Riu Gran Canaria',
                'Maspalomas, Gran Canaria',
                8.8,
                '4h 30m',
                '35 min',
                'all_inclusive',
                'All Inclusive',
                ['Kids Club', 'Direct beach access', '3 pools', 'Evening entertainment'],
                'Perfect for families seeking reliable quality. The extensive kids facilities and beachfront pools keep everyone happy.',
                1456,
                728,
                ['Popular dates - limited availability'],
                $q(['Gran Canaria', 'Maspalomas'], ['family_friendly', 'kids_club', 'all_inclusive']),
            ),
            self::row(
                'tui',
                'Liberty Hotels Lykia',
                'Ölüdeniz, Turkey',
                8.8,
                '4h 15m',
                '65 min',
                'all_inclusive',
                'All Inclusive',
                ['Blue Lagoon nearby', 'Private beach', 'Water sports', 'Family friendly'],
                'Excellent resort near the famous Blue Lagoon. Great value with comprehensive facilities and a beautiful setting.',
                1234,
                617,
                ['Longer transfer but worth it'],
                $q(['Turkey', 'Oludeniz'], ['family_friendly', 'all_inclusive', 'near_beach']),
            ),
            self::row(
                'jet2',
                'Tivoli Marina Vilamoura',
                'Vilamoura, Algarve',
                8.7,
                '2h 50m',
                '25 min',
                'bed_breakfast',
                'Bed & Breakfast',
                ['Marina views', 'Casino nearby', 'Rooftop bar', 'Central location'],
                'Prime marina location with excellent restaurants nearby. Perfect for couples who want a mix of beach and nightlife.',
                1567,
                784,
                [],
                $q(['Vilamoura', 'Algarve'], ['walkable', 'near_nightlife', 'swimming_pool']),
            ),
            self::row(
                'jet2',
                'Mitsis Rinela Beach',
                'Kokkini Hani, Crete',
                8.6,
                '3h 45m',
                '15 min',
                'all_inclusive',
                'All Inclusive',
                ['Private beach', 'Aqua park', 'Animation team', '5 restaurants'],
                'Great value Greek all-inclusive with excellent facilities. The aqua park and animation team are perfect for families.',
                1345,
                673,
                [],
                $q(['Crete', 'Kokkini Hani'], ['family_friendly', 'all_inclusive', 'kids_club']),
            ),
            self::row(
                'jet2',
                'Insotel Punta Prima',
                'Punta Prima, Menorca',
                8.5,
                '2h 10m',
                '30 min',
                'all_inclusive',
                'All Inclusive',
                ['Family friendly', 'Beach access', '2 pools', 'Mini club'],
                'Excellent value all-inclusive on quieter Menorca. Great for families wanting a more relaxed Balearic experience.',
                1234,
                617,
                [],
                $q(['Menorca', 'Punta Prima'], ['family_friendly', 'all_inclusive', 'near_beach']),
            ),
            self::row(
                'tui',
                'Constantinou Bros Athena Beach',
                'Paphos, Cyprus',
                8.5,
                '4h 30m',
                '15 min',
                'half_board',
                'Half Board',
                ['Beachfront', 'Spa', 'Tennis', 'Elegant dining'],
                'Classic beachfront hotel with excellent service. Popular with couples and families seeking reliable quality.',
                1345,
                673,
                [],
                $q(['Paphos', 'Cyprus'], ['near_beach', 'swimming_pool', 'spa_wellness']),
            ),
            self::row(
                'jet2',
                'Holiday World Resort',
                'Benalmádena, Costa del Sol',
                8.4,
                '2h 45m',
                '15 min',
                'all_inclusive',
                'All Inclusive',
                ['Waterpark', 'Kids entertainment', 'Multiple pools', 'Theme park nearby'],
                'Budget-friendly family resort with incredible entertainment. Adjacent theme park access is a huge plus for kids.',
                1098,
                275,
                ['Can be busy in peak season'],
                $q(['Benalmadena', 'Costa del Sol'], ['family_friendly', 'all_inclusive', 'kids_club']),
            ),
            self::row(
                'tui',
                'HD Parque Cristobal',
                'Playa del Inglés, Fuerteventura',
                8.2,
                '4h 10m',
                '25 min',
                'self_catering',
                'Self Catering',
                ['Family apartments', '300m from beach', 'Waterpark', 'Mini golf'],
                'Great budget option for families. The apartment-style accommodation gives you flexibility, and kids love the waterpark.',
                1124,
                562,
                ['Self catering only', '5 min walk to beach'],
                $q(['Fuerteventura', 'Playa del Ingles'], ['family_friendly', 'near_beach', 'swimming_pool']),
            ),
        ];
    }

    /**
     * @param  list<string>  $chips
     * @param  list<string>  $caveats
     * @return array<string, mixed>
     */
    private static function row(
        string $providerKey,
        string $hotel,
        string $destination,
        float $score,
        string $flight,
        string $transfer,
        string $boardKey,
        string $board,
        array $chips,
        string $summary,
        int $price,
        int $perPerson,
        array $caveats,
        array $createQuery,
    ): array {
        return [
            'provider_key' => $providerKey,
            'provider' => strtoupper($providerKey) === 'JET2' ? 'Jet2' : 'TUI',
            'hotel' => $hotel,
            'destination' => $destination,
            'score' => $score,
            'flight' => $flight,
            'transfer' => $transfer,
            'board_key' => $boardKey,
            'board' => $board,
            'chips' => $chips,
            'summary' => $summary,
            'price' => $price,
            'per_person' => $perPerson,
            'caveats' => $caveats,
            'create_query' => $createQuery,
        ];
    }
}
