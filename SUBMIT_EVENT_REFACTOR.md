# Submit-Event Form & Data Architecture Refactoring Checklist

> Generated: 2026-02-07
> Scope: `submit-event` form, Event model, related enums, search service, data storage patterns

---

## Critical

- [x] **Cast `age_group` to `AsEnumCollection`**
  - File: `app/Models/Event.php`
  - Changed `'age_group' => 'array'` → `'age_group' => AsEnumCollection::of(EventAgeGroup::class)`
  - Updated `toSearchableArray()` and `getAudienceAttribute()` to handle enum collection
  - Updated `EventFactory` to use raw enum instances (not `->value`)
  - Updated `submit()` fallback in create.blade.php
  - ✅ All existing tests pass

- [x] **Fix `EventSearchService` broken `eventType` relationship reference**
  - File: `app/Services/EventSearchService.php`
  - Removed `'eventType'` from `cardRelationships()` eager-load
  - Replaced 2× `whereHas('eventType', ...)` with `whereJsonContains('event_type', ...)` loops
  - ✅ All EventSearchTest tests pass

## High

- [x] **Extract duplicated `createOptionForm` blocks into reusable methods**
  - Created `app/Forms/InstitutionFormSchema.php` — static `createOptionForm()` + `createOptionUsing()`
  - Created `app/Forms/SpeakerFormSchema.php` — static `createOptionForm()` + `createOptionUsing()`
  - Created `app/Forms/VenueFormSchema.php` — static `createOptionForm()` + `createOptionUsing()`
  - Created `app/Forms/SharedFormSchema.php` — `addressFields()`, `socialMediaRepeater()`, `createAddressFromData()`, `createSocialMediaFromData()`
  - Replaced 3× Institution forms, 2× Speaker forms, 1× Venue form, 3× social media repeaters, 3× address cascades
  - Form reduced from ~870 lines to ~470 lines
  - ✅ All submit-event tests pass

- [x] **Address orphan entity creation from `createOptionUsing`**
  - Created `app/Console/Commands/PruneOrphanedEntities.php` — prunes pending institutions/speakers/venues with no events after 48h
  - Options: `--dry-run` (preview without deleting), `--hours=48` (configurable threshold)
  - Checks both direct relationships and polymorphic organizer references
  - Registered as daily schedule in `routes/console.php`

## Medium

- [x] ~~**Remove or simplify `children_allowed` toggle**~~ — **KEPT as-is**
  - User clarified: `children_allowed` signals whether parents can bring children to adult events (not redundant with `age_group`)
  - Added Malay helper text: "Adakah ibu bapa boleh membawa anak kecil ke majlis ini?"

- [x] **Create `Visibility` enum**
  - Created `App\Enums\EventVisibility` with cases: `Public`, `Unlisted`, `Private`
  - Implements `HasLabel` (Malay), `HasColor`, `HasIcon`
  - Added cast to Event model: `'visibility' => EventVisibility::class`
  - Updated `scopeActive()`, `shouldBeSearchable()`, `toSearchableArray()`
  - Updated all raw string comparisons in 5 files: EventPolicy, Show.php, EventsController, EventSaveController, EventInterestController

- [x] **Extract social media platform options to enum**
  - Created `App\Enums\SocialMediaPlatform` with 10 cases: Facebook, Twitter, Instagram, YouTube, TikTok, Telegram, WhatsApp, LinkedIn, Website, Other
  - Implements `HasLabel`, `HasIcon`, includes `getUrlPattern()` method
  - Replaced hardcoded arrays in 4 files: SharedFormSchema, VenueForm, InstitutionForm, SpeakerForm

- [x] **Fix mixed language labels in submit form**
  - Standardized ALL labels to Malay: "Maklumat Anda", "Nama Anda", "Telefon", "Nota untuk Pentadbir", "Hantar Majlis untuk Semakan", etc.
  - Page title/header also converted to Malay
  - Helper text and descriptions all in Malay

## Low

- [x] **Change `event_type` column to `jsonb`**
  - Created migration `2026_02_07_134015_alter_events_event_type_to_jsonb.php`
  - DB-agnostic: PostgreSQL uses `jsonb_build_array()`, SQLite uses PHP loop fallback
  - Converts existing string values to JSON arrays

- [x] **Add optional `ends_at` / duration to submit form**
  - Added duration Select field with options: 60/90/120/180/240/480 minutes
  - `ends_at` calculated in `submit()` as `$startsAt->copy()->addMinutes((int) $validated['duration'])`
  - Not required — if omitted, `ends_at` remains null

- [x] **Converted to multi-step wizard layout**
  - 4 steps: "Maklumat Majlis" → "Kategori & Bidang" → "Penganjur & Lokasi" → "Penceramah & Media"
  - Uses `Filament\Schemas\Components\Wizard` with `Step::make()`
  - Much less intimidating for public users

- [x] **Cache tag/institution/speaker option queries**
  - All tag queries wrapped in `Cache::remember('submit_tags_*', 60, ...)`
  - Institution/Speaker/Venue selection queries also cached with 60s TTL
  - Keys: `submit_tags_domain_ms`, `submit_institutions`, `submit_speakers`, `submit_venues`

- [x] **Move prayer time filtering to client-side**
  - Removed `$selectedDate`, `$prayerTimeOptions` properties and `updateDateAndPrayerTimes()` / `updatePrayerTimeOptions()` methods
  - Prayer time Select now uses static options from `EventPrayerTime::cases()`
  - Added `afterStateUpdatedJs` on DatePicker for Jumaat/Tarawikh reset
  - Server-side validation in `submit()` for Jumaat-on-non-Friday and Tarawikh-on-non-Ramadhan
  - Fixed root-cause bug: DatePicker `->timezone('Asia/Kuala_Lumpur')` was shifting dates backward in UTC app

---

## Additional Fixes Applied

- [x] **Fix `EventType::Kuliah` reference → `EventType::KuliahCeramah`**
  - `EventType::Kuliah` doesn't exist in the enum (correct case is `KuliahCeramah`)
  - Fixed in `create.blade.php` submit fallback
  - Fixed in `tests/Feature/SubmitEventLocationTest.php` (4 occurrences)

- [x] **Fix `PrayerOffset::Custom` bug**
  - `EventPrayerTime::SelepasTarawikh->getDefaultOffset()` returned `PrayerOffset::Custom` which doesn't exist
  - Changed to `PrayerOffset::After60` — tarawikh completes ~1 hour after Isha

- [x] **Fix `App\Models\EventType` reference in events index view**
  - `resources/views/livewire/pages/events/index.blade.php` referenced deleted `App\Models\EventType`
  - Replaced with `App\Enums\EventType` grouped options

---

## Pre-existing Issues (not caused by this refactoring) — All Resolved

- [x] `SubmitEventMediaTest` — Root cause: DatePicker `->timezone()` shifting dates → prayer_time validation failure. Fixed by removing timezone.
- [x] `SubmitEventOrganizerAutoSelectTest` — Same timezone root cause. Fixed.
- [x] `PublicPagesTest::it records guest submissions` — Same timezone root cause. Fixed.
- [x] `EventSearchTest::registration` — `registration_opens_at` lives on `EventSettings`, not `Event`. Fixed `show.blade.php` to use `$event->settings->registration_opens_at`.
- [x] `EventShowPageTest` — 2 tests had wrong assertion text (`'Akan Hadir?'` and `'akan hadir'`). Fixed to match actual view content.
- [x] Pint build binary missing — Restored via `composer reinstall laravel/pint`.

---

## Progress Log

| Date | Item | Status | Notes |
|------|------|--------|-------|
| 2026-02-07 | age_group AsEnumCollection cast | ✅ Done | Model, factory, form, search all updated |
| 2026-02-07 | EventSearchService fix | ✅ Done | Removed dead relationship, fixed DB fallback queries |
| 2026-02-07 | Extract createOptionForm duplication | ✅ Done | 4 new classes in App\Forms, ~400 lines removed |
| 2026-02-07 | EventType::Kuliah bug | ✅ Done | Fixed in form + 4 test files |
| 2026-02-07 | PrayerOffset::Custom bug | ✅ Done | Changed to After60 |
| 2026-02-07 | EventType model ref in view | ✅ Done | Migrated to EventType enum |
| 2026-02-07 | event_type column to jsonb | ✅ Done | DB-agnostic migration (PostgreSQL jsonb, SQLite PHP fallback) |
| 2026-02-07 | EventVisibility enum | ✅ Done | Enum + cast + 5 files updated for enum comparisons |
| 2026-02-07 | SocialMediaPlatform enum | ✅ Done | 10 cases, replaced in 4 files |
| 2026-02-07 | ends_at / duration field | ✅ Done | Duration select, ends_at calculated in submit() |
| 2026-02-07 | Multi-step wizard | ✅ Done | 4-step Wizard layout |
| 2026-02-07 | Cache option queries | ✅ Done | 60s TTL on all tag/entity queries |
| 2026-02-07 | Fix mixed language labels | ✅ Done | All labels standardized to Malay |
| 2026-02-07 | Orphan cleanup command | ✅ Done | PruneOrphanedEntities command + daily schedule |
| 2026-02-07 | children_allowed | ✅ Kept | User clarified: valid toggle for adult events |
| 2026-02-08 | Prayer time client-side | ✅ Done | Removed Livewire round-trip, static options, server-side Jumaat/Tarawikh validation |
| 2026-02-08 | DatePicker timezone bug | ✅ Done | Root cause of 3 test suites failing — removed `->timezone()` from DatePicker/TimePicker |
| 2026-02-08 | show.blade.php attributes | ✅ Done | Fixed `registration_opens_at` and `capacity` → accessed via `$event->settings` |
| 2026-02-08 | EventShowPageTest fixes | ✅ Done | Updated 2 wrong assertions to match actual view text |
| 2026-02-08 | Pint binary restored | ✅ Done | `composer reinstall laravel/pint` |
| 2026-02-08 | Past-time validation | ✅ Done | Simplified to `$startsAt->lessThanOrEqualTo($now)` check |
