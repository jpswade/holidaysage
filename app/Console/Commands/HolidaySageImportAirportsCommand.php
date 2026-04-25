<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class HolidaySageImportAirportsCommand extends Command
{
    private const DEFAULT_AIRPORT_CODES_URL = 'https://raw.githubusercontent.com/datasets/airport-codes/master/data/airport-codes.csv';

    protected $signature = 'holidaysage:airports:import
                            {--path= : Local CSV path (defaults to storage/app/reference/airport-codes.csv)}
                            {--url= : Remote CSV URL (defaults to datasets/airport-codes CSV)}
                            {--refresh : Re-download CSV even when local file exists}';

    protected $description = 'Download and import IATA airport coordinates into the airports table.';

    public function handle(): int
    {
        $path = (string) ($this->option('path') ?: storage_path('app/reference/airport-codes.csv'));
        $url = (string) ($this->option('url') ?: self::DEFAULT_AIRPORT_CODES_URL);

        if ($path === '') {
            $this->error('A valid local CSV path is required.');

            return self::FAILURE;
        }

        $shouldDownload = (bool) $this->option('refresh') || ! is_file($path);
        if ($shouldDownload) {
            $dir = dirname($path);
            if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
                $this->error('Failed to create directory: '.$dir);

                return self::FAILURE;
            }

            $response = Http::retry([300, 1000, 2000], throw: false)->get($url);
            if (! $response->ok()) {
                $this->error(sprintf('Failed to download airport data from %s (HTTP %d).', $url, $response->status()));

                return self::FAILURE;
            }

            if (file_put_contents($path, $response->body()) === false) {
                $this->error('Failed to write downloaded airport data to: '.$path);

                return self::FAILURE;
            }

            $this->line('Downloaded airport CSV to: '.$path);
        }

        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Airport CSV is not readable: '.$path);

            return self::FAILURE;
        }

        [$inserted, $updated, $skipped] = $this->importCsv($path);

        $this->info(sprintf(
            'Airport import complete: inserted=%d, updated=%d, skipped=%d',
            $inserted,
            $updated,
            $skipped
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{int, int, int}
     */
    private function importCsv(string $path): array
    {
        $file = new \SplFileObject($path, 'rb');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $header = $file->fgetcsv();
        if (! is_array($header) || $header === [null] || $header === false) {
            return [0, 0, 0];
        }

        $headerMap = [];
        foreach ($header as $index => $name) {
            if (! is_string($name)) {
                continue;
            }
            $headerMap[strtolower(trim($name))] = (int) $index;
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null] || $row === false) {
                continue;
            }

            $iata = strtoupper(trim((string) $this->csvValue($row, $headerMap, 'iata_code')));
            if (! preg_match('/^[A-Z]{3}$/', $iata)) {
                $skipped++;
                continue;
            }

            [$lat, $lng] = $this->extractCoordinates($row, $headerMap);
            if ($lat === null || $lng === null) {
                $skipped++;
                continue;
            }

            $airport = Airport::query()->firstOrNew(['iata_code' => $iata]);
            $exists = $airport->exists;
            $airport->fill([
                'name' => $this->normaliseNullableString((string) $this->csvValue($row, $headerMap, 'name')),
                'latitude' => $lat,
                'longitude' => $lng,
            ]);

            if (! $exists) {
                $airport->save();
                $inserted++;
                continue;
            }

            if ($airport->isDirty()) {
                $airport->save();
                $updated++;
                continue;
            }

            $skipped++;
        }

        return [$inserted, $updated, $skipped];
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $headerMap
     * @return array{?float, ?float}
     */
    private function extractCoordinates(array $row, array $headerMap): array
    {
        $lat = $this->toFloatOrNull($this->csvValue($row, $headerMap, 'latitude_deg'));
        $lng = $this->toFloatOrNull($this->csvValue($row, $headerMap, 'longitude_deg'));

        if ($lat !== null && $lng !== null) {
            return [$lat, $lng];
        }

        $coordinates = (string) $this->csvValue($row, $headerMap, 'coordinates');
        if ($coordinates === '' || ! str_contains($coordinates, ',')) {
            return [null, null];
        }

        [$coordLng, $coordLat] = array_map('trim', explode(',', $coordinates, 2));

        return [$this->toFloatOrNull($coordLat), $this->toFloatOrNull($coordLng)];
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $headerMap
     */
    private function csvValue(array $row, array $headerMap, string $column): mixed
    {
        $index = $headerMap[strtolower($column)] ?? null;
        if ($index === null) {
            return null;
        }

        return $row[$index] ?? null;
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function normaliseNullableString(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
