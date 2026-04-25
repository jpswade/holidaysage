---
name: Hotel images import layer
overview: "Move holiday image handling out of `ResultCardViewModel` and into the import/detail pipeline: parse and normalise images from provider HTML, persist a structured list on `Hotel`, download/cache binaries RoadSage-style, and let the UI read a stable public URL (cached first, remote fallback)."
todos:
  - id: schema-images-column
    content: Add `hotels.images` JSON + Hotel model fillable/cast/accessor for primary remote URL
    status: completed
  - id: schema-hotel-photos
    content: Add `hotel_photos` (or equivalent) table + model + `Hotel` relation; fields aligned with RoadSage `Photo` (external_url, hash, file_path, status, position)
    status: completed
  - id: parser-extract-images
    content: Extract/normalise `image` (and optional gallery) in Jet2DetailPageParser with fixture tests
    status: completed
  - id: normaliser-persist
    content: Include `images` in HolidayOptionNormaliser hotel keys + empty-parse retention rule for JSON metadata
    status: completed
  - id: photo-service-sync
    content: Implement `HotelImageService` (or similar) to fetch URLs, store on disk (public/linked), upsert `hotel_photos`; invoke from `LookupHolidayDetailJob` or queued follow-up job after hotel upsert
    status: completed
  - id: viewmodel-thin
    content: Strip ResultCardViewModel path logic; `imageUrl` = first successful cached public URL, else first remote URL from `images`; update unit tests
    status: completed
isProject: false
---

# Hotel images: import-time modelling (RoadSage pattern)

## Problem

[`ResultCardViewModel`](app/ViewModels/ResultCardViewModel.php) currently derives card imagery by walking `raw_attributes` paths. That belongs in the **import/detail** layer: the parser should produce a normalised image list, persistence should store it explicitly, and presenters should only read stable fields.

RoadSage’s Rightmove flow does this in the import service: it **extracts** images from provider payload ([`Service::extractImages`](file:///Users/wade/Sites/road/src/Listings/Services/Service.php)), **normalises** shape (sequential list of objects with `url`, optional `caption`, etc.), **persists** alongside the listing (`meta['images']` plus photo sync). The UI does not rediscover URLs from arbitrary trees.

## Target shape (HolidaySage)

- **Domain**: images are **hotel-scoped** (one hotel, many packages). Store on [`Hotel`](app/Models/Hotel.php), not on [`HolidayPackage`](app/Models/HolidayPackage.php) (unless you later need package-specific room photos).
- **Source metadata (JSON)**: add nullable JSON `images` on `hotels`, cast to array. Each element should be a small, explicit object, e.g. `{ "url": string, "source": "jet2_json_ld" | "jet2_gallery", "position": int }` — keep it provider-agnostic enough for future parsers. This row remains the **canonical list of provider URLs** for re-sync and debugging.
- **Binary cache (DB + disk)**: add a `hotel_photos` (or `hotel_images`) table modelled on RoadSage’s [`Photo`](file:///Users/wade/Sites/road/src/Listings/Models/Photo.php): at minimum `hotel_id`, `position`, `external_url`, `external_url_hash` (SHA-256 for lookup), `file_path`, `status` (`pending` / `cached` / `failed`), `mime_type`, optional `width`/`height`, `error_message`. Use [`PhotoService::getPropertyPhotos`](file:///Users/wade/Sites/road/src/Listings/Services/PhotoService.php) as a behavioural reference: iterate normalised `images` data, `firstOrCreate` by `(hotel_id, hash, type)`, download to the configured disk, set `file_path` when successful.
- **Primary URL for UI**: `Hotel` accessor e.g. `primaryImagePublicUrl()`: **prefer** the first `hotel_photos` row with `status=cached` and existing file, resolved via `Storage::url` (or a named route that mirrors RoadSage’s `servePhoto` if you keep files non-public); **else** fall back to `images[0].url`. Avoid duplicating the same URL in multiple columns; JSON holds remote URLs, rows hold cache state and local paths.
- **Concurrency / failures**: do not block the detail import forever — either run downloads inline with strict timeouts and count failures, or enqueue a `CacheHotelImagesJob` after `Hotel` upsert (plan should pick one; queuing scales better).
- **Jet2 URLs**: `media.jet2.com/is/image/...` may need HTTP client behaviour consistent with your existing `LookupHolidayDetailJob` fetch (User-Agent, HTTPS); reuse patterns from RoadSage’s Guzzle client or Laravel HTTP.

## Import / parse (heavy lifting)

1. **Jet2 detail parser** — extend [`Jet2DetailPageParser`](app/Services/ProviderImport/DetailParsers/Jet2DetailPageParser.php):
   - In the existing JSON-LD `Hotel` loop (where ratings and geo are already read), read `image` from the same `$doc` and normalise:
     - string URL → one image
     - array of strings or `ImageObject`-like structures → ordered list of URLs (match schema.org variations with **fixture-backed** unit tests using [`tests/Fixtures/jet2_detail_prinsotel_alba.html`](tests/Fixtures/jet2_detail_prinsotel_alba.html) / [`jet2_detail_iberostar_waves_malaga_playa.html`](tests/Fixtures/jet2_detail_iberostar_waves_malaga_playa.html)).
   - **Optional second pass** (only if you want a gallery, not just hero): parse the known gallery markup (e.g. `image-galleryV2__fullimage` + `data-lazy`) in a **dedicated private method** with tests pinned to the same HTML fixtures — no recursive JSON sweep.
2. **Merge** — [`LookupHolidayDetailJob`](app/Jobs/LookupHolidayDetailJob.php) already does `array_merge($candidate, $detail['hotel'])`. Ensure `hotel` payload includes a top-level `images` key the normaliser understands.

## Normalisation / persistence

3. **HolidayOptionNormaliser** — update [`splitHotelAndPackageData()`](app/Services/Normalisation/HolidayOptionNormaliser.php):
   - Add `images` to `$hotelKeys` so it is written to the `hotels` row, not only buried inside `raw_attributes`.
4. **Migration** — new migration adding `images` json to `hotels` (nullable default).
5. **Upsert behaviour (JSON)** — on each successful detail import, **replace** `images` when the new parse is non-empty; if the new parse yields no images, **retain** existing `images` (mirrors RoadSage’s refresh fallback idea in [`Service::get`](file:///Users/wade/Sites/road/src/Listings/Services/Service.php) around empty refresh).
6. **Photo sync** — after the `Hotel` record is created/updated with non-empty `images`, call `HotelImageService::syncFromImagesMetadata($hotel)` (name TBD) to:
   - reorder / upsert `hotel_photos` rows to match the JSON order;
   - download missing or stale binaries (define policy: re-download if `status=failed` or `external_url` changed);
   - optionally prune rows whose URL no longer appears in `images` (or keep for audit — choose explicitly in implementation).

## Presentation layer (thin)

7. **ResultCardViewModel** — remove `extractImageUrl` / `raw_attributes` path walking; set `imageUrl` from `Hotel::primaryImagePublicUrl()` (or equivalent) so cards automatically prefer locally cached files.
8. **Deal / cards** — [`resources/views/searches/partials/recommendation-card.blade.php`](resources/views/searches/partials/recommendation-card.blade.php) and any deal view: keep using `$card->imageUrl` (unchanged contract), backed by the model.

## Tests (TDD order)

- Extend [`Jet2DetailPageParserTest`](tests/Unit/ProviderImport/Jet2DetailPageParserTest.php): assert extracted `images` (count, order, first URL) for both real HTML fixtures.
- Add/adjust a **normaliser** test: merged payload produces `hotelData['images']` in the right shape.
- **HotelImageService** (or job) tests: use `Storage::fake()`, HTTP fake a small image response for a known Jet2 URL, assert row `status=cached` and file exists; assert fallback when download fails.
- Add/update [`ResultCardViewModelTest`](tests/Unit/ViewModels/ResultCardViewModelTest.php): assert primary URL picks cached file URL when a `hotel_photos` record exists, else remote from `images` JSON — **no** `raw_attributes` path tricks.
- Run existing feature tests for customer pages.

## Other providers

Only [`Jet2DetailPageParser`](app/Services/ProviderImport/DetailParsers/Jet2DetailPageParser.php) exists today; the `Hotel` JSON column + `hotel_photos` + sync service still allows future parsers to populate `images` the same way.
