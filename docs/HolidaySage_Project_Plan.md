# HolidaySage — Software Project Plan (v1)

## 1. Overview

**Project Name:** HolidaySage  
**Type:** SaaS Web Application (MVP)  
**Status:** In Development (Prototype Complete via v0)  

---

## 2. Objective

HolidaySage is a decision engine that helps users find the **best holiday options** by:

- Defining preferences once
- Continuously tracking package holidays (Jet2, TUI, etc.)
- Ranking and filtering options using an intelligent scoring system

### Core Value Proposition

> “Set your ideal holiday once — we’ll find the best options for you, every day.”

---

## 3. Problem Statement

Users currently:
- Browse multiple travel sites manually
- Compare dozens of hotels
- Struggle to evaluate trade-offs (price vs travel vs quality)
- Revisit searches repeatedly over time

### Existing tools:
- Overwhelming (too many options)
- Passive (no ongoing tracking)
- Neutral (no recommendations)

### HolidaySage:
- Reduces choice to 3–5 strong options
- Provides clear recommendations
- Continuously improves results over time

---

## 4. Key Concepts

### SavedHolidaySearch (Core Entity)

Represents a user-defined search:

```json
{
  "departure_airport": "MAN",
  "date_range": "July-August",
  "duration": 10,
  "adults": 2,
  "children": 2,
  "budget": 4000,
  "max_flight_hours": 4,
  "max_transfer_minutes": 60,
  "preferences": ["family friendly", "walkable", "near beach"]
}
```

### HolidayOption
A normalised holiday package:
- Hotel
- Flight
- Transfer
- Board
- Price
- Features

### ScoredHolidayOption
HolidayOption + scoring output:
- Overall score (0–10)
- Sub-scores
- Recommendation explanation
- Warning flags

---

## 5. System Architecture

### Data Flow

1. User creates SavedHolidaySearch
2. Ingestion layer (scraper)
3. Normalisation layer
4. Scoring engine
5. Storage
6. Frontend display

### Components

| Component | Responsibility |
|----------|----------------|
| Scraper | Fetch raw holiday data |
| Parser | Extract structured data |
| Normaliser | Standardise data model |
| Scoring Engine | Rank + evaluate |
| API Layer | Serve frontend |
| Frontend | UI / UX |
| Scheduler | Daily updates |

---

## 6. Features (MVP Scope)

### Core Features

#### Create Search
- Form-based input
- Optional import from provider link
- Saves configuration

#### Saved Searches Dashboard
- List of searches
- Status indicators

#### Results View
- Top 3–5 ranked options
- Highlighted Best Overall Choice

Each card includes:
- Score
- Price
- Flight time
- Transfer time
- Board type
- Key features
- Recommendation reasoning
- Warning flags

#### Continuous Updates
- Daily ingestion
- Automatic refresh

---

## 7. Non-Functional Requirements

### Performance
- Results load < 2 seconds

### UX
- Mobile-first
- Minimal friction

### Reliability
- Scraper resilience
- Graceful degradation

---

## 8. Scoring System

### Inputs:
- Flight duration
- Transfer time
- Price vs budget
- Family suitability
- Location factors

### Outputs:
- Overall score
- Sub-scores
- Explanation
- Warnings

---

## 9. Milestones

### Phase 1 — Prototype (Complete)
- v0 UI

### Phase 2 — MVP Integration
- Connect UI to API
- Integrate pipeline

### Phase 3 — Scoring Refinement
- Improve explanations

### Phase 4 — User Testing
- Gather feedback

### Phase 5 — Iteration
- Refine UX and scoring

---

## 10. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Scraper instability | Retry + caching |
| Low user trust | Improve explanations |
| Generic feel | Maintain opinionated UX |
| UI complexity | Keep MVP tight |

---

## 11. Future Enhancements

- Notifications (price drops, better options)
- Historical tracking
- Destination layer integration
- AI recommendations

---

## 12. Success Criteria

- Users understand instantly
- Users trust recommendations
- Users say it saves time

---

## 13. Strategic Positioning

HolidaySage is not:
- A booking platform
- A deals site

It is:

> A decision engine for holidays
