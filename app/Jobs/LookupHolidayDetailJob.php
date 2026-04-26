<?php

namespace App\Jobs;

use App\Models\Hotel;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearchRun;
use App\Services\Hotels\HotelImageService;
use App\Services\Normalisation\HolidayOptionNormaliser;
use App\Services\ProviderImport\Jet2SmartSearchHttpClient;
use App\Services\ProviderImport\ProviderDetailPageParserResolver;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LookupHolidayDetailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $runId,
        public int $searchId,
        public int $providerSourceId,
        public array $candidate,
    ) {}

    public function handle(
        HolidayOptionNormaliser $normaliser,
        ProviderDetailPageParserResolver $parserResolver,
        HotelImageService $hotelImageService,
        Jet2SmartSearchHttpClient $jet2Http,
    ): void {
        if ($this->batch() !== null && $this->batch()->cancelled()) {
            return;
        }

        $provider = ProviderSource::query()->findOrFail($this->providerSourceId);
        $candidate = $this->candidate;
        $detailUrl = (string) ($candidate['provider_url'] ?? '');
        if ($detailUrl !== '') {
            $detail = $this->fetchAndParse($provider, $detailUrl, $candidate, $parserResolver, $jet2Http);
            $candidate = array_merge($candidate, $detail['hotel'] ?? []);
            $detailPackages = $detail['packages'] ?? [];
        } else {
            $detailPackages = [];
        }

        $packagePayloads = $detailPackages === [] ? [$candidate] : array_map(
            fn (array $pkg) => array_merge($candidate, $pkg),
            $detailPackages
        );

        $createdIds = [];
        $hotelIds = [];
        foreach ($packagePayloads as $payload) {
            $signed = $normaliser->normaliseAndSign($payload, $provider);
            $package = $normaliser->upsert($provider, $signed);
            $createdIds[] = $package->id;
            $hotelIds[$package->hotel_id] = true;
        }

        foreach (array_keys($hotelIds) as $hotelId) {
            $hotel = Hotel::query()->find($hotelId);
            if ($hotel !== null) {
                $hotelImageService->syncFromMetadata($hotel);
            }
        }

        DB::transaction(function () use ($createdIds): void {
            $run = SavedHolidaySearchRun::query()->lockForUpdate()->find($this->runId);
            if (! $run) {
                return;
            }
            $ids = $run->imported_holiday_package_ids ?? [];
            $ids = array_values(array_unique([...$ids, ...$createdIds]));
            $run->imported_holiday_package_ids = $ids;
            $run->normalised_record_count = count($ids);
            $run->save();
        });

        Log::info('holidaysage.detail_lookup.done', [
            'run_id' => $this->runId,
            'provider_source_id' => $this->providerSourceId,
            'packages_upserted' => count($createdIds),
            'provider_url' => $detailUrl,
        ]);
    }

    /**
     * @param  array<string,mixed>  $candidate
     * @return array{hotel: array<string,mixed>, packages: list<array<string,mixed>>}
     */
    private function fetchAndParse(
        ProviderSource $provider,
        string $detailUrl,
        array $candidate,
        ProviderDetailPageParserResolver $parserResolver,
        Jet2SmartSearchHttpClient $jet2Http,
    ): array {
        $absoluteUrl = str_starts_with($detailUrl, 'http') ? $detailUrl : rtrim($provider->base_url, '/').'/'.ltrim($detailUrl, '/');
        $html = $this->fetchHotelDetailHtml($provider, $absoluteUrl, $jet2Http);
        if ($html !== null && $html !== '') {
            return $parserResolver->for($provider)->parse($candidate, $html);
        }

        return $parserResolver->for($provider)->parse($candidate, '');
    }

    private function fetchHotelDetailHtml(ProviderSource $provider, string $absoluteUrl, Jet2SmartSearchHttpClient $jet2Http): ?string
    {
        if ($provider->key === 'jet2') {
            $response = $jet2Http->get($absoluteUrl, isApi: false);
            if (! $response->successful()) {
                return null;
            }
            $body = (string) $response->body();

            return $body !== '' ? $body : null;
        }

        $response = Http::retry([], throw: false)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
                'Accept-Language' => 'en-GB,en-US;q=0.9,en;q=0.8,pt;q=0.7',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->connectTimeout(5)
            ->timeout(20)
            ->get($absoluteUrl);
        if (! $response->successful()) {
            return null;
        }
        $body = (string) $response->body();

        return $body !== '' ? $body : null;
    }
}
