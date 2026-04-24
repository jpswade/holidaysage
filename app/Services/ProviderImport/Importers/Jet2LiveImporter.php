<?php

namespace App\Services\ProviderImport\Importers;

use App\Contracts\ProviderHttpImporter;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearch;
use App\Services\ProviderImport\Importers\Concerns\ExtractsEmbeddedJson;
use App\Services\ProviderImport\ProviderImportResult;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Jet2LiveImporter implements ProviderHttpImporter
{
    use ExtractsEmbeddedJson;

    public function providerKey(): string
    {
        return 'jet2';
    }

    public function import(string $url, SavedHolidaySearch $search, ProviderSource $provider): ProviderImportResult
    {
        $response = Http::retry([200, 500, 1000], throw: false)
            ->timeout(30)
            ->accept('text/html,application/json')
            ->get($url);

        if ($response->status() === 0) {
            throw new ConnectionException('Jet2 request did not return a response.');
        }
        if (! $response->successful()) {
            throw new \RuntimeException('Jet2 HTTP '.$response->status().' for '.$url);
        }

        $body = (string) $response->body();
        $candidates = $this->candidatesFromBody($body, $search, $provider, $url);
        if ($candidates === []) {
            throw new \RuntimeException('Jet2 payload did not contain parsable holiday candidates.');
        }

        return new ProviderImportResult(
            responseStatus: $response->status(),
            rawBody: $body,
            candidates: $candidates,
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function candidatesFromBody(string $body, SavedHolidaySearch $search, ProviderSource $provider, string $url): array
    {
        $docs = $this->extractJsonDocuments($body);
        $candidates = [];

        foreach ($docs as $doc) {
            $name = $doc['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }
            $reviewScore = $doc['aggregateRating']['ratingValue'] ?? null;
            $reviewCount = $doc['aggregateRating']['reviewCount'] ?? null;
            $price = $doc['offers']['price'] ?? null;
            $currency = $doc['offers']['priceCurrency'] ?? 'GBP';

            $dep = $search->travel_start_date ? $search->travel_start_date->toDateString() : now()->addMonths(2)->toDateString();
            $nights = max(1, (int) $search->duration_min_nights);
            $ret = Carbon::parse($dep)->addDays($nights)->toDateString();

            $slug = Str::slug($name) ?: Str::random(12);
            $candidates[] = [
                'provider_option_id' => 'jet2-'.substr(sha1($url.'|'.$name), 0, 12),
                'provider_hotel_id' => null,
                'provider_url' => is_string($doc['url'] ?? null) ? $doc['url'] : $url,
                'hotel_name' => $name,
                'hotel_slug' => $slug,
                'resort_name' => null,
                'destination_name' => $search->destination_preferences[0] ?? 'Unknown destination',
                'destination_country' => 'Unknown',
                'airport_code' => $search->departure_airport_code,
                'departure_date' => $dep,
                'return_date' => $ret,
                'nights' => $nights,
                'adults' => (int) $search->adults,
                'children' => (int) $search->children,
                'infants' => (int) $search->infants,
                'board_type' => null,
                'price_total' => is_numeric($price) ? (float) $price : 0.0,
                'price_per_person' => null,
                'currency' => is_string($currency) ? strtoupper($currency) : 'GBP',
                'flight_outbound_duration_minutes' => null,
                'flight_inbound_duration_minutes' => null,
                'transfer_minutes' => null,
                'distance_to_beach_meters' => null,
                'distance_to_centre_meters' => null,
                'star_rating' => null,
                'review_score' => is_numeric($reviewScore) ? (float) $reviewScore : null,
                'review_count' => is_numeric($reviewCount) ? (int) $reviewCount : null,
                'is_family_friendly' => (bool) ($search->children > 0),
                'has_kids_club' => false,
                'has_waterpark' => false,
                'has_family_rooms' => false,
                'latitude' => null,
                'longitude' => null,
                'raw_attributes' => [
                    'provider' => $provider->key,
                    'source' => 'live_http_ld_json',
                ],
            ];
        }

        return $candidates;
    }
}
