# Majlisilmu MVP Feature Checklist

**Last Updated:** 2026-03-10  
**Document Purpose:** Track current MVP implementation status against the live codebase (routes, Livewire/Volt pages, Filament resources, services, jobs, tests).

---

## MVP Health Snapshot

| Pillar | Status | Notes |
|---|---|---|
| Public Pages & Core Discovery | DONE | Core browse/detail/submit journeys are implemented and live. |
| Authenticated User Features | DONE | Auth, saved events/searches, interests, registrations, dashboard flows are implemented. |
| Institution Dashboard | PARTIAL | Read visibility is implemented; self-service management workflows are incomplete. |
| Admin/Moderation (Filament) | PARTIAL | Core moderation and resources are implemented; advanced reviewer tooling is incomplete. |
| API Surface | DONE | Public + authenticated MVP API endpoints are implemented. |
| Search & Discovery | DONE | Full-text + geo + advanced filtering and UI are implemented. |
| Infrastructure & Operations | PARTIAL | Core scheduling/logging/notifications are implemented; trust automation/Horizon remain pending. |

---

## Public Pages & Features

### Event Discovery & Display
- [x] Event listing page (`/events`, `/majlis`)
- [x] Event detail page (`/events/{slug}`, `/majlis/{slug}`)
- [x] Event description, speakers, topics display
- [x] Waze / Google Maps navigation links
- [x] Livestream / Recording links
- [x] Donation account display
- [x] Registration form with validation
- [x] JSON-LD structured data (SEO)
- [x] OpenGraph / Twitter Cards
- [x] Share button (native Web Share API + share modal)
- [x] Add to Calendar (Google, Apple/iCal via ICS, Outlook, Office 365, Yahoo)
- [x] Prayer-relative timing display
- [x] "Near me" geo-location button
- [x] Event distance display in search results

### Institutions, Speakers, Series
- [x] Institutions listing page (`/institutions`, `/institusi`)
- [x] Institution detail page (`/institutions/{slug}`, `/institusi/{slug}`)
- [x] Speakers listing page (`/speakers`, `/penceramah`)
- [x] Speaker detail page (`/speakers/{slug}`, `/penceramah/{slug}`)
- [x] Series detail page (`/series/{slug}`, `/siri/{slug}`)

### Public Submission
- [x] Event submission form (`/submit-event`, `/hantar-majlis`)
- [x] Submission success page
- [ ] Submission rate limiting on route
- [x] CAPTCHA integration (Turnstile)
- [x] Poster upload on submission

Note: `event-submission` limiter exists, but is intentionally not attached to `submit-event.create` route in current policy.

---

## User Features (Requires Authentication)

### Authentication
- [x] User registration page
- [x] User login page
- [x] Password reset flow
- [x] Email verification flow
- [x] Social login (Google)

### Saved Events, Interests, Searches
- [x] Save/bookmark events
- [x] View saved events list (API + dashboard)
- [x] Saved searches CRUD API
- [x] Event interest marking API (going/interested)
- [x] Saved searches UI page
- [x] Daily digest email job (scheduled)
- [x] Weekly digest email job (scheduled)
- [x] Email preference settings

### User Dashboard
- [x] User profile summary/dashboard page
- [x] My registrations list
- [x] My saved events list
- [x] My saved searches list

---

## Institution Dashboard

### Institution Management
- [x] Institution dashboard home
- [ ] Create/edit events for institution (institution-scoped workflow)
- [x] View institution events list
- [x] Manage institution profile (self-service dashboard flow)
- [x] Manage donation accounts (self-service dashboard flow)
- [x] View event registrations
- [ ] Export attendee list from institution dashboard UI

### Member Management
- [x] Add/remove institution members from institution dashboard
- [x] Role assignment (admin, editor, viewer) from institution dashboard
- [ ] Member invitation flow

Note: registration export endpoint exists (`GET /api/v1/events/{id}/registrations/export`), but institution dashboard self-service export UX is still missing.

---

## Moderation & Admin (Filament)

### Filament Resources
- [x] EventResource (CRUD)
- [x] InstitutionResource (CRUD)
- [x] SpeakerResource (CRUD)
- [x] VenueResource (CRUD)
- [x] SeriesResource (CRUD)
- [x] DonationChannelResource (CRUD)
- [x] ReportResource (CRUD)
- [x] ReferenceResource (CRUD)
- [x] TagResource (taxonomy-based tags)
- [x] SpaceResource (supporting location model)
- [ ] TopicResource (replaced by TagResource taxonomy)
- [ ] EventTypeResource (not a standalone resource in current architecture)

### Moderation Workflow
- [x] Moderation Queue page
- [x] Approve/Reject/Needs-changes actions
- [ ] Side-by-side diff view (current vs submitted)
- [ ] SLA visibility in queue UI (countdown/age indicators)
- [ ] Duplicate detection hints in moderation UI

### Trust & Auto-approval
- [ ] Trust score model implementation
- [ ] Auto-approval based on trust score
- [ ] Trust score weekly adjustment job
- [x] High-risk report escalation path (report-driven re-moderation)

---

## API Endpoints

### Public API
- [x] `GET /events` - Search/list events (legacy alias)
- [x] `GET /events/{slug}` - Event details (legacy alias)
- [x] `GET /events/{slug}/calendar.ics` - ICS download (legacy alias)
- [x] `GET /institutions` - List institutions (legacy alias)
- [x] `GET /speakers` - List speakers (legacy alias)
- [x] `GET /api/v1/events` - JSON API events index
- [x] `GET /api/v1/events/{id}` - JSON API event detail
- [x] `POST /api/v1/reports` - Report content

### Authenticated API
- [x] `POST /events/{slug}/register` - Event registration
- [x] `POST /api/v1/event-saves` - Save event
- [x] `DELETE /api/v1/event-saves/{id}` - Unsave event
- [x] `GET/POST/DELETE /api/v1/event-interests` - Event interest (going/interested)
- [x] Saved searches CRUD API (`/api/v1/saved-searches`)
- [x] `GET /api/v1/events/{id}/registrations/export` - Export registrations (CSV)
- [x] `GET /api/v1/user/registrations` - User registrations

---

## Search & Discovery

### Search Functionality
- [x] Text search (title, description, institution, venue, speaker)
- [x] Filter by state/district/subdistrict
- [x] Filter by language/type/audience-related fields
- [x] Filter by institution/speaker
- [x] Date range filter (API + UI)
- [x] Topic filter UI
- [x] Geo-distance sorting ("Near me")
- [x] Typesense integration with DB fallback

### Search UI
- [x] Basic search input
- [x] Advanced filters panel
- [x] Active filter chips
- [x] Sort options UI (time/relevance/distance)
- [x] "Near me" button with geolocation

---

## SEO & Sitemaps

- [x] Sitemap index (`/sitemap.xml` -> redirected to localized sitemap)
- [x] Events sitemap (`/sitemap-events.xml`)
- [x] Institutions sitemap (`/sitemap-institutions.xml`)
- [x] Speakers sitemap (`/sitemap-speakers.xml`)
- [x] Canonical URLs on event pages (explicit canonical tag)
- [x] noindex handling for draft/pending/private pages (meta robots strategy)
- [x] robots.txt configuration
- [x] Meta title/description optimization pass (comprehensive)

---

## Infrastructure & Operations

### Rate Limiting
- [x] Search rate limiting
- [ ] Event submission rate limiting on submit route
- [x] Registration rate limiting
- [x] Rate limit service provider
- [x] Reports rate limiting

### Audit & Logging
- [x] Audit logging (`owen-it/laravel-auditing`)
- [x] Event model observers and cache busting hooks

### Queue & Jobs
- [ ] Horizon dashboard setup
- [x] Event escalation job (`EscalatePendingEvents`)
- [x] Digest email job (`SendSavedSearchDigest`, daily/weekly scheduled)
- [ ] Trust score recalculation job
- [x] Search indexing pipeline (Scout + searchable models)
- [x] Media maintenance schedules (`media-library:clean`, `media-library:regenerate`)

### Notifications
- [x] Event approved notification
- [x] Event rejected notification
- [x] Event needs changes notification
- [x] Event submitted notification (moderators)
- [x] Event escalation notification
- [x] Report resolved notification
- [x] Saved search digest notification

---

## UI/UX Enhancements

### Event Page
- [x] Premium event card/hero treatment
- [x] Prayer-relative timing badge
- [x] Add to Calendar dropdown
- [x] Loading states (implemented in key interactive areas)
- [x] Image gallery/slider
- [x] Related events section
- [x] Share preview modal

### Home Page
- [x] Hero section with search
- [x] "Near me" quick access
- [x] Featured events section
- [x] Browse by state/topic section
- [x] Upcoming events grid
- [x] 7-day date quick filter
- [x] Tonight/near-prayer contextual highlight section
- [x] Submit event CTA section

### General
- [x] Responsive mobile design
- [x] Dark mode support
- [x] Comprehensive loading skeleton coverage
- [x] System-wide toast notifications
- [x] Custom error pages (404, 500)

---

## Phase 2 / Post-MVP Features

- [ ] Series templates (recurring templates)
- [x] Follow speaker/institution
- [ ] Push notifications (web)
- [ ] QR check-in at events
- [ ] Distinguish check-in source in attendance records (`button` vs `qr_scan`)
- [ ] Poster generator

---

## Review Notes (2026-02-28)

1. The previous checklist had architecture drift (`TopicResource`/`EventTypeResource` listed as active MVP resources). Current taxonomy management is centered on `TagResource` + `TagType`.
2. Submit-event throttling is intentionally disabled on the public submit route despite a defined `event-submission` limiter.
3. Trust-score automation remains unimplemented; moderation currently relies on explicit moderation actions + escalation jobs.
