<?php

namespace Tests\Feature\HolidaySage;

use App\Models\Airport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidaySageImportAirportsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_valid_rows_and_skips_invalid_rows_from_csv(): void
    {
        $path = storage_path('app/reference/test-airports.csv');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $csv = <<<'CSV'
id,ident,type,name,latitude_deg,longitude_deg,iso_country,iata_code,coordinates
1,AGP1,large_airport,Malaga-Costa del Sol Airport,36.6749,-4.4991,ES,AGP,"-4.4991,36.6749"
2,BAD1,small_airport,No IATA Airport,36.0000,-4.0000,ES,,"-4.0000,36.0000"
3,BAD2,small_airport,Missing coords,,,,ES,ZZZ,
CSV;
        file_put_contents($path, $csv);

        $this->artisan('holidaysage:airports:import', ['--path' => $path])
            ->assertExitCode(0);

        $this->assertDatabaseCount('airports', 1);
        $this->assertDatabaseHas('airports', [
            'iata_code' => 'AGP',
            'name' => 'Malaga-Costa del Sol Airport',
        ]);
    }

    public function test_it_is_idempotent_and_updates_existing_airports(): void
    {
        Airport::query()->create([
            'iata_code' => 'AGP',
            'name' => 'Old Name',
            'latitude' => 36.6000,
            'longitude' => -4.5000,
        ]);

        $path = storage_path('app/reference/test-airports-update.csv');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $csv = <<<'CSV'
id,ident,type,name,latitude_deg,longitude_deg,iso_country,iata_code,coordinates
1,AGP1,large_airport,Malaga Updated,36.6749,-4.4991,ES,AGP,"-4.4991,36.6749"
CSV;
        file_put_contents($path, $csv);

        $this->artisan('holidaysage:airports:import', ['--path' => $path])
            ->assertExitCode(0);

        $this->assertDatabaseCount('airports', 1);
        $this->assertDatabaseHas('airports', [
            'iata_code' => 'AGP',
            'name' => 'Malaga Updated',
        ]);
    }
}
