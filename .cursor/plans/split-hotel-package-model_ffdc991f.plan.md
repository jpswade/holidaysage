---
name: split-hotel-package-model
overview: Normalise the current mixed holiday option model into separate hotel and package entities, using search-focused package uniqueness and a reset-and-rebuild migration strategy.
todos:
  - id: schema-split
    content: Add hotels and holiday_packages schema plus scoring FK changes
    status: completed
  - id: model-refactor
    content: Introduce Hotel and HolidayPackage models/relationships and retire mixed HolidayOption model
    status: completed
  - id: normaliser-split
    content: Refactor normalisation service and jobs to upsert hotel then package
    status: completed
  - id: scoring-update
    content: Update scoring pipeline to read/write package-linked scores
    status: completed
  - id: reset-verify
    content: Truncate agreed data and verify repeated runs no longer duplicate hotel data
    status: completed
isProject: false
---

# Split Hotels and Packages

## Goal
Separate immutable/semi-static hotel attributes from package/offer attributes to avoid duplicated hotel data across runs and providers.

## Target Schema
- Add `hotels` table for hotel identity and profile fields currently repeated in `holiday_options`:
  - provider linkage (`provider_source_id`, `provider_hotel_id`), canonical names/slugs, destination/resort/country, star/review/location/family flags, hotel-level `raw_attributes`, first/last seen timestamps.
  - unique key: provider + provider_hotel_id (with fallback hash when provider_hotel_id is missing).
- Add `holiday_packages` table for package-level fields:
  - `hotel_id` FK, provider option/url refs, airport, departure/return/nights, occupancy (`adults/children/infants`), board, flight/transfer details, package pricing/currency, package-level raw attributes, first/last seen timestamps.
  - unique key (search-focused): hotel + departure date + nights + airport + occupancy + board.
- Repoint scoring to package rows:
  - update `scored_holiday_options` to reference `holiday_package_id` (or rename table to `scored_holiday_packages` with equivalent columns).

## Service/Job Refactor
- Replace `HolidayOptionNormaliser` responsibilities with two-stage normalisation:
  - hotel normalisation/upsert first,
  - package normalisation/upsert second (linked to hotel).
- Update pipeline jobs to track package IDs in runs (replace `imported_holiday_option_ids` semantics with package IDs).
- Keep scorer logic package-centric (scores a package using joined hotel+package attributes).

## Reset Strategy
- Apply schema migration set and then truncate search/import/scoring/package data as agreed.
- No backfill from old `holiday_options`; rebuild from fresh imports.

## Key Files To Change
- Migrations:
  - `[database/migrations](database/migrations)` (new `create_hotels_table`, `create_holiday_packages_table`, scoring FK migration, and optional old table deprecation migration).
- Models:
  - `[app/Models/HolidayOption.php](app/Models/HolidayOption.php)` (replace/retire), add `Hotel` + `HolidayPackage` models.
  - `[app/Models/ScoredHolidayOption.php](app/Models/ScoredHolidayOption.php)` (FK/relationship update).
  - `[app/Models/SavedHolidaySearchRun.php](app/Models/SavedHolidaySearchRun.php)` (imported IDs field meaning update).
- Normalisation + jobs:
  - `[app/Services/Normalisation/HolidayOptionNormaliser.php](app/Services/Normalisation/HolidayOptionNormaliser.php)` (split logic).
  - `[app/Jobs/NormaliseHolidayCandidateJob.php](app/Jobs/NormaliseHolidayCandidateJob.php)`
  - `[app/Jobs/ScoreHolidayOptionsForSearchJob.php](app/Jobs/ScoreHolidayOptionsForSearchJob.php)`
- Importers/parsers:
  - Jet2/TUI importers continue emitting candidate payloads but mapped into hotel+package write model.

## Rollout Sequence
```mermaid
flowchart LR
  addSchema[AddHotelsAndPackagesSchema] --> adaptWrites[RefactorNormaliserAndJobs]
  adaptWrites --> repointScoring[RepointScoringToPackageFK]
  repointScoring --> resetData[TruncateImportSearchScoringData]
  resetData --> verify[RunSyncImportAndValidateCounts]
```

## Validation
- Run same URL repeatedly and verify:
  - hotel count stabilises,
  - package count grows only when unique search-focused package key changes,
  - scoring rows reference package IDs and remain one-per-run-per-package.
- Confirm CLI counts still reconcile (`raw/parsed/normalised/scored`) against package rows.