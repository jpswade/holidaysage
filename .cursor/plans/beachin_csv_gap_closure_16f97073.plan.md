---
name: CSV Gap Closure
overview: Complete an upfront field-level analysis for empty CSV columns, then implement extraction, persistence, and export wiring so run-scoped CSV output is materially complete and deterministic.
todos:
  - id: field-matrix
    content: Finalise the field matrix for currently blank CSV columns with extraction rule, persistence target, and export fallback order.
    status: completed
  - id: parser-parity
    content: Implement missing Jet2 detail extraction heuristics (facilities, walkability, accessibility, cots, transfer/flight estimates) in the parser.
    status: completed
  - id: normaliser-wiring
    content: Route new extracted fields through normaliser and persistence with null-safe semantics.
    status: completed
  - id: export-fill
    content: Replace hardcoded empty CSV exports with mapped persisted/raw values and Beachin-compatible formatting.
    status: completed
  - id: tests-parity
    content: Add/extend fixture-based tests and run run-scoped CSV parity comparison against the Python Sheet2 reference.
    status: completed
isProject: false
---

# CSV Enrichment Plan (Upfront Analysis First)

## Current-State Analysis
- The export mapping in [`/Users/wade/Sites/holidaysage/app/Console/Commands/HolidaySageExportCsvCommand.php`](/Users/wade/Sites/holidaysage/app/Console/Commands/HolidaySageExportCsvCommand.php) has many literal placeholders (`''`) in the row builder (`132-206`), so blanks are guaranteed even when data could be derived.
- `Hotel` currently stores core enrichment values (counts, distances, booleans, accessibility issues) but does not yet persist several walkability/facility/accessibility-detail fields required by CSV output.
- `HolidayPackage` stores board, prices, flight windows, and local prices, but not secondary travel estimates (`flight_time_hours_est`, `transfer_type`) or cot notes/signal.
- `HolidayOptionNormaliser` routes only a subset of extracted attributes into persistent columns and currently duplicates unclassified raw fields into both `hotel_extra` and `package_extra`, which complicates deterministic export fallback logic.
- Parser coverage in [`/Users/wade/Sites/holidaysage/app/Services/ProviderImport/DetailParsers/Jet2DetailPageParser.php`](/Users/wade/Sites/holidaysage/app/Services/ProviderImport/DetailParsers/Jet2DetailPageParser.php) is strong for counts/distances/local prices/flight windows but still missing a defined extraction contract for several exported amenity and note fields.

## Field Matrix (Upfront Specification)
- **Travel estimates**
  - `flight_time_hours_est`: derive from explicit average-flight text first; fallback from outbound/inbound window duration heuristics; store on package.
  - `transfer_type`: derive from explicit phrases (`private`, `shared`, `coach`), else default to `coach` when transfer exists; store on package.
- **Facilities**
  - `play_area`, `evening_entertainment`, `kids_disco`, `gym`, `spa`, `adults_only_area`: boolean signals from explicit section/list items first, then full-text keyword checks; store on hotel.
  - `kids_club_age_min`: regex extraction from kids club age ranges (e.g. `4-12`); store on hotel.
- **Walkability**
  - `promenade`, `near_shops`, `cafes_bars`, `harbour`: boolean signals from explicit text evidence; store on hotel.
  - `distance_to_shops_m`, `distance_to_cafes_bars_m`: regex extraction from metre/km phrases with normalised units; store on hotel.
- **Accessibility detail**
  - `steps_count`: numeric extraction when steps are mentioned; store on hotel.
  - `accessibility_notes`: joined, human-readable summary of accessibility evidence (`lift`, `steps`, floor constraints); store on hotel.
- **Room/text content**
  - `cots_available`: true when cot evidence exists; null otherwise; store on package or hotel (choose one canonical owner, then export consistently).
  - `introduction_snippet`: first N chars (fixed cap) of introduction text; store in hotel raw attributes (`hotel_extra.introduction_text`) and export computed snippet.
  - `style_keywords`: deterministic keyword extraction from introduction + key selling points; persist as delimited text or raw list, export as delimited string.

## Export Fallback Rules (Deterministic)
- Every exported column must have a strict fallback order to avoid silent blanks:
  - `column value` -> `model column` -> `raw_attributes namespaced key` -> computed heuristic at export-time -> `''`.
- Avoid deriving business values inside export if they can be parsed once and persisted; export-time heuristics should only be final fallback.
- For booleans, keep current CSV contract (`TRUE`/`FALSE`/blank). Blank means unknown, not false.

## Implementation Approach
- Use this plan’s field matrix as the canonical contract for extraction and export.
- Extraction order for every field: explicit structured data -> targeted selector extraction -> deterministic text heuristic -> null.
- Store new values in model columns when queryable/filterable; otherwise store in `raw_attributes` with stable namespaced keys, then expose in export.
- Keep null semantics for unknown values (no false negatives).

## Workstreams

### 1) Add missing data carriers in HolidaySage
- Extend schema/models for fields currently only export-placeholders (or not persisted).
- Files:
  - [`/Users/wade/Sites/holidaysage/database/migrations/`](/Users/wade/Sites/holidaysage/database/migrations/)
  - [`/Users/wade/Sites/holidaysage/app/Models/Hotel.php`](/Users/wade/Sites/holidaysage/app/Models/Hotel.php)
  - [`/Users/wade/Sites/holidaysage/app/Models/HolidayPackage.php`](/Users/wade/Sites/holidaysage/app/Models/HolidayPackage.php)
  - [`/Users/wade/Sites/holidaysage/app/Services/Normalisation/HolidayOptionNormaliser.php`](/Users/wade/Sites/holidaysage/app/Services/Normalisation/HolidayOptionNormaliser.php)
- Promote likely-queryable fields to columns (walkability/facilities/accessibility/travel estimates), retain long-tail textual notes in raw attributes.

### 2) Implement missing extraction logic in Jet2 parser pipeline
- Implement the field matrix extraction contract in:
  - [`/Users/wade/Sites/holidaysage/app/Services/ProviderImport/DetailParsers/Jet2DetailPageParser.php`](/Users/wade/Sites/holidaysage/app/Services/ProviderImport/DetailParsers/Jet2DetailPageParser.php)
- Specifically add:
  - `grid-item__heading/text` local-info map
  - `overview__list-text` play area/distance cues
  - full-text heuristics for gym/spa/kids_disco/evening entertainment/promenade/harbour/shops/cafes-bars/adults-only
  - kids-club age extraction
  - accessibility section + step-count regex and notes composition
  - cots evidence extraction and boolean derivation
  - transfer type and flight-time estimate derivation rules

### 3) Wire values through normalisation and upserts
- Route parser outputs to `Hotel`/`HolidayPackage` consistently and preserve extras in namespaced raw payloads.
- Ensure no data loss when fields are absent in a run (null-safe updates).
- Files:
  - [`/Users/wade/Sites/holidaysage/app/Services/Normalisation/HolidayOptionNormaliser.php`](/Users/wade/Sites/holidaysage/app/Services/Normalisation/HolidayOptionNormaliser.php)

### 4) Replace CSV placeholders with sourced values
- Update export row mapping so previously empty fields use persisted values/raw fallbacks from this plan’s matrix.
- File:
  - [`/Users/wade/Sites/holidaysage/app/Console/Commands/HolidaySageExportCsvCommand.php`](/Users/wade/Sites/holidaysage/app/Console/Commands/HolidaySageExportCsvCommand.php)
- Keep existing formatting contracts (boolean CSV format, currency formatting, stable date/time formatting).

### 5) Add fixture-based regression tests and parity checks
- Extend test coverage with real Jet2 fixtures for the newly populated fields.
- Files:
  - [`/Users/wade/Sites/holidaysage/tests/Unit/ProviderImport/Jet2DetailPageParserTest.php`](/Users/wade/Sites/holidaysage/tests/Unit/ProviderImport/Jet2DetailPageParserTest.php)
  - [`/Users/wade/Sites/holidaysage/tests/Feature/HolidaySage/HolidaySageExportCsvCommandTest.php`](/Users/wade/Sites/holidaysage/tests/Feature/HolidaySage/HolidaySageExportCsvCommandTest.php)
- Re-run parity against `Holidays Summer 2026 - Sheet2.csv` using run-scoped export and produce a residual-diff list.

## Acceptance Criteria
- Run-scoped CSV export populates currently-blank target columns whenever extraction rules in this plan detect evidence.
- Remaining blanks are only where no explicit data and no heuristic signal exists.
- Tests pass with real fixtures and parity diff is reduced to expected tolerances (format/precision only unless documented).