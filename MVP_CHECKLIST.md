# Majlisilmu MVP Feature Checklist

**Last Updated:** 2026-02-09
**Document Purpose:** Track implementation progress for MVP features based on Product Documentation

---

## 📊 Progress Summary

| Category | Completed | Total | Progress |
|----------|-----------|-------|----------|
| Public Pages | 13 | 13 | 100% |
| User Features | 8 | 8 | 100% |
| Admin/Filament | 12 | 16 | 75% |
| API Endpoints | 13 | 13 | 100% |
| Search & Discovery | 7 | 10 | 70% |
| Moderation | 2 | 5 | 40% |
| Infrastructure | 8 | 10 | 80% |
| **Overall** | **66** | **75** | **88%** |

---

## 🌐 Public Pages & Features

### Event Discovery & Display
- [x] Event listing page (`/events`)
- [x] Event detail page (`/events/{slug}`)
- [x] Event description, speakers, topics display
- [x] Waze / Google Maps navigation links
- [x] Livestream / Recording links
- [x] Donation account display
- [x] Registration form with validation
- [x] JSON-LD structured data (SEO)
- [x] OpenGraph / Twitter Cards
- [x] Share button (native Web Share API)
- [x] **Add to Calendar** (Google, Apple/iCal, Outlook, Office 365, Yahoo) ✨
- [x] Prayer-relative timing display ✨
- [x] "Near me tonight" geo-location button ✨
- [x] Event distance display in search results ✨

### Institutions & Speakers
- [x] Institutions listing page (`/institutions`)
- [x] Institution detail page (`/institutions/{slug}`)
- [x] Speakers listing page (`/speakers`)
- [x] Speaker detail page (`/speakers/{slug}`)
- [x] Series detail page (`/series/{slug}`)

### Public Submission
- [x] Event submission form (`/submit-event`)
- [x] Submission success page
- [x] Rate limiting on submissions
- [x] CAPTCHA integration (reCAPTCHA/Turnstile) ✨
- [ ] Poster upload on submission (Phase 3)

---

## 👤 User Features (Requires Authentication)

### Authentication
- [x] User registration page ✨
- [x] User login page ✨
- [x] Password reset flow ✨
- [x] Email verification (Built-in Laravel)
- [x] Social login (Google) ✨

### Saved Events & Searches
- [x] Save/bookmark events (AJAX call) ✨
- [x] View saved events list (via API / Dashboard)
- [x] Saved searches CRUD API (backend exists)
- [x] Event interest marking API (going/interested) ✨
- [x] Saved searches UI page ✨
- [x] Daily digest email job (scheduled, verified) ✨
- [x] Weekly digest email job (scheduled, verified) ✨
- [x] Email preference settings ✨

### User Dashboard
- [x] User profile page
- [x] My registrations list
- [x] My saved events list ✨
- [x] My saved searches list ✨

---

## 🏛️ Institution Dashboard

### Institution Management
- [x] Institution admin dashboard home
- [ ] Create/edit events for institution
- [x] View institution events list
- [ ] Manage institution profile
- [ ] Manage donation accounts
- [x] View event registrations
- [ ] Export attendee list (CSV/Excel)

### Member Management
- [ ] Add/remove institution members
- [ ] Role assignment (admin, editor, viewer)
- [ ] Member invitation flow

---

## 🛡️ Moderation & Admin (Filament)

### Filament Resources
- [x] EventResource (CRUD)
- [x] InstitutionResource (CRUD)
- [x] SpeakerResource (CRUD)
- [x] VenueResource (CRUD)
- [x] TopicResource (CRUD)
- [x] SeriesResource (CRUD)
- [x] DonationChannelResource (CRUD) ✨
- [x] ReportResource (CRUD)
- [x] EventTypeResource (CRUD) ✨
- [x] ReferenceResource (CRUD) ✨

### Moderation Workflow
- [x] Moderation Queue page
- [x] Approve/Reject/Needs-changes actions
- [ ] Side-by-side diff view (current vs submitted)
- [ ] SLA visibility (time in queue, escalation countdown)
- [ ] Duplicate detection hints

### Trust & Auto-approval
- [ ] Trust score model implementation (job exists but empty)
- [ ] Auto-approval based on trust score
- [ ] Trust score weekly adjustment job
- [ ] High-risk report escalation (partial - in ReportController)

---

## 🔌 API Endpoints

### Public API
- [x] `GET /events` - Search/list events
- [x] `GET /events/{slug}` - Event details
- [x] `GET /events/{slug}/calendar.ics` - ICS download ✨
- [x] `GET /institutions` - List institutions
- [x] `GET /speakers` - List speakers
- [x] `GET /api/v1/events` - JSON API for events ✨
- [x] `GET /api/v1/events/{id}` - Single event JSON API ✨
- [x] `POST /api/v1/reports` - Report content ✨

### Authenticated API
- [x] `POST /events/{slug}/register` - Event registration
- [x] `POST /api/v1/event-saves` - Save event
- [x] `DELETE /api/v1/event-saves/{id}` - Unsave event ✨
- [x] `GET/POST/DELETE /api/v1/event-interests` - Event interest (going/interested) ✨
- [x] Saved searches CRUD API (`/api/v1/saved-searches`)
- [x] `GET /api/v1/events/{id}/registrations/export` - Export registrations ✨
- [x] `GET /api/v1/user/registrations` - User's registrations ✨

---

## 🔍 Search & Discovery

### Search Functionality
- [x] Text search (title, description)
- [x] Filter by state/district
- [x] Filter by language/genre/audience
- [x] Filter by institution/speaker
- [x] Date range filter (API only - starts_after, starts_before, etc.) ✨
- [x] Date range filter UI ✨
- [x] Topic filter UI (events advanced filters)
- [x] Geo-distance sorting ("Near me") via Typesense
- [x] Typesense integration (Scout driver configured)

### Search UI
- [x] Basic search input
- [x] Advanced filters dropdown/modal
- [x] Filter chips (active filters display)
- [x] Sort options UI (time, relevance, distance)
- [x] "Near me" button with geolocation ✨

---

## 🗺️ SEO & Sitemaps

- [x] Sitemap index (`/sitemap.xml`)
- [x] Events sitemap (`/sitemap-events.xml`)
- [x] Institutions sitemap (`/sitemap-institutions.xml`)
- [x] Speakers sitemap (`/sitemap-speakers.xml`)
- [x] Canonical URLs on event pages
- [x] noindex for draft/pending/private events
- [x] robots.txt configuration ✨
- [ ] Meta title/description optimization

---

## ⚙️ Infrastructure & Operations

### Rate Limiting
- [x] Search rate limiting
- [x] Event submission rate limiting
- [x] Registration rate limiting
- [x] Rate limit service provider
- [x] Reports rate limiting ✨

### Audit & Logging
- [x] Audit logging (owen-it/laravel-auditing)
- [x] Model observers for events

### Queue & Jobs
- [ ] Horizon dashboard setup (not installed)
- [x] Event escalation job (EscalatePendingEvents)
- [x] Digest email job (SendSavedSearchDigest - daily/weekly scheduled)
- [ ] Trust score recalculation job (exists but empty)
- [x] Search indexing job (via Scout)

### Notifications
- [x] Event approved notification
- [x] Event rejected notification
- [x] Event needs changes notification
- [x] Event submitted notification (moderators)
- [x] Event escalation notification
- [x] Report resolved notification ✨
- [x] Saved search digest notification ✨

---

## 🎨 UI/UX Enhancements

### Event Page
- [x] Premium card design
- [x] Prayer-relative timing badge ✨
- [x] Add to Calendar dropdown ✨
- [x] wire:loading states (minimal) ✨
- [ ] Image gallery/slider
- [ ] Related events section
- [ ] Share preview modal

### Home Page
- [x] Hero section with search ✨
- [x] "Near me tonight" quick access ✨
- [x] Featured events carousel ✨
- [x] Browse by state/topic ✨
- [x] Upcoming events grid ✨
- [x] 7-day date quick filter ✨
- [x] Tonight's events banner ✨
- [x] Submit event CTA section ✨

### General
- [x] Responsive mobile design
- [x] Dark mode support (Tailwind)
- [ ] Loading states/skeletons (comprehensive)
- [ ] Toast notifications (system-wide)
- [ ] Error pages (404, 500)

---

## 📱 Phase 2 Features (Future)

These are documented but not for MVP:

- [ ] Series templates (recurring scheduling)
- [ ] Follow speaker/institution
- [ ] Push notifications (web)
- [ ] QR check-in at events
- [ ] Poster generator

---

## 📋 Phase 3 Features (Future)

- [ ] Poster-to-event extraction (AI/OCR)
- [ ] Widgets/embeds for masjid sites
- [ ] Partner API (read-only)
- [ ] Reputation-based auto-approval

---

## 🔧 Technical Debt & Improvements

- [x] Test suite established (37 test files - Feature & Unit)
- [ ] Comprehensive test coverage (target: >80%)
- [ ] PHPStan level 5+ compliance
- [ ] API documentation (OpenAPI/Swagger)
- [ ] Performance optimization (query optimization)
- [ ] Redis caching for hot paths
- [ ] CDN setup for assets

---

## 📝 Notes

### Recently Verified (This Review - 2026-02-02)
1. ✅ Social login (Google) via SocialiteController
2. ✅ robots.txt properly configured
3. ✅ JSON API endpoints (/api/v1/events)
4. ✅ Report API with high-risk handling
5. ✅ Event interests API (going/interested)
6. ✅ Registration export API
7. ✅ Date range filter in API (starts_after, starts_before)
8. ✅ Additional Filament Resources: EventTypeResource, ReferenceResource
9. ✅ SendSavedSearchDigest job with daily/weekly scheduling
10. ✅ 37 test files covering features
11. ✅ CAPTCHA (Turnstile) integrated in submit-event flow
12. ✅ Saved searches web UI page (create/list/delete/run)
13. ✅ Date range filter UI in events listing page
14. ✅ Distance display + DB fallback geo-distance computation in search results

### Corrections Made
- "DonationAccountResource" → "DonationChannelResource"
- Trust score model marked as NOT implemented (job is empty)
- Digest email jobs marked as scheduled (exist in routes/console.php)
- Horizon NOT installed

### Next Priority Items
1. 🔴 User & Institution dashboards (profile, my-events, my-registrations)
2. 🔴 `GET /api/v1/user/registrations` endpoint + UI consumption
3. 🔴 Advanced filters modal/dropdown + topic filter UI
4. 🟡 Trust score implementation (job + scoring model + moderation hooks)
5. 🟡 Error pages (404, 500) and recovery UX

### Technical Observations
- Tests: 37 test files (34 Feature, 3 Unit)
- Services: CalendarService, EventSearchService, ModerationService, PrayerTimeService
- Jobs: AdjustTrustScores (empty), EscalatePendingEvents, SendSavedSearchDigest
- Scheduled: Daily digest at 8am, Weekly digest Monday 8am, Hourly escalation checks

### Blockers
- None currently identified

---

**Legend:**
- [x] Completed
- [ ] Not started
- ✨ Verified/Added this review
- 🔴 High priority
- 🟡 Medium priority
