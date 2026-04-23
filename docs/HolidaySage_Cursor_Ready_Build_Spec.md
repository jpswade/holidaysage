# HolidaySage — Cursor-Ready Build Specification (v1)

## Purpose

This document is a build-ready specification for **HolidaySage**, designed for use in Cursor as the primary implementation brief.

HolidaySage is a saved-search holiday decision engine. Users define their ideal holiday once, and the system continuously imports, scores, ranks, and refreshes package holiday options from providers such as Jet2 and TUI.

It is **not** a booking platform.  
It is **not** a generic travel listing site.  
It is a **decision engine for holidays**.

---

## Product Summary

### Core value proposition

> Set your ideal holiday once — HolidaySage will keep checking and show you the best options.

### MVP goals

Build an application that allows users to:

1. Create a saved holiday search using a structured form
2. Optionally import criteria from a provider search URL
3. Store and manage saved searches
4. Ingest provider holiday results on a schedule
5. Normalise holiday packages into a consistent schema
6. Score and rank results against the saved search criteria
7. Present the best options clearly, with reasoning and warning flags
8. Re-run searches daily and update results over time

---

## Product Principles

1. **Opinionated, not neutral**  
   The system should make recommendations, not simply list holidays.

2. **Saved search first**  
   The core object is a persistent search, similar to RoadSage.

3. **Import is secondary**  
   Provider links may be used to prefill a search, but the product should not centre around pasted URLs.

4. **Human decision support**  
   Recommendations must explain why an option was selected.

5. **Continuous improvement**  
   Saved searches should refresh automatically and surface better matches over time.

---

## Recommended Tech Stack

Assume a Laravel-based stack unless the existing codebase dictates otherwise.

### Backend
- PHP 8.3+
- Laravel 11+
- MySQL or MariaDB
- Redis for queue/cache
- Laravel Horizon for queue visibility
- Laravel Scheduler / cron for recurring imports
- Laravel Scout optional later, not required for MVP

### Frontend
- Blade + Livewire or Inertia + Vue
- Tailwind CSS
- Alpine.js optional for simple interactions

### Background processing
- Laravel queues for:
  - provider imports
  - parsing
  - normalisation
  - scoring
  - refresh orchestration

### Storage
- S3-compatible object storage optional for raw snapshots
- Local disk acceptable for MVP snapshots if needed

---

## High-Level Architecture

### Core flow

1. User creates a `SavedHolidaySearch`
2. System creates one or more provider-specific import jobs
3. Raw provider data is fetched and stored
4. Raw provider entries are parsed into structured package candidates
5. Structured candidates are normalised into `HolidayOption`
6. Scoring engine evaluates each option against the saved search
7. Ranked results are persisted as `ScoredHolidayOption`
8. Frontend displays best results
9. Scheduler refreshes search daily

---

## Domain Model

## 1. saved_holiday_searches

Represents the user's persistent holiday search.

### Fields

- `id`
- `uuid`
- `user_id` nullable for MVP if anonymous searches are allowed
- `name`
- `slug`
- `provider_import_url` nullable
- `departure_airport_code`
- `departure_airport_name` nullable
- `travel_start_date` nullable
- `travel_end_date` nullable
- `travel_date_flexibility_days` default 0
- `duration_min_nights`
- `duration_max_nights`
- `adults`
- `children`
- `infants` default 0
- `budget_total` nullable
- `budget_per_person` nullable
- `max_flight_minutes` nullable
- `max_transfer_minutes` nullable
- `board_preferences` JSON nullable
- `destination_preferences` JSON nullable
- `feature_preferences` JSON nullable
- `excluded_destinations` JSON nullable
- `excluded_features` JSON nullable
- `sort_preference` nullable
- `status` enum(`draft`, `active`, `paused`, `archived`)
- `last_imported_at` nullable
- `last_scored_at` nullable
- `next_refresh_due_at` nullable
- `created_at`
- `updated_at`

### Example feature preferences

```json
[
  "family_friendly",
  "walkable",
  "near_beach",
  "kids_club",
  "all_inclusive_preferred"
]
```

---

## 2. saved_holiday_search_runs

Represents a refresh cycle for a saved search.

### Fields

- `id`
- `saved_holiday_search_id`
- `run_type` enum(`manual`, `scheduled`, `import`)
- `status` enum(`queued`, `running`, `completed`, `failed`)
- `provider_count` default 0
- `raw_record_count` default 0
- `parsed_record_count` default 0
- `normalised_record_count` default 0
- `scored_record_count` default 0
- `started_at` nullable
- `finished_at` nullable
- `error_message` nullable
- `created_at`
- `updated_at`

---

## 3. provider_sources

Represents supported providers.

### Fields

- `id`
- `key` e.g. `jet2`, `tui`
- `name`
- `base_url`
- `status` enum(`active`, `disabled`)
- `created_at`
- `updated_at`

---

## 4. provider_import_snapshots

Stores raw fetches or provider response snapshots for debugging and replay.

### Fields

- `id`
- `saved_holiday_search_run_id`
- `provider_source_id`
- `source_url`
- `response_status`
- `snapshot_path` nullable
- `snapshot_hash` nullable
- `record_count_estimate` nullable
- `fetched_at`
- `created_at`
- `updated_at`

---

## 5. holiday_options

Represents a normalised holiday package option.

### Fields

- `id`
- `provider_source_id`
- `provider_option_id`
- `provider_hotel_id` nullable
- `provider_url`
- `hotel_name`
- `hotel_slug`
- `resort_name` nullable
- `destination_name`
- `destination_country`
- `airport_code`
- `departure_date`
- `return_date`
- `nights`
- `adults`
- `children`
- `infants`
- `board_type` nullable
- `price_total`
- `price_per_person` nullable
- `currency` default `GBP`
- `flight_outbound_duration_minutes` nullable
- `flight_inbound_duration_minutes` nullable
- `transfer_minutes` nullable
- `distance_to_beach_meters` nullable
- `distance_to_centre_meters` nullable
- `star_rating` nullable
- `review_score` nullable
- `review_count` nullable
- `is_family_friendly` default false
- `has_kids_club` default false
- `has_waterpark` default false
- `has_family_rooms` default false
- `latitude` nullable
- `longitude` nullable
- `raw_attributes` JSON nullable
- `signature_hash`
- `first_seen_at`
- `last_seen_at`
- `created_at`
- `updated_at`

### Unique guidance

Use a composite uniqueness rule that approximates package uniqueness, for example:

- provider
- provider option id if reliable
- departure date
- nights
- hotel name
- room / board / occupancy signature

---

## 6. scored_holiday_options

Represents the score of a holiday option for a specific saved search and run.

### Fields

- `id`
- `saved_holiday_search_id`
- `saved_holiday_search_run_id`
- `holiday_option_id`
- `overall_score` decimal(5,2)
- `travel_score` decimal(5,2) nullable
- `value_score` decimal(5,2) nullable
- `family_fit_score` decimal(5,2) nullable
- `location_score` decimal(5,2) nullable
- `board_score` decimal(5,2) nullable
- `price_score` decimal(5,2) nullable
- `is_disqualified` boolean default false
- `disqualification_reasons` JSON nullable
- `warning_flags` JSON nullable
- `recommendation_summary` text nullable
- `recommendation_reasons` JSON nullable
- `rank_position` nullable
- `created_at`
- `updated_at`

---

## 7. holiday_search_import_mappings

Optional helper table for storing extracted import criteria from provider URLs.

### Fields

- `id`
- `saved_holiday_search_id`
- `provider_source_id`
- `original_url`
- `extracted_criteria` JSON
- `created_at`
- `updated_at`

---

## Enums and Shared Values

### Search status
- `draft`
- `active`
- `paused`
- `archived`

### Run status
- `queued`
- `running`
- `completed`
- `failed`

### Run type
- `manual`
- `scheduled`
- `import`

### Provider status
- `active`
- `disabled`

---

## API / Route Design

Assume standard Laravel web routes plus optional JSON endpoints.

## Web routes

### Public or authenticated routes

- `GET /`
  - Landing page
- `GET /searches`
  - Saved searches index
- `GET /searches/create`
  - Create saved search form
- `POST /searches`
  - Store saved search
- `GET /searches/{search}`
  - Show saved search and best current results
- `GET /searches/{search}/edit`
  - Edit saved search
- `PUT /searches/{search}`
  - Update saved search
- `POST /searches/{search}/refresh`
  - Trigger manual refresh
- `GET /searches/{search}/results`
  - Full results list
- `GET /searches/{search}/runs`
  - Run history
- `GET /searches/{search}/runs/{run}`
  - Show run details
- `POST /searches/import`
  - Parse provider URL and return prefill suggestions

## Optional JSON endpoints

- `GET /api/searches`
- `POST /api/searches`
- `GET /api/searches/{search}`
- `POST /api/searches/{search}/refresh`
- `GET /api/searches/{search}/results`
- `GET /api/searches/{search}/runs/{run}`

---

## UI Pages

## 1. Landing page

### Purpose
Explain the product clearly and direct users into the saved search flow.

### Content
- Headline
- Short explanation of the saved search concept
- CTA to create a search
- Optional examples of tracked searches
- Trust-building explanation of how HolidaySage works

### Suggested headline
**Find your best holiday without checking the same travel sites every day**

---

## 2. Create Search page

### Purpose
Capture user intent in a structured way.

### Sections
- Search name
- Departure airport
- Date or date range
- Flexibility
- Nights
- Party composition
- Budget
- Travel limits
- Preferences
- Optional provider import URL

### UX note
This page should feel like defining an ideal holiday, not filling in a booking engine.

### Example preference options
- Family friendly
- Near beach
- Walkable area
- Kids club
- Waterpark
- Quiet resort
- All inclusive preferred
- Short transfer
- Good review scores

---

## 3. Saved Searches index

### Purpose
Show all searches and their status.

### Each card should show
- Search name
- Summary of criteria
- Last refreshed time
- Number of active results
- Whether better matches were found recently
- CTA to open search

---

## 4. Saved Search detail page

### Purpose
Show the current best matches and latest activity for a single search.

### Sections
- Search summary
- Last updated timestamp
- Top pick
- Ranked shortlist
- Why options were selected
- Warning flags
- Run history snippet

### Important
This page is the heart of the product.

---

## 5. Results page

### Purpose
Show full ranked list for a search or run.

### Each card should include
- Hotel name
- Provider
- Overall score
- Price
- Nights
- Flight duration
- Transfer duration
- Board type
- Key features
- Recommendation reasons
- Warning flags
- Optional link out to provider

---

## Recommendation UX Rules

Each scored card must answer:

1. Why was this selected?
2. What trade-offs should I know?
3. Why is it ranked above the next one?

### Example recommendation summary

> Excellent overall fit for a family summer break. Strong family facilities, short transfer, good value, and comfortably within budget.

### Example recommendation reasons
```json
[
  "Transfer under 40 minutes",
  "Kids club and family rooms available",
  "Near beach and walkable resort area",
  "Good value against similar options"
]
```

### Example warning flags
```json
[
  "Large resort may feel busy in peak season",
  "Beach is a short walk rather than beachfront"
]
```

---

## Scoring Engine Specification

## Goal

Convert structured package options into ranked, defensible recommendations.

## Suggested scoring dimensions

### 1. Travel Score
Factors:
- outbound flight duration
- inbound flight duration
- transfer time
- departure airport match
- travel date closeness

### 2. Value Score
Factors:
- total price
- price relative to comparable options
- price relative to budget
- rating/review balance

### 3. Family Fit Score
Factors:
- kids club
- family rooms
- family-friendly status
- waterpark
- board type suitability

### 4. Location Score
Factors:
- distance to beach
- distance to centre
- walkability
- resort suitability

### 5. Board Score
Factors:
- whether board matches preferences
- all inclusive preference uplift
- downgrade if undesired board only

---

## Sample MVP scoring approach

Use a weighted score out of 10:

```text
overall_score =
  (travel_score * 0.25) +
  (value_score * 0.25) +
  (family_fit_score * 0.25) +
  (location_score * 0.15) +
  (board_score * 0.10)
```

You can tune weights later per search type.

---

## Suggested disqualification rules

Disqualify or heavily penalise if:

- price exceeds hard budget by large margin
- transfer exceeds hard max
- nights outside allowed range
- destination is excluded
- critical family requirement missing
- board type clearly conflicts with requirement

Store reasons in `disqualification_reasons`.

---

## Background Jobs

## Job list

### 1. `RefreshSavedHolidaySearchJob`
Orchestrates a full refresh cycle for a single search.

Responsibilities:
- create run record
- dispatch provider import jobs
- dispatch downstream parse / normalise / score workflow
- update run state

### 2. `ImportProviderResultsJob`
Fetches raw provider results for one provider.

Responsibilities:
- build provider request from search criteria
- fetch raw response
- store snapshot
- dispatch parsing job

### 3. `ParseProviderSnapshotJob`
Parses raw provider snapshot into structured candidate entries.

Responsibilities:
- extract hotel/package rows
- standardise provider-specific fields
- emit candidate payloads

### 4. `NormaliseHolidayCandidateJob`
Converts parsed candidate into `HolidayOption`.

Responsibilities:
- unify field shapes
- compute derived fields
- upsert holiday option

### 5. `ScoreHolidayOptionsForSearchJob`
Scores all relevant options for the search and run.

Responsibilities:
- load search criteria
- score candidate options
- persist ranked `scored_holiday_options`
- assign rank positions

### 6. `RefreshDueSearchesJob`
Scheduled job that finds all searches due for refresh.

---

## Scheduler

Run frequently enough to pick up due searches without delay.

### Suggested schedule
- Every hour: `RefreshDueSearchesJob`
- Daily overnight: broad refresh window
- Manual refresh available on demand

### Due logic
Each active search should have `next_refresh_due_at`.
After a successful run, set next due time to around 24 hours later.

---

## Provider Import Strategy

## MVP provider support
- Jet2
- TUI

## Import modes
1. **Search form to provider query**
   - Build provider searches directly from saved search criteria
2. **Provider URL import**
   - Parse a URL and prefill fields
   - Store original mapping for reference

### Recommendation
Treat URL import as a helper, not the primary model.

---

## Data Normalisation Rules

Normalise as early as possible into a shared internal shape.

### Important normalisations
- airport codes
- dates
- durations
- board labels
- prices
- booleans for features
- resort/destination naming
- distances
- ratings

### Example board mappings
- `AI`, `All Inclusive`, `All-In` => `all_inclusive`
- `HB`, `Half Board` => `half_board`
- `SC`, `Self Catering` => `self_catering`

---

## Directory / Project Structure Guidance

Example Laravel structure:

```text
app/
  Actions/
    HolidaySearch/
  Data/
  Enums/
  Http/
    Controllers/
      SearchController.php
      SearchResultController.php
      SearchRunController.php
      SearchImportController.php
    Requests/
  Jobs/
    RefreshSavedHolidaySearchJob.php
    ImportProviderResultsJob.php
    ParseProviderSnapshotJob.php
    NormaliseHolidayCandidateJob.php
    ScoreHolidayOptionsForSearchJob.php
    RefreshDueSearchesJob.php
  Models/
    SavedHolidaySearch.php
    SavedHolidaySearchRun.php
    ProviderSource.php
    ProviderImportSnapshot.php
    HolidayOption.php
    ScoredHolidayOption.php
  Services/
    Providers/
      Jet2/
      Tui/
    Scoring/
      HolidayScorer.php
      ScoreBreakdown.php
    Imports/
      ImportUrlParser.php
    Normalisation/
      HolidayOptionNormaliser.php
  Support/
database/
  migrations/
  factories/
  seeders/
resources/
  views/
    searches/
routes/
  web.php
  api.php
```

---

## Model Relationships

### SavedHolidaySearch
- hasMany `SavedHolidaySearchRun`
- hasMany `ScoredHolidayOption`

### SavedHolidaySearchRun
- belongsTo `SavedHolidaySearch`
- hasMany `ProviderImportSnapshot`
- hasMany `ScoredHolidayOption`

### HolidayOption
- belongsTo `ProviderSource`
- hasMany `ScoredHolidayOption`

### ScoredHolidayOption
- belongsTo `SavedHolidaySearch`
- belongsTo `SavedHolidaySearchRun`
- belongsTo `HolidayOption`

---

## Eloquent Model Notes

### SavedHolidaySearch casts
- `board_preferences` => array
- `destination_preferences` => array
- `feature_preferences` => array
- `excluded_destinations` => array
- `excluded_features` => array
- dates => datetime

### HolidayOption casts
- `raw_attributes` => array
- booleans for feature flags
- numeric ratings/prices

### ScoredHolidayOption casts
- `disqualification_reasons` => array
- `warning_flags` => array
- `recommendation_reasons` => array

---

## Initial Migrations To Create

Create migrations in this order:

1. `provider_sources`
2. `saved_holiday_searches`
3. `saved_holiday_search_runs`
4. `provider_import_snapshots`
5. `holiday_options`
6. `scored_holiday_options`
7. `holiday_search_import_mappings`

---

## Seed Data

Seed provider sources:

```php
[
    ['key' => 'jet2', 'name' => 'Jet2', 'base_url' => 'https://www.jet2holidays.com', 'status' => 'active'],
    ['key' => 'tui', 'name' => 'TUI', 'base_url' => 'https://www.tui.co.uk', 'status' => 'active'],
]
```

Also seed:
- common board types if needed
- common airport choices if you keep an airports table
- example saved searches for local development

---

## Implementation Order

## Phase 1 — Core domain and storage
1. Create migrations
2. Create Eloquent models
3. Define enums and casts
4. Seed provider sources

## Phase 2 — Saved search CRUD
5. Build create/edit/store/update pages
6. Build saved searches index
7. Build saved search show page with placeholder results
8. Add validation rules

## Phase 3 — Import foundation
9. Implement URL import parser interface
10. Implement provider source abstractions
11. Store raw provider snapshots
12. Build run model lifecycle

## Phase 4 — Parsing and normalisation
13. Parse provider data into structured candidates
14. Upsert `holiday_options`
15. Track first seen / last seen

## Phase 5 — Scoring
16. Implement scorer service
17. Generate `scored_holiday_options`
18. Rank and store recommendation summaries
19. Add top-pick logic

## Phase 6 — Refresh automation
20. Add scheduled refresh orchestration
21. Add manual refresh action
22. Add run history and status display

## Phase 7 — Product polish
23. Improve trust messaging
24. Add score breakdown UI
25. Add “better option found” messaging
26. Add warning / trade-off presentation

---

## Validation Rules

## SavedHolidaySearchRequest
Suggested rules:
- `name`: required|string|max:120
- `departure_airport_code`: required|string|max:8
- `duration_min_nights`: required|integer|min:1|max:30
- `duration_max_nights`: required|integer|min:1|max:30|gte:duration_min_nights
- `adults`: required|integer|min:1|max:10
- `children`: nullable|integer|min:0|max:10
- `infants`: nullable|integer|min:0|max:10
- `budget_total`: nullable|numeric|min:0
- `max_flight_minutes`: nullable|integer|min:30|max:1440
- `max_transfer_minutes`: nullable|integer|min:0|max:600
- arrays for preferences / exclusions should be validated item-by-item

---

## Example Search Summary Logic

Generate readable summaries for cards and headers:

> Manchester · July/August · 10–11 nights · 2 adults, 2 children · Up to £4,500 · Family friendly · Near beach

This summary is useful on:
- saved search cards
- search headers
- notification messages later

---

## Example Top Pick Logic

Top pick is the highest-ranked non-disqualified option.

Tie-breakers:
1. higher overall score
2. lower total price
3. shorter transfer
4. stronger family fit

---

## Example Service Class Interfaces

### `HolidayScorer`

```php
interface HolidayScorer
{
    public function score(SavedHolidaySearch $search, HolidayOption $option): ScoreBreakdown;
}
```

### `ImportUrlParser`

```php
interface ImportUrlParser
{
    public function supports(string $url): bool;

    public function parse(string $url): array;
}
```

### `ProviderSearchBuilder`

```php
interface ProviderSearchBuilder
{
    public function build(SavedHolidaySearch $search): array;
}
```

---

## Logging and Debugging

Log enough detail to diagnose provider issues.

### Log events
- search refresh started
- provider import started
- provider import failed
- parse counts
- normalise counts
- score counts
- run completed

### Store
- snapshot path or payload metadata
- error message on failed runs
- per-run record counts

---

## Testing Strategy

## Feature tests
- create saved search
- update saved search
- manual refresh dispatches run
- results page loads ranked results

## Unit tests
- scorer calculations
- disqualification rules
- import URL parsers
- normalisation mappings

## Integration tests
- provider snapshot to holiday option conversion
- refresh run lifecycle

---

## MVP Success Criteria

The MVP is successful if:

1. A user can create a saved holiday search in under two minutes
2. The system can import and normalise results from at least one provider
3. The system can score and rank options consistently
4. The saved search detail page makes the top recommendation feel credible
5. A refresh cycle can run automatically without manual intervention

---

## Explicit Non-Goals For MVP

Do not build these yet:

- user accounts if not required
- payments
- booking checkout
- alerts/notifications
- social features
- full destination content explorer
- advanced collaborative search sharing
- generic marketplace filters

---

## Suggested Cursor Prompt

Use this when handing the project to Cursor:

> Build HolidaySage as a Laravel 11 application using Tailwind and Blade or Livewire.  
> Implement the saved-search-first architecture described in this specification.  
> Start by creating the migrations, models, enums, validation requests, and CRUD pages for `SavedHolidaySearch`, followed by the run tracking tables and placeholder scored results.  
> Then scaffold the provider import pipeline with interfaces for Jet2 and TUI, raw snapshot storage, normalisation, and a scoring service.  
> Keep the product focused on persistent holiday searches, ranked recommendations, and daily refreshes.  
> Do not build a booking platform or generic travel listing site.

---

## Final Product Positioning Reminder

HolidaySage should feel like:

- a calm assistant
- a decision engine
- a persistent watcher of the market

It should not feel like:

- a noisy OTA
- a generic comparison engine
- a travel affiliate page with filters everywhere
