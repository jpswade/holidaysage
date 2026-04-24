<?php

namespace Database\Seeders;

use App\Enums\ProviderSourceStatus;
use App\Models\ProviderSource;
use Illuminate\Database\Seeder;

class ProviderSourceSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'key' => 'jet2',
                'name' => 'Jet2holidays',
                'base_url' => 'https://www.jet2holidays.com',
                'status' => ProviderSourceStatus::Active,
            ],
            [
                'key' => 'tui',
                'name' => 'TUI',
                'base_url' => 'https://www.tui.co.uk',
                'status' => ProviderSourceStatus::Active,
            ],
        ];

        foreach ($rows as $row) {
            ProviderSource::query()->updateOrCreate(
                ['key' => $row['key']],
                $row
            );
        }
    }
}
