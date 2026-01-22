# Majlisilmu MVP Feature Checklist

**Last Updated:** 2026-01-11
**Document Purpose:** Track implementation progress for MVP features based on Product Documentation

---

## 📊 Progress Summary

| Category | Completed | Total | Progress |
|----------|-----------|-------|----------|
| Public Pages | 9 | 11 | 82% |
| User Features | 4 | 7 | 57% |
| Admin/Filament | 8 | 10 | 80% |
| API Endpoints | 8 | 8 | 100% |
| Search & Discovery | 5 | 8 | 63% |
| Moderation | 2 | 4 | 50% |
| Infrastructure | 6 | 7 | 86% |
| **Overall** | **42** | **55** | **76%** |

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
- [x] **Add to Calendar** (Google, Apple/iCal, Outlook, Office 365, Yahoo) ✨ NEW
- [x] Prayer-relative timing display ✨ NEW
- [x] "Near me tonight" geo-location button ✨ NEW
- [ ] Event distance display in search results

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
- [ ] CAPTCHA integration (reCAPTCHA/Turnstile)
- [ ] Poster upload on submission (Phase 3)

---

## 👤 User Features (Requires Authentication)

### Authentication
- [x] User registration page ✨ NEW
- [x] User login page ✨ NEW
- [x] Password reset flow ✨ NEW
- [x] Email verification (Built-in Laravel)
- [ ] Social login (Google/Facebook) - Optional

### Saved Events & Searches
- [x] Save/bookmark events (AJAX call) ✨ NEW
- [x] View saved events list (via API / Dashboard)
- [x] Saved searches CRUD API (backend exists)
- [ ] Saved searches UI page
- [ ] Daily digest email job
- [ ] Weekly digest email job
- [ ] Email preference settings

### User Dashboard
- [ ] User profile page
- [ ] My registrations list
- [ ] My saved events list
- [ ] My saved searches list

---

## 🏛️ Institution Dashboard

### Institution Management
- [ ] Institution admin dashboard home
- [ ] Create/edit events for institution
- [ ] View institution events list
- [ ] Manage institution profile
- [ ] Manage donation accounts
- [ ] View event registrations
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
- [x] DonationAccountResource (CRUD)
- [x] ReportResource (CRUD)

### Moderation Workflow
- [x] Moderation Queue page
- [x] Approve/Reject/Needs-changes actions
- [ ] Side-by-side diff view (current vs submitted)
- [ ] SLA visibility (time in queue, escalation countdown)
- [ ] Duplicate detection hints

### Trust & Auto-approval
- [x] Trust score model (on institutions/speakers)
- [ ] Auto-approval based on trust score
- [ ] Trust score weekly adjustment job
- [ ] High-risk report escalation

---

## 🔌 API Endpoints

### Public API
- [x] `GET /events` - Search/list events
- [x] `GET /events/{slug}` - Event details
- [x] `GET /events/{slug}/calendar.ics` - ICS download ✨ NEW
- [x] `GET /institutions` - List institutions
- [x] `GET /speakers` - List speakers
- [ ] `GET /api/events` - JSON API for events

### Authenticated API
- [x] `POST /events/{slug}/register` - Event registration
- [x] `POST /api/event-saves` - Save event
- [x] `DELETE /api/event-saves/{id}` - Unsave event ✨ NEW
- [x] Saved searches CRUD (backend exists)
- [ ] `POST /reports` - Report content
- [ ] `GET /api/user/registrations` - User's registrations

---

## 🔍 Search & Discovery

### Search Functionality
- [x] Text search (title, description)
- [x] Filter by state/district
- [x] Filter by language/genre/audience
- [x] Filter by institution/speaker
- [ ] Date range filter
- [ ] Topic filter UI
- [ ] Geo-distance sorting ("Near me")
- [ ] Typesense integration (Scout driver configured)

### Search UI
- [x] Basic search input
- [ ] Advanced filters dropdown/modal
- [ ] Filter chips (active filters display)
- [ ] Sort options (time, relevance, distance)
- [x] "Near me" button with geolocation ✨ NEW

---

## 🗺️ SEO & Sitemaps

- [x] Sitemap index (`/sitemap.xml`)
- [x] Events sitemap (`/sitemap-events.xml`)
- [x] Institutions sitemap (`/sitemap-institutions.xml`)
- [x] Speakers sitemap (`/sitemap-speakers.xml`)
- [x] Canonical URLs on event pages
- [x] noindex for draft/pending/private events
- [ ] robots.txt configuration
- [ ] Meta title/description optimization

---

## ⚙️ Infrastructure & Operations

### Rate Limiting
- [x] Search rate limiting
- [x] Event submission rate limiting
- [x] Registration rate limiting
- [x] Rate limit service provider

### Audit & Logging
- [x] Audit logging (owen-it/laravel-auditing)
- [x] Model observers for events

### Queue & Jobs
- [ ] Horizon dashboard setup
- [x] Event escalation job
- [ ] Digest email job (daily/weekly)
- [ ] Trust score recalculation job
- [ ] Search indexing job

### Notifications
- [x] Event approved notification
- [x] Event rejected notification
- [x] Event needs changes notification
- [x] Event submitted notification (moderators)
- [x] Event escalation notification
- [ ] Digest email notifications

---

## 🎨 UI/UX Enhancements

### Event Page
- [x] Premium card design
- [x] Prayer-relative timing badge ✨ NEW
- [x] Add to Calendar dropdown ✨ NEW
- [ ] Image gallery/slider
- [ ] Related events section
- [ ] Share preview modal

### Home Page
- [x] Hero section with search ✨ NEW
- [x] "Near me tonight" quick access ✨ NEW
- [x] Featured events carousel ✨ NEW
- [x] Browse by state/topic ✨ NEW
- [x] Upcoming events grid ✨ NEW
- [x] 7-day date quick filter ✨ NEW
- [x] Tonight's events banner ✨ NEW
- [x] Submit event CTA section ✨ NEW

### General
- [x] Responsive mobile design
- [x] Dark mode support (Tailwind)
- [ ] Loading states/skeletons
- [ ] Toast notifications
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

- [ ] Comprehensive test coverage (target: >80%)
- [ ] PHPStan level 5+ compliance
- [ ] API documentation (OpenAPI/Swagger)
- [ ] Performance optimization (query optimization)
- [ ] Redis caching for hot paths
- [ ] CDN setup for assets

---

## 📝 Notes

### Recently Completed (This Session)
1. ✅ Prayer-relative timing feature
2. ✅ Add to Calendar feature
3. ✅ Complete Home Page redesign
4. ✅ User Authentication (Login, Register, Password Reset)
5. ✅ Save Event functionality (API + UI with AJAX)
6. ✅ Renamed "Bahasa Melayu" to "Melayu", "Basa Jawa" to "Jawa"

### Next Priority Items
1. 🔴 Search filters UI enhancement (advanced filters modal)
2. 🟡 Institution dashboard for admins
3. 🟡 Digest email implementation
4. 🟡 Event distance display in search results

### Blockers
- None currently identified

---

**Legend:**
- [x] Completed
- [ ] Not started
- 🔴 High priority
- 🟡 Medium priority
- ✨ Recently added
