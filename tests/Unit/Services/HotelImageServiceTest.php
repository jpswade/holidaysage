<?php

namespace Tests\Unit\Services;

use App\Models\Hotel;
use App\Models\HotelPhoto;
use App\Models\ProviderSource;
use App\Services\Hotels\HotelImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HotelImageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_downloads_and_caches_images_from_hotel_metadata(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://media.jet2.com/*' => Http::response('fake-image-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $provider = ProviderSource::query()->create([
            'key' => 'jet2-test-a',
            'name' => 'Jet2',
            'base_url' => 'https://www.jet2holidays.com',
        ]);

        $hotel = Hotel::query()->create([
            'provider_source_id' => $provider->id,
            'hotel_identity_hash' => 'abc',
            'hotel_name' => 'Test Hotel',
            'hotel_slug' => 'test',
            'destination_name' => 'X',
            'images' => [
                [
                    'url' => 'https://media.jet2.com/is/image/jet2/TEST_01',
                    'source' => 'jet2_json_ld',
                    'position' => 0,
                ],
            ],
        ]);

        app(HotelImageService::class)->syncFromMetadata($hotel);

        $photo = HotelPhoto::query()->where('hotel_id', $hotel->id)->first();
        $this->assertNotNull($photo);
        $this->assertSame(HotelPhoto::STATUS_CACHED, $photo->status);
        $this->assertNotNull($photo->file_path);
        Storage::disk('public')->assertExists($photo->file_path);
    }

    public function test_it_marks_rows_failed_when_http_errors(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://media.jet2.com/*' => Http::response('', 500),
        ]);

        $provider = ProviderSource::query()->create([
            'key' => 'jet2-test-b',
            'name' => 'Jet2',
            'base_url' => 'https://www.jet2holidays.com',
        ]);

        $hotel = Hotel::query()->create([
            'provider_source_id' => $provider->id,
            'hotel_identity_hash' => 'def',
            'hotel_name' => 'Test Hotel 2',
            'hotel_slug' => 'test-2',
            'destination_name' => 'Y',
            'images' => [
                [
                    'url' => 'https://media.jet2.com/is/image/jet2/TEST_FAIL',
                    'source' => 'jet2_json_ld',
                    'position' => 0,
                ],
            ],
        ]);

        app(HotelImageService::class)->syncFromMetadata($hotel);

        $photo = HotelPhoto::query()->where('hotel_id', $hotel->id)->first();
        $this->assertNotNull($photo);
        $this->assertSame(HotelPhoto::STATUS_FAILED, $photo->status);
    }
}
