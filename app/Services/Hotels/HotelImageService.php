<?php

namespace App\Services\Hotels;

use App\Models\Hotel;
use App\Models\HotelPhoto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HotelImageService
{
    private const MAX_IMAGES = 40;

    /**
     * Sync DB rows and download binaries from `hotels.images` JSON metadata.
     */
    public function syncFromMetadata(Hotel $hotel): void
    {
        $rows = $this->imageRowsFromMetadata($hotel);
        if ($rows === []) {
            // No image metadata: leave existing `hotel_photos` in place (empty parse should not wipe cache).
            return;
        }

        $hashes = [];
        foreach ($rows as $row) {
            $url = $row['url'];
            $hash = hash('sha256', $url);
            $hashes[$hash] = true;
            $photo = HotelPhoto::query()->firstOrNew([
                'hotel_id' => $hotel->id,
                'external_url_hash' => $hash,
            ]);
            $photo->external_url = $url;
            $photo->position = (int) $row['position'];
            if (! $photo->exists) {
                $photo->status = HotelPhoto::STATUS_PENDING;
            }
            $photo->save();
        }

        $this->pruneOrphanedPhotos($hotel, array_keys($hashes));

        $hotel->unsetRelation('photos');
        $hotel->load('photos');
        foreach ($hotel->photos as $photo) {
            if (! isset($hashes[$photo->external_url_hash])) {
                continue;
            }
            if ($photo->isCached()) {
                continue;
            }
            if (in_array($photo->status, [HotelPhoto::STATUS_PENDING, HotelPhoto::STATUS_FAILED], true)) {
                $this->downloadAndCache($photo);
            }
        }
    }

    /**
     * @return list<array{url: string, position: int}>
     */
    private function imageRowsFromMetadata(Hotel $hotel): array
    {
        $raw = $hotel->images;
        if (! is_array($raw) || $raw === []) {
            return [];
        }
        $out = [];
        $i = 0;
        foreach ($raw as $item) {
            if ($i >= self::MAX_IMAGES) {
                break;
            }
            $url = null;
            if (is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
                $url = $item;
            } elseif (is_array($item) && isset($item['url']) && is_string($item['url']) && filter_var($item['url'], FILTER_VALIDATE_URL)) {
                $url = $item['url'];
            }
            if ($url === null) {
                continue;
            }
            $out[] = [
                'url' => $url,
                'position' => is_array($item) && isset($item['position']) && is_numeric($item['position'])
                    ? (int) $item['position']
                    : $i,
            ];
            $i++;
        }

        return $out;
    }

    private function downloadAndCache(HotelPhoto $photo): void
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
                'Accept' => 'image/*,*/*;q=0.8',
            ])
                ->connectTimeout(5)
                ->timeout(20)
                ->get($photo->external_url);
            if (! $response->successful()) {
                $this->markFailed($photo, 'HTTP '.$response->status());

                return;
            }
            $body = $response->body();
            if ($body === '') {
                $this->markFailed($photo, 'Empty response body');

                return;
            }
            $mime = $response->header('Content-Type');
            $ext = $this->extensionFromContentType($mime, $photo->external_url);
            $relative = 'hotel-photos/'.$photo->hotel_id.'/'.$photo->external_url_hash.'.'.$ext;
            Storage::disk('public')->put($relative, $body, ['visibility' => 'public']);
            $photo->file_path = $relative;
            $photo->status = HotelPhoto::STATUS_CACHED;
            $photo->mime_type = $mime ? explode(';', $mime, 2)[0] : null;
            $photo->file_size = strlen($body);
            $photo->error_message = null;
            $photo->save();
        } catch (ConnectionException $e) {
            $this->markFailed($photo, 'Connection: '.$e->getMessage());
            Log::warning('holidaysage.hotel_image.download_failed', [
                'hotel_photo_id' => $photo->id,
                'url' => $photo->external_url,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->markFailed($photo, $e->getMessage());
            Log::warning('holidaysage.hotel_image.download_failed', [
                'hotel_photo_id' => $photo->id,
                'url' => $photo->external_url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markFailed(HotelPhoto $photo, string $message): void
    {
        $photo->status = HotelPhoto::STATUS_FAILED;
        $photo->error_message = strlen($message) > 2000 ? substr($message, 0, 2000) : $message;
        $photo->save();
    }

    private function extensionFromContentType(?string $contentType, string $url): string
    {
        $ct = $contentType ? strtolower(explode(';', $contentType, 2)[0]) : '';
        if (str_contains($ct, 'png')) {
            return 'png';
        }
        if (str_contains($ct, 'webp')) {
            return 'webp';
        }
        if (str_contains($ct, 'gif')) {
            return 'gif';
        }
        if (str_contains($ct, 'jpeg') || str_contains($ct, 'jpg')) {
            return 'jpg';
        }
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && str_ends_with(strtolower($path), '.png') ? 'png' : 'jpg';
    }

    /**
     * @param  list<string>  $keepHashes
     */
    private function pruneOrphanedPhotos(Hotel $hotel, array $keepHashes): void
    {
        if ($keepHashes === []) {
            HotelPhoto::query()->where('hotel_id', $hotel->id)->delete();

            return;
        }
        $orphans = HotelPhoto::query()
            ->where('hotel_id', $hotel->id)
            ->whereNotIn('external_url_hash', $keepHashes)
            ->get();
        foreach ($orphans as $photo) {
            if ($photo->file_path) {
                Storage::disk('public')->delete($photo->file_path);
            }
            $photo->delete();
        }
    }
}
