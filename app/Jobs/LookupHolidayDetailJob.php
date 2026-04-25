<?php

namespace App\Jobs;

use App\Models\Hotel;
use App\Models\ProviderSource;
use App\Models\SavedHolidaySearchRun;
use App\Services\Hotels\HotelImageService;
use App\Services\Normalisation\HolidayOptionNormaliser;
use App\Services\ProviderImport\ProviderDetailPageParserResolver;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

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
    ): void {
        if ($this->batch() !== null && $this->batch()->cancelled()) {
            return;
        }

        $provider = ProviderSource::query()->findOrFail($this->providerSourceId);
        $candidate = $this->candidate;
        $detailUrl = (string) ($candidate['provider_url'] ?? '');
        if ($detailUrl !== '') {
            $detail = $this->fetchAndParse($provider, $detailUrl, $candidate, $parserResolver);
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
    ): array {
        try {
            $absoluteUrl = str_starts_with($detailUrl, 'http') ? $detailUrl : rtrim($provider->base_url, '/').'/'.ltrim($detailUrl, '/');
            $html = $this->fetchHtmlViaPythonRequests($absoluteUrl);
            if ($html !== null && $html !== '') {
                return $parserResolver->for($provider)->parse($candidate, $html);
            }

            $handler = HandlerStack::create(new StreamHandler);
            $response = Http::retry([], throw: false)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
                    'Accept-Language' => 'en-GB,en-US;q=0.9,en;q=0.8,pt;q=0.7',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->connectTimeout(5)
                ->timeout(8)
                ->withOptions(['handler' => $handler])
                ->get($absoluteUrl);
            if (! $response->successful()) {
                return $parserResolver->for($provider)->parse($candidate, '');
            }

            return $parserResolver->for($provider)->parse($candidate, (string) $response->body());
        } catch (Throwable) {
            return $parserResolver->for($provider)->parse($candidate, '');
        }
    }

    private function fetchHtmlViaPythonRequests(string $url): ?string
    {
        $script = <<<'PY'
import base64
import requests
import sys

url = sys.argv[1]
headers = {
    "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language": "en-GB,en-US;q=0.9,en;q=0.8,pt;q=0.7",
}
r = requests.get(url, headers=headers, timeout=12)
if r.status_code >= 200 and r.status_code < 300:
    print(base64.b64encode(r.content).decode("ascii"))
PY;

        $process = new Process(['python3', '-c', $script, $url]);
        $process->setTimeout(16);
        $process->run();
        if (! $process->isSuccessful()) {
            return null;
        }
        $encoded = trim($process->getOutput());
        if ($encoded === '') {
            return null;
        }
        $decoded = base64_decode($encoded, true);

        return $decoded === false ? null : $decoded;
    }
}
