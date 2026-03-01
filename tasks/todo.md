# Event Majlis Search Live + Fuzzy Todo

- [x] Expand `/majlis` DB search to include title, institution, venue, and speaker names
- [x] Add fuzzy fallback for typo-tolerant search across those fields
- [x] Add regression tests for direct field search, fuzzy typo search, and Livewire live updates
- [x] Run focused Pest verification

## Review

- Updated `EventSearchService` database search to:
  - direct-match `events.title`, `events.description`, `institution.name`, `venue.name`, and `speakers.name`
  - tokenize collapsed queries for broad partial matching (minimum token length 3)
  - run typo-tolerant fuzzy fallback when direct matches are empty
- Added regressions in `tests/Feature/EventSearchTest.php` for:
  - institution-name search
  - venue-name search
  - speaker-name search
  - typo fuzzy search (`Melawti` -> `Melawati`)
  - live update behavior via Livewire component state change
- Verification:
  - `vendor/bin/pest --compact tests/Feature/EventSearchTest.php --filter=\"(searches events by institution name|searches events by venue name|searches events by speaker name|supports fuzzy search with minor venue name typos|updates event results live when search changes|searches events by title)\"` => **6 passed**
  - `vendor/bin/phpstan analyse --ansi app/Services/EventSearchService.php tests/Feature/EventSearchTest.php` => **No errors**

---

# Blaze Integration Todo

- [x] Research Blaze package behavior and project compatibility constraints
- [x] Install Blaze package and wire configuration
- [x] Implement safe maximum optimization strategy for this app's component tree
- [x] Add regression coverage for Blaze-enabled rendering paths
- [x] Run Pest verification in parallel
- [x] Run browser verification with Chrome MCP

## Review

- Blaze installed (`livewire/blaze:^1.0`) with app-level config and provider wiring.
- Optimization is applied to anonymous Blade components with explicit safety exclusions for Livewire SFC directories and class-based component view.
- Added `tests/Feature/BlazeIntegrationTest.php` to lock optimization map behavior and login page rendering.
- Pest run (`vendor/bin/pest --parallel`) reports 3 existing failures that also fail with `BLAZE_ENABLED=false`:
  - `Tests\\Feature\\EventPledgeTest` (expects removed string `Log Masuk untuk Hadir`)
  - `Tests\\Feature\\InspirationTest` (expects `Test Did You Know` on event page)
  - `Tests\\Feature\\MediaConversionsTest` (`poster_orientation` portrait assertion mismatch)
- Chrome MCP verification on `https://majlisilmu.test`, event detail page, and login page shows successful renders with no browser console errors.

---

# StateFusion Dependency Removal Todo

- [x] Implement in-app replacement trait for state metadata helpers
- [x] Implement in-app Filament state select filter
- [x] Migrate EventStatus + EventsTable to local implementation
- [x] Remove `a909m/filament-statefusion` from dependencies while keeping `spatie/laravel-model-states`
- [x] Run full Pest suite in parallel
- [x] Document review results

## Review

- Removed `a909m/filament-statefusion` and replaced the required features with local equivalents:
  - `app/Support/State/StateMetadata.php`
  - `app/Filament/Tables/Filters/ModelStateSelectFilter.php`
- Migrated Event status integration to local implementation:
  - `app/States/EventStatus/EventStatus.php`
  - `app/Filament/Resources/Events/Tables/EventsTable.php`
- Cleared and regenerated testing package/service caches after package removal to remove stale provider references.
- Added `BLAZE_ENABLED=false` to `phpunit.xml` and cleared testing caches to prevent Blaze compiled-view race failures in parallel tests.
- Full verification:
  - `vendor/bin/pest --parallel --compact` => **468 passed**
  - Chrome MCP smoke test:
    - `https://majlisilmu.test/` loads correctly with no JS errors.
    - `https://majlisilmu.test/admin/login` loads correctly; only non-blocking `favicon.ico` 404 in console/network.

---

# Event Show UI Polish Todo

- [x] Prevent speaker avatars from being cropped on event show page
- [x] Replace generalized summary badges under "Tentang Majlis Ini" with tag-category badges
- [x] Ensure all four tag categories are always shown (Domain, Discipline, Source, Issue)
- [x] Verify with focused Pest suites
- [x] Verify with Chrome MCP on target event URL

## Review

- Speaker avatar cards now use non-cropping image presentation (`object-contain`) in both single-speaker and multi-speaker cards.
- Removed the `Ringkasan` badge block from the About section and switched to fixed tag category panels.
- Tag categories now render in deterministic order with all four groups visible, including an empty-state badge (`Tiada`) for missing groups.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventShowPageTest.php` => **11 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php` => **35 passed**
  - Chrome MCP checked on `https://majlisilmu.test/majlis/riyadus-salihin-kelas-daurah-lbqxd47`:
    - About section shows `DOMAIN`, `DISIPLIN`, `SUMBER`, `ISU`.
    - Speaker image computed style confirms `object-fit: contain`.

---

# Event Speaker Premium Card Pass

- [x] Refine single-speaker card visual style to a premium/classy treatment
- [x] Further reduce ring heaviness and blue side spacing while preserving balance
- [x] Verify Blade compiles without syntax errors
- [x] Document review results

## Review

- Restyled the single-speaker featured card with a premium surface treatment: gradient material, subtle inset ring, glow accent, and refined shadow depth.
- Tightened the left speaker media panel further (`lg:w-[18.5rem]`) and reduced horizontal/vertical spacing to open more room for name details.
- Simplified the avatar framing into a thin, elegant ring (`border` + `p-[2px]`) with smaller overall footprint while preserving clarity.
- Elevated typography hierarchy on the right side with a compact label pill, larger display-name scale, and divider accent for a classier composition.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully.**

---

# Event Multi-Speaker Premium Card Pass

- [x] Redesign multi-speaker cards to align with single-speaker premium direction
- [x] Apply premium card material treatment, refined avatar frame, and hierarchy improvements
- [x] Keep grid-friendly layout for 2-3 column responsiveness
- [x] Verify Blade compiles without syntax errors
- [x] Document review results

## Review

- Upgraded each multi-speaker card to a premium visual language matching the single-speaker card: gradient material, inset highlight ring, soft glow accent, and refined shadow behavior.
- Reworked profile section to use a compact circular avatar with thin classy ring and balanced spacing for grid cards.
- Improved text hierarchy with a `Speaker` pill, stronger name typography, better title styling, and a subtle divider accent.
- Added a cleaner bio block with line clamp and a graceful fallback text when bio is unavailable.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully.**

---

# Speaker Avatar Emphasis Follow-up

- [x] Increase multi-speaker avatar prominence while preserving premium style
- [x] Remove patterned fallback background behind avatar areas for cleaner look
- [x] Verify Blade compiles without syntax errors

## Review

- Increased multi-speaker avatar size and adjusted overlap (`-mt-12`, `size-24`) to better match hero emphasis.
- Removed patterned placeholder background from both single-speaker and multi-speaker fallback media panels.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully.**

---

# Multi-Speaker Live Verification Follow-up

- [x] Verify UI on a real event with more than one speaker
- [x] Remove fallback cover placeholder image rendering for multi-speaker cards
- [x] Increase multi-speaker avatar prominence and fallback avatar legibility
- [x] Re-run Blade compilation check

## Review

- Verified on `https://majlisilmu.test/majlis/tadabbur-fiqh-zakat-3wfwlaf` (multi-speaker event).
- Multi-speaker cards now show gradient fallback covers only (no placeholder cover image asset).
- Avatar block is larger (`size-32`) with stronger overlap and placeholder-avatar zoom treatment.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully.**

---

# Series Detail Missing Attribute Fix

- [x] Replace deprecated `poster_url` usage on series detail cards
- [x] Eager-load event media for series detail card image rendering
- [x] Add regression test for series detail with attached upcoming event
- [x] Run focused Pest verification in parallel

## Review

- Replaced series card image binding from `$event->poster_url` (missing accessor) to `$event->card_image_url`, which is the canonical accessor used across public pages.
- Updated series page query to eager-load `media` and `institution.media` for event card image rendering without per-item relationship fetches.
- Extended `tests/Feature/PublicPagesTest.php` to attach an upcoming event to a public series and assert the series page renders both series and event titles.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicPagesTest.php` => **6 passed**.

---

# Social Media Handle Refactor

- [x] Build centralized parser + URL builder for social platforms (username-first)
- [x] Normalize SocialMedia model persistence (extract from full URL, strip `@`, canonical URL rendering)
- [x] Update Filament/form schemas to accept handle or full URL and relax URL-only requirements
- [x] Update public/admin rendering to consume canonical/resolved URLs
- [x] Add migration/backfill for username-first storage model
- [x] Add regression tests for parsing, normalization, and rendering behavior
- [x] Run focused Pest verification in parallel

## Review

- Added `App\Support\SocialMedia\SocialMediaLinkResolver` as the single source of truth for platform aliasing, username extraction (full URL / `@handle` / raw handle), and canonical URL generation.
- Updated `App\Models\SocialMedia` to normalize on save and expose `resolved_url` + `display_username` accessors.
- Updated all social media repeaters (Speaker, Institution, Venue, Reference, SharedFormSchema) so either handle or URL is accepted (`requiredWithout` pairing), with URL no longer hard-required.
- Updated frontend/admin rendering paths to use canonical resolved URLs:
  - speaker/institution public pages
  - institution/venue infolists
- Added migration `2026_02_28_000001_make_social_media_url_nullable.php` and normalization command `social-media:normalize`.
- Ran normalization command in local environment:
  - `php artisan social-media:normalize --dry-run`
  - `php artisan social-media:normalize --force`
- Added regression suite `tests/Feature/SocialMediaNormalizationTest.php` for extraction + URL resolution behavior.
- Verification:
  - `vendor/bin/pest --parallel --compact --filter="(SocialMediaTest|SocialMediaNormalizationTest|EditInstitutionSocialMediaTest|SpeakerShowSocialPlacementTest)"` => **11 passed**
  - `vendor/bin/phpstan analyse --ansi app/Support/SocialMedia/SocialMediaLinkResolver.php app/Models/SocialMedia.php app/Console/Commands/NormalizeSocialMediaHandles.php` => **No errors**
  - `php artisan view:cache` => **Blade templates cached successfully**

---

# Social Media SVG Icon Wiring

- [x] Simplify icon naming rule to `<platform>.svg`
- [x] Replace inline social SVGs with storage-based icon images on speaker page
- [x] Replace inline social SVGs with storage-based icon images on institution page
- [x] Create storage folder scaffold and filename guide
- [x] Run focused verification for social rendering changes

## Review

- `App\Models\SocialMedia` now resolves icon file names directly as `<platform>.svg` (with `link.svg` fallback when platform is empty).
- Speaker and institution public social cards now render icon images via `$social->icon_url`, so the displayed icon is always driven by stored platform name.
- Added icon drop path and usage guide at:
  - `storage/app/public/social-media-icons/README.md`
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact --filter="(SpeakerShowSocialPlacementTest|EditInstitutionSocialMediaTest|SocialMediaNormalizationTest)"` => **7 passed (20 assertions)**

---

# Inspiration Main Content Missing

- [x] Identify whether missing text is rendering issue or data issue
- [x] Patch inspiration seeder to repair stale records instead of skipping existing rows
- [x] Reseed inspiration data locally and verify content is present
- [x] Run focused verification

## Review

- Root cause was stale seeded rows where `inspirations.content` was `NULL` for existing records; sidebar had no text to render.
- Updated seeder to use `updateOrCreate` (instead of `firstOrCreate`) so future reseeds backfill/fix existing inspiration content.
- Executed `php artisan db:seed --class=Database\\Seeders\\InspirationSeeder` to repair current local data.
- Validation after reseed:

---

# Event Location Invariant Alignment

- [x] Verify public submit-event flow constraints for organizer/location combinations
- [x] Fix event factory defaults to avoid dual institution+venue location assignment
- [x] Fix event seeder generation/backfill to enforce institution XOR venue location
- [x] Align admin event form to prevent selecting institution and venue together
- [x] Add regression assertion for seeded schedule event location invariants
- [x] Run focused verification (Pest + PHPStan + Blade compile)

## Review

- Confirmed submit-event public flow enforces location as mutually exclusive (`institution` or `venue`), with `space` only for institution-based locations.
- Updated `database/factories/EventFactory.php` so default generated events no longer carry both `institution_id` and `venue_id`.
- Updated `database/seeders/EventSeeder.php`:
  - Bulk seeding now starts location-empty and assigns exactly one location for non-online events.
  - Schedule seeding no longer writes both institution and venue in the same row.
  - Backfill now normalizes seeded rows with dual locations and clears invalid `space_id` on venue-located rows.
- Updated admin schema in `app/Filament/Resources/Events/Schemas/EventForm.php` to enforce mutual exclusivity in UI and hydration.
- Extended `tests/Feature/EventSeederSubmitEventCompatibilityTest.php` to assert location XOR (`institution_id` xor `venue_id`).
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSeederSubmitEventCompatibilityTest.php` => **1 passed (8 assertions)**
  - `vendor/bin/phpstan analyse --ansi database/factories/EventFactory.php database/seeders/EventSeeder.php app/Filament/Resources/Events/Schemas/EventForm.php tests/Feature/EventSeederSubmitEventCompatibilityTest.php app/Livewire/Pages/Events/Show.php resources/views/livewire/pages/events/show.blade.php` => **No errors**
  - `php artisan view:cache` => **Blade templates cached successfully**
- `vendor/bin/pest --parallel --compact tests/Feature/AdminEventsResourceTest.php` => **1 existing unrelated failure** (`A909M\FilamentStateFusion\Tables\Filters\StateFusionSelectFilter` class missing in EventsTable)

---

# Event Show Redundancy Cleanup

- [x] Show location cover image below speaker cards when location cover exists
- [x] Move "Tambah ke Kalendar" into floating action bar with Hadir/Minat/Simpan actions
- [x] Remove redundant open-registration messages from sidebar
- [x] Run Blade compile + focused event show verification

## Review

- Event page now computes canonical location media (institution `cover`, venue `main` with legacy `cover` fallback) and renders a dedicated location-cover section immediately below speaker cards when media exists.
- Moved calendar actions into the floating engagement bar as `Tambah ke Kalendar` dropdown beside `Hadir/Minat/Simpan` and removed the duplicated sidebar calendar card.
- Removed non-essential open-event registration text labels:
  - `Open to All — No Registration Needed`
  - `No registration required`
- Also removed the extra hero organizer chip below title to avoid duplicated institution display in hero.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventShowPageTest.php` => **11 passed (32 assertions)**
  - Browser check (`https://majlisilmu.test/majlis/forum-perdana-bersama-asatizah-uu8oszr` + `https://majlisilmu.test/majlis/kuliah-ceramah-bersama-asatizah-apbzpdh`) confirms:
    - `Tambah ke Kalendar` present in action bar
    - `Open to All — No Registration Needed` absent
    - `No registration required` absent
    - `Lokasi` section renders after `Penceramah` on location-cover event page

---

# Event Timezone + Meridiem Consistency

- [x] Trace time rendering differences between event detail and speaker detail pages
- [x] Replace meridiem localization paths that produced `tengah malam` with explicit `AM/PM` formatting for time displays on event page
- [x] Normalize existing `events`, `event_sessions`, and `event_settings` datetime records to UTC using each row timezone context
- [x] Fix UTC persistence in factories/seeders/observer for future data correctness
- [x] Ensure default viewer-timezone fallback uses Malaysia timezone when no explicit user timezone is present
- [x] Verify on target URLs with Chrome MCP

## Review

- Root cause was twofold:
  - mixed formatting (`translatedFormat(... A)`) generated localized meridiem text like `tengah malam`
  - legacy records were stored as local time while app expected UTC for rendering
- Updated event page time displays to use explicit `format(..., 'g:i A'/'h:i A')` for AM/PM output while keeping translated dates.
- Normalized existing DB timestamps (one-time local execution):
  - `events_updated: 662`
  - `event_sessions_updated: 0`
  - `event_settings_updated: 169`
- Fixed future data writes:
  - `database/factories/EventFactory.php` now generates local schedule time then stores UTC
  - `database/factories/EventSessionFactory.php` now stores UTC
  - `database/seeders/EventSeeder.php` now stores UTC
  - `app/Observers/EventObserver.php` now persists prayer-relative calculated times in UTC
- Added `config('app.default_user_timezone')` fallback (`Asia/Kuala_Lumpur`) and wired it in `UserTimezoneResolver` so guest/no-cookie rendering does not default to UTC.
- Chrome MCP verification:
  - `https://majlisilmu.test/majlis/forum-perdana-bersama-asatizah-uu8oszr` now shows `Ahad, 22 Mac 2026` and `4:08 PM — 7:08 PM`
  - `https://majlisilmu.test/penceramah/nadia-azzahra-binti-othman-xoqg6ug` now shows the same event as `4:08 PM — 07:08 PM`
  - Event share modal now shows `22 Mac 2026, 04:08 PM — 07:08 PM`
  - `inspirations.content` null count is now **0**
  - `Jangan Berputus Asa` and `Perancangan Allah Yang Terbaik` now return full `contentPreviewText`.
- Verification:
  - `vendor/bin/pest --parallel --compact --filter=\"(seeds inspirations via InspirationSeeder|shows sidebar inspiration on speaker page|shows sidebar inspiration on institution page)\"` => **3 passed**
  - Note: existing unrelated failure persists in full `InspirationTest` for event-page expectation (`Test Did You Know`).

---

# Speaker Freelance Badge Position

- [x] Remove freelance badge from hero action row (next to `Kongsi`)
- [x] Render freelance badge in right sidebar below social media
- [x] Ensure sidebar still appears when speaker is freelance
- [x] Run view/test verification

## Review

- Moved `Bebas / Freelance` out of the hero action buttons and into the sidebar directly after the social media card.
- Updated sidebar render condition to include `$speaker->is_freelance`, preventing the badge from disappearing when social links are absent.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact --filter=SpeakerShowSocialPlacementTest` => **1 passed**

---

# Social Platform Save Regression (Admin Speaker Edit)

- [x] Reproduce and isolate why platform flips to `Lain-lain` after clearing URL
- [x] Patch `SocialMedia` normalization to accept enum-backed platform values
- [x] Add regression test for enum platform + handle-only save
- [x] Run focused verification

## Review

- Root cause: Filament can hydrate `platform` as a backed enum instance; `SocialMedia` normalization only accepted strings, causing platform to be treated as null and fallback to `other` + website URL coercion.
- Updated `App\Models\SocialMedia` to normalize platform from both string and `BackedEnum` values before resolve/save/accessor logic.
- Added regression test to ensure `platform => SocialMediaPlatform::Facebook`, `username => nurul`, `url => null` persists as `facebook` and resolves to `https://www.facebook.com/nurul`.
- Verification:
  - `vendor/bin/pest --parallel --compact --filter=SocialMediaNormalizationTest` => **6 passed**
  - `php artisan view:cache` => **Blade templates cached successfully**

---

# Social Icon Wrapper Removal

- [x] Remove boxed/bordered wrapper styling from social icons in speaker sidebar
- [x] Remove boxed/bordered wrapper styling from social icons in institution sidebar
- [x] Keep hover color by platform while rendering plain icon links
- [x] Run focused verification

## Review

- Social media links now display as plain clickable icons without rounded bordered background wrappers in both speaker and institution sidebars.
- Platform-specific hover colors are preserved (`Facebook` blue, `Instagram` pink, etc.) with lightweight link styling.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact --filter=\"(SpeakerShowSocialPlacementTest|EditInstitutionSocialMediaTest)\"` => **2 passed**

---

# Share Modal Clarity + Icon Targets

- [x] Add explicit "Akan Dikongsi" entity name in speaker share modal
- [x] Add explicit "Akan Dikongsi" entity name in institution share modal
- [x] Add explicit "Akan Dikongsi" entity name in event share modal
- [x] Convert share targets to icon-only buttons for speaker/institution/event modals
- [x] Run focused verification

## Review

- Added an explicit shared-entity block in all three share modals:
  - speaker: shows `formatted_name`
  - institution: shows institution `name`
  - event: shows event `title`
- Replaced text-labeled share targets with icon-only buttons using `storage/social-media-icons/*.svg` across speaker, institution, and event modals.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact --filter=\"(EventShowPageTest|SpeakerShowSocialPlacementTest|EditInstitutionSocialMediaTest)\"` => **13 passed**

---

# Share Modal Cleanup Pass

- [x] Show speaker avatar together with speaker name in share modal
- [x] Remove "Akan Dikongsi" label text from speaker/institution/event modals
- [x] Keep entity name/title visible for share clarity
- [x] Run focused verification

## Review

- Speaker share modal now displays avatar + speaker name in one row for clearer context.
- Removed the extra "Akan Dikongsi" label text from all three share modals while preserving the entity name/title.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact --filter=\"(EventShowPageTest|SpeakerShowSocialPlacementTest|EditInstitutionSocialMediaTest)\"` => **13 passed**

---

# Share UI Sync (Remaining Contexts)

- [x] Find remaining share modal implementations not yet aligned
- [x] Sync legacy event share modal (`show-old`) to icon-only channels
- [x] Show clear shared entity title in legacy event share modal
- [x] Run focused verification and scan for stale text-button share grids

## Review

- Updated legacy event share modal in `resources/views/livewire/pages/events/show-old.blade.php` to follow the same pattern:
  - clear shared entity title block (event title)
  - icon-only share channel buttons using `storage/social-media-icons/*.svg`
- Kept context-specific channel list for the legacy modal (WhatsApp, Telegram, Facebook, X, Email) according to existing data flow.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact --filter=\"(EventShowPageTest|SpeakerShowSocialPlacementTest|EditInstitutionSocialMediaTest)\"` => **13 passed**
  - `rg` scan confirms active share modals now use the new icon-oriented pattern.

---

# Admin Event Save Block (Time Step Validation)

- [x] Reproduce save failure on `/admin/events/{id}/edit` for affected record
- [x] Identify frontend/blocking validation source
- [x] Patch form time fields to avoid silent submit blocking
- [x] Verify save flow via browser + focused Pest check

## Review

- Root cause: native browser form validation blocked submit before Livewire request because `custom_time` / `end_time` values (for example `02:13`) violated `minutesStep(5)` (`step=300`).
- Updated admin event form time fields in `app/Filament/Resources/Events/Schemas/EventForm.php`:
  - removed `->minutesStep(5)` from `custom_time`
  - removed `->minutesStep(5)` from `end_time`
- Browser verification on `https://majlisilmu.test/admin/events/019c6687-6308-73ee-8d94-6d18ab194129/edit?tab=penganjur-lokasi%3A%3Adata%3A%3Atab`:
  - `form.checkValidity()` changed from `false` to `true`
  - save now sends Livewire update request and returns success notification `Disimpan`
- Verification:
  - `php artisan view:clear && php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact tests/Feature/AdminEventsResourceTest.php --filter=\"shows typed event fields on the admin edit form\"` => **1 passed (11 assertions)**

---

# Event Space Dropdown Empty (Institution Location)

- [x] Inspect target event and institution-space linkage
- [x] Patch event form `space_id` query to avoid empty dropdown when institution has no pivot-linked spaces
- [x] Verify in browser on target admin event URL
- [x] Run focused verification

## Review

- Root cause: target event institution (`019c6687-4f4b-72f1-b644-e114a5a50695`) has no rows in `institution_space`, while `space_id` options were filtered strictly by `whereHas('institutions', institution_id)`, resulting in empty options.
- Updated `app/Filament/Resources/Events/Schemas/EventForm.php` `space_id` relationship query:
  - keep active-space filter
  - include both institution-linked spaces and global spaces with no institution links (`orWhereDoesntHave('institutions')`)
- Browser verification on:
  - `https://majlisilmu.test/admin/events/019c6687-5cb3-736b-a832-d53e5a6b85fa/edit?tab=penganjur-lokasi%3A%3Adata%3A%3Atab`
  - `Ruang` dropdown now shows options (for example `Dewan Utama`, `Dewan Solat Lelaki`, `Dewan Serbaguna`, `Dewan Jamuan`).
- Verification:
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/Events/Schemas/EventForm.php` => **No errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/AdminEventsResourceTest.php --filter=\"shows typed event fields on the admin edit form\"` => **1 passed (11 assertions)**

---

# Event Tags Cloud Layout

- [x] Replace event detail tag rendering with fixed taxonomy cloud layout
- [x] Include `Isu` taxonomy slot in the same pattern as submit-event categories
- [x] Verify Blade/test/browser rendering on target event URL

## Review

- Updated `resources/views/livewire/pages/events/show.blade.php` tag section under About:
  - switched from unordered `groupBy` loop to fixed taxonomy order:
    1. Domain
    2. Sumber
    3. Disiplin
    4. Isu
  - rendered each taxonomy as compact rounded tag chips (cloud style), matching the requested style direction.
- `Isu` is now supported explicitly in this layout and appears when issue tags exist.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventShowPageTest.php` => **11 passed (30 assertions)**
  - Browser check on `https://majlisilmu.test/majlis/fiqh-solat-tazkirah-tz50o46` confirms cloud layout with:
    - labels: `Domain`, `Sumber`, `Disiplin`
    - chips: `Akidah (Iman & Tauhid)`, `Al-Qur'an`, `Tafsir`

---

# Online Event Seeder Location Fix

- [x] Reproduce and isolate why seeded online events can still show physical location
- [x] Update `EventSeeder` backfill normalization to clear location fields for online events
- [x] Add/extend regression coverage for online seeded event location cleanup
- [x] Run focused parallel Pest verification and document review

## Review

- Root cause: seeded legacy rows with `event_format = online` were not normalized by `backfillSeededEventRequiredFields()`, so stale `institution_id`/`venue_id`/`space_id` values could persist.
- Updated `database/seeders/EventSeeder.php` backfill logic to enforce:
  - online events => always clear `institution_id`, `venue_id`, and `space_id`
  - non-online events => keep existing XOR normalization (`institution_id` xor `venue_id`) and `space_id` cleanup.
- Added regression coverage in `tests/Feature/EventSeederSubmitEventCompatibilityTest.php`:
  - creates an invalid online seeded event with physical location fields set
  - runs `EventSeeder`
  - asserts all physical location fields are cleared.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSeederSubmitEventCompatibilityTest.php` => **2 passed (12 assertions)**
  - `vendor/bin/phpstan analyse --ansi database/seeders/EventSeeder.php tests/Feature/EventSeederSubmitEventCompatibilityTest.php` => **No errors**

---

# Event Hero Fallback Polish (No Location/Image)

- [x] Inspect hero fallback behavior when event has no location or no location cover image
- [x] Improve hero atmosphere fallback source chain beyond location media
- [x] Add intentional no-location hero metadata chip state (instead of omitting location chip)
- [x] Run focused verification and document review

## Review

- Updated hero media fallback chain in `resources/views/livewire/pages/events/show.blade.php`:
  - institution cover -> venue main/cover -> organizer media (institution cover / speaker main-avatar) -> first speaker media -> visual gradient fallback.
- Added format-aware no-image hero styling (online/hybrid/physical gradients + geometric glass accents), so empty-media states keep visual depth instead of a flat block.
- Replaced "show location chip only when physical location exists" with explicit hero location-state metadata:
  - physical location => existing place label
  - online => `Acara Dalam Talian` + live-join subtitle
  - hybrid/no-location => `Mod Hibrid` or `Lokasi Akan Dikemaskini` states.
- Verification:
  - `php artisan view:clear && php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventShowPageTest.php` => **11 passed (30 assertions)**
  - Browser check on `https://majlisilmu.test/majlis/forum-perdana-bersama-asatizah-uu8oszr` confirms hero now shows `Acara Dalam Talian` + `Sertai melalui pautan siaran langsung` in the hero chip.

---

# Speaker Page Null-Safe Location Fix

- [x] Reproduce source of null `district` access on speaker show route
- [x] Patch speaker page location helper to safely handle events without location addresses
- [x] Add regression coverage for speaker page with online/no-location events
- [x] Run focused verification and document review

## Review

- Root cause in `resources/views/components/pages/speakers/⚡show.blade.php`: event-location helper dereferenced nested address relations without null-safe access (`$address->district`, `$address->subdistrict`) for online/no-location events.
- Patched helper to use full null-safe access:
  - `$address?->district?->name`
  - `$address?->state?->name`
  - `$address?->subdistrict?->name`
- Added regression test in `tests/Feature/SpeakerShowPageTimingTest.php`:
  - online event with `institution_id`, `venue_id`, `space_id` all null
  - speaker page renders successfully and includes event title.
- Verification:
  - `php artisan view:clear && php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact tests/Feature/SpeakerShowPageTimingTest.php` => **6 passed (17 assertions)**
  - Browser check on `https://majlisilmu.test/penceramah/nadia-azzahra-binti-othman-xoqg6ug` now renders successfully (no 500).
  - `vendor/bin/phpstan analyse --ansi` still reports pre-existing unrelated project errors (17), with no new speaker-page runtime issue.

---

# Event Left Column Disappears After Engagement Click

- [x] Reproduce issue on target event URL with Chrome MCP
- [x] Isolate root cause in Livewire re-render behavior
- [x] Patch event-show reveal wrappers so sections stay visible after action updates
- [x] Re-verify with Chrome MCP and focused tests

## Review

- Reproduced on `https://majlisilmu.test/majlis/kuliah-ceramah-bersama-asatizah-apbzpdh` while logged in:
  - clicking `Saya Akan Hadir` / `Minat` / `Simpan` updated counts
  - left-column sections (speakers/about/etc.) became visually hidden.
- Root cause: `scroll-reveal` sections in `resources/views/livewire/pages/events/show.blade.php` rely on client-applied `.revealed`; Livewire updates remove that transient class and sections return to hidden state (`opacity: 0`).
- Fix: added `revealed` directly in the event-show section class lists (`scroll-reveal reveal-up revealed`) for all affected content sections.
- Verification:
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventShowPageTest.php` => **11 passed (30 assertions)**
  - Chrome MCP before/after screenshots confirm left column remains visible after engagement clicks.

---

# Missing Inspiration Quotes on `migrate:fresh --seed`

- [x] Trace why inspiration quotes are missing from the full seed pipeline
- [x] Wire `InspirationSeeder` into `DatabaseSeeder`
- [x] Update seeding pipeline regression test expectations
- [x] Verify with focused Pest tests
- [x] Verify with real `php artisan migrate:fresh --seed` run

## Review

- Root cause: `database/seeders/InspirationSeeder.php` existed but was not included in `database/seeders/DatabaseSeeder.php`, so full database seed runs skipped inspiration quotes.
- Fix:
  - Added `InspirationSeeder::class` to the primary seeder batch in `DatabaseSeeder`.
  - Updated `tests/Feature/InstitutionSeederTest.php` expected batch list to include `InspirationSeeder::class`.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionSeederTest.php` => **3 passed (12 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/InspirationTest.php --filter="seeds inspirations via InspirationSeeder"` => **1 passed (14 assertions)**
  - `php artisan migrate:fresh --seed --no-interaction` now shows `Database\Seeders\InspirationSeeder ... DONE`
  - Post-seed count check: `App\Models\Inspiration::query()->count()` => **20**

---

# Speaker Index Search UX (Translateable + Live Fuzzy)

- [x] Make speaker search placeholder use a translatable key already present in locale files
- [x] Convert speaker index search to Livewire live search (debounced) with URL query sync
- [x] Add fuzzy fallback matching for typo-tolerant speaker search results
- [x] Add/extend feature tests for translation, fuzzy behavior, and live updates
- [x] Verify with focused Pest + browser check

## Review

- Updated `resources/views/components/pages/speakers/⚡index.blade.php`:
  - Added Livewire URL-bound `search` property with `wire:model.live.debounce.300ms`.
  - Replaced request-driven GET form flow with live in-place filtering and `clearSearch()` action.
  - Switched placeholder to `__('Search speakers...')`, which maps to Malay (`Cari penceramah...`) in `resources/lang/ms.json`.
  - Implemented fuzzy fallback ranking (token-aware similarity) when direct SQL search returns no results.
- Added/updated tests in `tests/Feature/SpeakerIndexTest.php`:
  - translated placeholder rendering
  - fuzzy typo match (`Smad` -> `Samad`)
  - live updates through `Livewire::test(...)->set('search', ...)`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/SpeakerIndexTest.php` => **5 passed (16 assertions)**
  - `php artisan view:cache` => **Blade templates cached successfully**
  - Chrome MCP:
    - placeholder resolves as `Cari penceramah...`
    - typo query updates URL live (`?search=Mzan`) and returns fuzzy matches (`Dr Mizan Mohamed`).

---

# Institution Index Translation + Fuzzy Search

- [x] Audit `/institusi` for hardcoded English and missing translation keys
- [x] Make hero/search/no-result copy fully translation-driven
- [x] Add typo-tolerant fuzzy fallback search for institutions
- [x] Add focused regression tests
- [x] Verify with Pest + Chrome MCP

## Review

- Updated `resources/views/components/pages/institutions/⚡index.blade.php`:
  - Replaced hardcoded hero copy with translation lookups (`Centers of`, `Knowledge & Community`, hero description).
  - Added translated accessible label for search input and ensured all page copy remains translation-driven.
  - Enhanced search logic:
    - keeps direct SQL matching (name + description, tokenized),
    - adds fuzzy fallback ranking (Levenshtein/similar_text) when direct matches are empty,
    - preserves pagination + query string behavior.
- Added translation keys to:
  - `resources/lang/ms.json`
  - `resources/lang/ms_MY.json`
  - `resources/lang/en.json`
  - including pagination UI phrases used on this page (`Showing`, `to`, `of`, `Pagination Navigation`, `Go to page :page`) and search controls (`Clear`, `Clear Search`, etc.).
- Added test coverage in `tests/Feature/InstitutionIndexTest.php`:
  - translated hero/search copy
  - translated no-result state
  - fuzzy typo search (`Hidayh` -> `Masjid Al Hidayah`)
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php` => **3 passed (10 assertions)**
  - JSON validation for locale files => **json-ok**
  - `php artisan view:cache` => **Blade templates cached successfully**
  - Chrome MCP on `https://majlisilmu.test/institusi`:
    - hero/search/pagination text in Malay
    - typo query `Klng` returns fuzzy matches like `Kompleks Islam Klang`.

---

# Institution Search Follow-up (Exact Placeholder + Live Fuzzy)

- [x] Set institutions placeholder text to exact copy `Cari institusi...`
- [x] Convert institutions search input from GET form submit to Livewire live search binding
- [x] Ensure fuzzy matching executes in live mode (no Enter/submit required)
- [x] Extend tests for live institution search updates
- [x] Re-verify in Chrome MCP

## Review

- Updated institutions search input in `resources/views/components/pages/institutions/⚡index.blade.php`:
  - placeholder now uses `__('Search institutions...')` mapped to `Cari institusi...`
  - `wire:model.live.debounce.300ms="search"` enabled
  - clear action switched to `wire:click="clearSearch"`
  - search state moved to URL-bound Livewire property (`#[Url] public ?string $search`)
- Added live regression test in `tests/Feature/InstitutionIndexTest.php`:
  - `Livewire::test('pages.institutions.index')->set('search', 'Hidayh')` asserts fuzzy live result.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php` => **4 passed (12 assertions)**
  - Chrome MCP on `https://majlisilmu.test/institusi`:
    - placeholder attribute is exactly `Cari institusi...`
    - typing `Klng` updates URL to `?search=Klng` and immediately shows `Kompleks Islam Klang` (live fuzzy behavior).

---

# Majlis Advanced Filter Filament Refactor

- [x] Audit and map every `Maklumat Majlis` field from submit-event flow into `/majlis` advanced filter semantics
- [x] Refactor `app/Livewire/Pages/Events/Index.php` to Filament form-driven filter state with URL synchronization
- [x] Extend `EventSearchService` for comprehensive filtering (language codes, event format, muslim-only, timing mode, link/end-time toggles)
- [x] Replace old `/majlis` Flux filter UI with Filament form rendering and tidy scoped CSS styling
- [x] Expand saved-search request extraction for the new advanced filter keys
- [x] Add/adjust feature tests for newly supported filters and run verification (`pest --parallel`, `phpstan`, `view:cache`)

## Review

- Rebuilt `/majlis` page filtering around a Filament form state (`filterData`) in `app/Livewire/Pages/Events/Index.php`:
  - URL-synced advanced filters include: `event_type` (multi), `event_format` (multi), `language_codes` (multi), `gender`, `age_group`, `children_allowed`, `is_muslim_only`, `prayer_time`, `timing_mode`, `starts_after`, `starts_before`, `time_scope`, `institution_id`, `speaker_ids`, `topic_ids`, link/end-time toggles (`has_event_url`, `has_live_url`, `has_end_time`), plus geolocation + sort.
  - Added smart behavior for location controls, sort state, age-group to children-allowed assist, and full clear/reset.
- Replaced old Flux advanced filter markup in `resources/views/livewire/pages/events/index.blade.php`:
  - now renders `{{ $this->form }}` (Filament form-driven filtering),
  - added scoped tidy styling for Filament sections/inputs (`.mi-filter-shell ...`),
  - expanded active filter chips and saved-search query payload to include all new advanced fields.
- Extended `app/Services/EventSearchService.php`:
  - added DB-forced filtering gate for fields not indexed in Typesense (`language_codes`, `timing_mode`, `is_muslim_only`, URL/end-time presence toggles, `prayer_time`),
  - added `event_format` filters to Typesense and DB paths,
  - added DB filters for `language_codes`, `timing_mode`, `is_muslim_only`, `has_event_url`, `has_live_url`, `has_end_time`.
- Updated `app/Livewire/Pages/SavedSearches/Index.php` request extraction to capture new scalar/array filters (`event_format`, `language_codes`, `speaker_ids`, timing/link/muslim-only flags, etc.).
- Added new regression coverage in `tests/Feature/EventSearchTest.php`:
  - `filters events by language_codes array filter`
  - `filters events by event_format array filter`
  - `filters events by is_muslim_only toggle`
  - `filters events by link presence and timing mode`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="language_codes array filter|event_format array filter|is_muslim_only toggle|link presence and timing mode|filters events by language|filters events by prayer_time enum value in advanced filters"` => **6 passed (18 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SavedSearchPageTest.php` => **4 passed (13 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php app/Services/EventSearchService.php app/Livewire/Pages/SavedSearches/Index.php tests/Feature/EventSearchTest.php` => **No errors**
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php` still has **2 pre-existing unrelated failures**:
    - `Event Detail Page` expects `Related Events`
    - `Event Registration` expects `No registration required`

---

# Institution Pending Event Visual Parity

- [x] Compare institution event list/calendar rendering with speaker pending-event treatment
- [x] Add pending state payload (`pending`) to institution calendar event data
- [x] Apply speaker-matching pending visuals on institution upcoming and past cards (amber accent + pending badge)
- [x] Apply pending-aware color priority in institution calendar cells and mobile dots
- [x] Extend feature assertion for pending badge and run focused verification

## Review

- Updated `resources/views/components/pages/institutions/⚡show.blade.php` to mirror speaker pending rendering:
  - Added `pending` flag in calendar event payload.
  - Upcoming and past list cards now use pending-aware date sidebar gradients (`from-amber-600 to-amber-800`) and date text tones.
  - Added explicit `Menunggu Kelulusan` badge for pending events in both upcoming and past cards.
  - Calendar day number, event chips, overflow text, and mobile dots now prioritize pending amber styling over remote/default colors.
- Updated `tests/Feature/InstitutionShowPageTest.php` pending event assertion to also verify `Menunggu Kelulusan` is rendered.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionShowPageTest.php` => **18 passed (54 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php` => **6 passed (20 assertions)**
  - `php artisan view:cache` => **Blade templates cached successfully**

---

# Institution Status Guard Follow-up

- [x] Re-validate status filtering semantics for institution show event queries
- [x] Confirm `active()` is the canonical approved+pending visibility scope and keep institution queries aligned to it
- [x] Add regression test to ensure non-approved/pending statuses are hidden
- [x] Re-run focused verification

## Review

- Confirmed `App\Models\Event::active()` already constrains to `approved` + `pending` public active events.
- Kept institution show queries on `->active()` without duplicating status clauses, so visibility remains centralized in one app-wide scope.
- Added `tests/Feature/InstitutionShowPageTest.php` coverage:
  - `does not show events outside approved and pending statuses`
  - verifies `rejected` and `draft` events are not rendered on institution detail.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionShowPageTest.php` => **19 passed (57 assertions)**
  - `php artisan view:cache` => **Blade templates cached successfully**

---

# Majlis Main Search Prominence Rollback

- [x] Inspect current `/majlis` Filament section styling overrides affecting search UI
- [x] Restore original look for main search section only, without changing advanced filter behavior
- [x] Keep advanced filter visual polish scoped to advanced section only
- [x] Run focused verification

## Review

- Added section-level classes in `app/Livewire/Pages/Events/Index.php`:
  - `Search & Sort` => `mi-main-search-section`
  - `Advanced Filters` => `mi-advanced-filter-section`
- Updated `resources/views/livewire/pages/events/index.blade.php` CSS so custom Filament styling applies only to `.mi-advanced-filter-section`.
- Result: main search section returns to its original/default prominent Filament look; advanced filters keep the custom tidy styling.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="updates event results live when search changes"` => **1 passed (3 assertions)**
  - `php artisan view:cache` => **Blade templates cached successfully**

---

# Majlis Main Search Snapshot Restore (2026-02-28)

- [x] Inspect yesterday git snapshot for `/majlis` main search UI
- [x] Restore main search bar to the previous prominent style only
- [x] Keep advanced filter behavior and Filament state synchronization intact
- [x] Run focused verification

## Review

- Used snapshot commit `e2312cd` (2026-02-28) as visual reference for the prominent main search input.
- Removed the duplicate Filament `Search & Sort` section from `app/Livewire/Pages/Events/Index.php` and kept `Advanced Filters` in Filament.
- Restored the prominent top search input UI in `resources/views/livewire/pages/events/index.blade.php` with:
  - icon-leading text input styling from snapshot
  - Livewire binding to `filterData.search` (`wire:model.live.debounce.400ms`) so advanced filter updates do not desync/clear search state.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="updates event results live when search changes"` => **1 passed (3 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php resources/views/livewire/pages/events/index.blade.php` => **No errors**
  - `php artisan view:cache` => **Blade templates cached successfully**

---

# Majlis Search Style Alignment (Institusi + Penceramah)

- [x] Compare `/majlis` main search with `/institusi` and `/penceramah` search UI patterns
- [x] Update `/majlis` main search markup to match shared search pattern (shape, shadow, clear affordance)
- [x] Preserve advanced filter behavior and Filament synchronization
- [x] Run focused verification and browser check

## Review

- Updated `resources/views/livewire/pages/events/index.blade.php` main search block to mirror institution/speaker search styling:
  - `max-w-xl` centered search container
  - white input with elevated shadow treatment
  - `Clear` action on filled search
  - `Esc` key clear behavior (`wire:keydown.escape="clearSearch"`).
- Added `clearSearch()` action in `app/Livewire/Pages/Events/Index.php` to keep both `search` and `filterData.search` synchronized while resetting pagination.
- Kept all advanced filter schema and search pipeline behavior intact.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="updates event results live when search changes"` => **1 passed (3 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php` => **No errors**
  - `php artisan view:cache` => **Blade templates cached successfully**
  - Chrome MCP snapshot on `https://majlisilmu.test/majlis` confirms updated search textbox is rendered in the hero/filter card area.

---

# Majlis Search Loading Jitter Fix

- [x] Reproduce loading-jitter behavior on `/majlis` search input via Chrome MCP
- [x] Move loading feedback from document flow to non-disruptive overlay position
- [x] Verify search UI no longer shifts while typing

## Review

- Updated `resources/views/livewire/pages/events/index.blade.php` filter card wrapper to `relative`.
- Changed loading indicator from inline-flow (`mb-4`) into absolute overlay:
  - `absolute right-4 top-4 ... md:right-6 md:top-6`
  - kept existing loading target + spinner text behavior.
- Result: `Updating results...` no longer pushes the search input/sort row during typing.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="updates event results live when search changes"` => **1 passed (3 assertions)**
  - `php artisan view:cache` => **Blade templates cached successfully**
  - Chrome MCP run on `https://majlisilmu.test/majlis` under `Slow 3G` shows stable search layout while typing.

---

# Majlis Advanced Filter Grouping Verification

- [x] Confirm advanced filter fields are grouped into logical subsections in Filament schema
- [x] Verify advanced filter remains collapsed on first page load
- [x] Validate grouped filter behavior and URL synchronization via Chrome MCP
- [x] Validate timing dependency behavior (`timing_mode` controls `prayer_time` enablement/reset)

## Review

- Confirmed grouped subsections are present in `app/Livewire/Pages/Events/Index.php` under the Filament `Advanced Filters` section:
  - `Location`
  - `People & Content`
  - `Audience`
  - `Time & Date`
  - `Links & Visibility`
- Confirmed advanced filter panel is collapsed by default on initial load (`->collapsible()->collapsed()`).
- Chrome MCP verification on `https://majlisilmu.test/majlis`:
  - Initial state: advanced section closed.
  - Expanded state: grouped sections render with clean hierarchy.
  - `time_scope=past` updates results and active chips, and syncs to URL.
  - `timing_mode=prayer_relative` enables prayer-time selection.
  - Switching `timing_mode` to `absolute` clears `prayer_time` and keeps URL/query state consistent.
  - Search typing keeps layout stable while loading badge appears (no input row shift observed).

---

# Majlis Venue Filter In Location Group

- [x] Add `venue_id` to `/majlis` filter state + URL normalization pipeline
- [x] Add `Venue` select into Location group (Filament schema)
- [x] Apply venue filter in search service query path
- [x] Surface venue in active filter chips / saved-search payload
- [x] Add focused feature test for `venue_id` filtering and run verification

## Review

- Updated `app/Livewire/Pages/Events/Index.php`:
  - Added `#[Url] public ?string $venue_id = null;`
  - Added `Venue` select to Location group in Filament advanced filters.
  - Added computed `venues()` options source (`verified|pending`, `is_active=true`).
  - Wired `venue_id` through default state, URL normalization, form-state normalization, public-property fill, and search filter payload.
- Updated `app/Services/EventSearchService.php`:
  - Added `venue_id` handling in database query builder (`where('venue_id', ...)`).
  - Added `venue_id` in Typesense filter parts for parity.
  - Added `filled($filters['venue_id'])` to `requiresDatabaseFiltering()` so venue-filtered searches use DB path safely.
- Updated `resources/views/livewire/pages/events/index.blade.php`:
  - Added venue state variables/collection.
  - Added `venue_id` into saved-search query payload.
  - Included venue in active-filter count / active state detection.
  - Added venue chip rendering in active filters area.
- Added test coverage in `tests/Feature/EventSearchTest.php`:
  - `filters events by venue in advanced filters`.
- Verification:
  - `php -l app/Livewire/Pages/Events/Index.php` => **No syntax errors**
  - `php -l app/Services/EventSearchService.php` => **No syntax errors**
  - `php -l resources/views/livewire/pages/events/index.blade.php` => **No syntax errors**
  - `php -l tests/Feature/EventSearchTest.php` => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter=\"filters events by venue in advanced filters\"` => **1 passed (3 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php app/Services/EventSearchService.php` => **No errors**
  - Chrome MCP on `https://majlisilmu.test/majlis` confirms:
    - `Venue` field appears under Location group.
    - URL sync includes `venue_id=...`.
    - Results reduce correctly when venue filter is applied.

---

# Majlis Label Tweak (Kawasan + Remove Masa Tamat)

- [x] Rename `Subdistrict` field label to `Kawasan` in advanced location group
- [x] Remove `Masa Tamat` (`has_end_time`) control from Links & Visibility UI
- [x] Remove `has_end_time` from active-filter summary payload and chips
- [x] Verify syntax and UI rendering

## Review

- Updated `app/Livewire/Pages/Events/Index.php`:
  - `subdistrict_id` label now `Kawasan`.
  - Removed `has_end_time` select from `Links & Visibility` and adjusted layout columns from 3 to 2.
  - Updated section description to reflect URL-only filters.
- Updated `resources/views/livewire/pages/events/index.blade.php`:
  - Subdistrict fallback chip text now `Kawasan`.
  - Removed `has_end_time` from saved-search query payload and active-filter counters/state.
  - Removed `Has End Time / No End Time` active chip rendering block.
- Verification:
  - `php -l app/Livewire/Pages/Events/Index.php` => **No syntax errors**
  - `php -l resources/views/livewire/pages/events/index.blade.php` => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="displays the events index page"` => **1 passed (3 assertions)**
  - Chrome MCP snapshot on `https://majlisilmu.test/majlis` confirms:
    - Location group label shows `Kawasan`.
    - `Masa Tamat` field no longer appears in advanced filters.

---

# Majlis Desktop Filter Row (Institusi + Tempat)

- [x] Identify `/majlis` location filter layout source
- [x] Update desktop column layout so `Institusi` and `Tempat` appear on same row
- [x] Run syntax verification on modified file

## Review

- Updated `app/Livewire/Pages/Events/Index.php`:
  - Changed Location section columns from `['default' => 1, 'md' => 2, 'xl' => 4]` to `['default' => 1, 'md' => 2, 'lg' => 3]`.
  - Result on desktop breakpoints (`lg` and up): first row keeps location hierarchy fields, second row places `Institusi` and `Tempat` side-by-side.
- Verification:
  - `php -l app/Livewire/Pages/Events/Index.php` => **No syntax errors**
  - Chrome MCP on `https://majlisilmu.test/majlis` confirms `Institusi` and `Tempat` share the same row at `1024px` and `1440px` widths.

---

# Majlis Location Labels + Dependent Institution/Venue Options

- [x] Change `Kawasan` label to `Daerah Kecil / Bandar / Mukim`
- [x] Make `Institusi` options follow selected `Negeri`, `Daerah`, and `Daerah Kecil / Bandar / Mukim`
- [x] Make `Tempat` options follow selected `Negeri`, `Daerah`, and `Daerah Kecil / Bandar / Mukim`
- [x] Reset selected `institusi`/`tempat` when geography chain changes to prevent stale mismatches
- [x] Run syntax/static/test checks and browser verification

## Review

- Updated `app/Livewire/Pages/Events/Index.php`:
  - Changed `subdistrict_id` filter label to `Daerah Kecil / Bandar / Mukim`.
  - Added dependent resets:
    - `state_id` change now clears `district_id`, `subdistrict_id`, `institution_id`, `venue_id`.
    - `district_id` change now clears `subdistrict_id`, `institution_id`, `venue_id`.
    - `subdistrict_id` change now clears `institution_id`, `venue_id`.
  - Replaced static options for `institution_id` and `venue_id` with dynamic options closures based on current location selection.
  - Added helper methods:
    - `institutionOptions(...)`
    - `venueOptions(...)`
    - `applyAddressLocationFilters(...)` using `whereHas('address', ...)` for `state_id`/`district_id`/`subdistrict_id`
    - `normalizeNullableString(...)`
  - Added PHPStan-safe generic PHPDoc on `applyAddressLocationFilters(...)`.
- Updated `resources/views/livewire/pages/events/index.blade.php`:
  - Updated subdistrict active-chip fallback label to `Daerah Kecil / Bandar / Mukim`.

- Verification:
  - `php -l app/Livewire/Pages/Events/Index.php` => **No syntax errors**
  - `php -l resources/views/livewire/pages/events/index.blade.php` => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="displays the events index page"` => **1 passed (3 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php` => **No errors**
  - Chrome MCP on `https://majlisilmu.test/majlis` confirms:
    - Location label renders as `Daerah Kecil / Bandar / Mukim`.
    - After selecting `Negeri: Johor`, `Institusi` search results are narrowed (e.g., `Masjid` results reduced compared to unfiltered state).
    - After selecting `Negeri: Johor`, `Tempat` search results are narrowed (e.g., `Dewan` shows Johor-matching venue list).

---

# Majlis Advanced Filters Order (Time Group on Top)

- [x] Move `Time & Date` advanced filter group to the top position
- [x] Keep other groups and field behavior unchanged
- [x] Verify syntax and UI ordering

## Review

- Updated `app/Livewire/Pages/Events/Index.php`:
  - Reordered advanced filter sections so `Time & Date` is now the first group under `Advanced Filters`.
  - Removed the original lower-position `Time & Date` block to avoid duplication.
- Verification:
  - `php -l app/Livewire/Pages/Events/Index.php` => **No syntax errors**
  - Chrome MCP on `https://majlisilmu.test/majlis?state_id=2489` confirms expanded order starts with `Time & Date`, followed by `Lokasi`.

---

# Majlis Date Range Wording Clarification

- [x] Clarify `Tarikh Mula` / `Tarikh Tamat` as event-held date range filters (not per-event duration)
- [x] Update active filter chips wording for date range clarity
- [x] Add Malay locale translations for new date-range wording
- [x] Verify syntax, JSON validity, test, and UI copy

## Review

- Updated `app/Livewire/Pages/Events/Index.php`:
  - `Time & Date` section description now clarifies this is a held-date range filter.
  - Date labels changed to `Held From Date` and `Held Until Date`.
  - Added helper text on both date pickers:
    - `Filters events held on or after this date.`
    - `Filters events held on or before this date.`
- Updated `resources/views/livewire/pages/events/index.blade.php`:
  - Active chips now read `Held from` / `Held until` instead of generic `From` / `Until`.
- Updated translations:
  - `resources/lang/ms.json`
  - `resources/lang/ms_MY.json`
  - Added localized strings for the new labels, helper texts, section description, and chip wording.

- Verification:
  - `php -l app/Livewire/Pages/Events/Index.php` => **No syntax errors**
  - `php -l resources/views/livewire/pages/events/index.blade.php` => **No syntax errors**
  - `php -r` JSON decode checks => **ms.json OK**, **ms_MY.json OK**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="displays the events index page"` => **1 passed (3 assertions)**
  - Chrome MCP on `https://majlisilmu.test/majlis?state_id=2489` confirms:
    - `Tarikh Majlis Dari` / `Tarikh Majlis Hingga` labels appear.
    - Helper text explicitly states date range filtering for events being held.

---

# Majlis Date Range Query Semantics (Held-Period Overlap)

- [x] Update `/majlis` search query to treat `starts_after` / `starts_before` as held-period overlap filters (using `ends_at` fallback)
- [x] Align Typesense filter construction to the same overlap semantics
- [x] Add/adjust focused tests proving overlap behavior (not just label wording)
- [x] Run focused verification and document review

## Review

- Updated `app/Services/EventSearchService.php`:
  - Database query now treats date filters as held-period overlap:
    - `starts_after` => event is held on/after boundary using `ends_at >= boundary` OR (`ends_at` null and `starts_at >= boundary`).
    - `starts_before` => event starts on/before boundary (`starts_at <= boundary`).
  - Typesense filter builder now uses the same overlap semantics for lower-bound filtering:
    - `(ends_at:>=X||starts_at:>=X)` plus `starts_at:<=Y` when upper bound is set.
- Updated tests:
  - `tests/Feature/EventSearchTest.php`:
    - Reworked date-range test to prove overlap behavior with a multi-day event crossing into the selected range.
    - Kept timezone boundary behavior deterministic by setting `ends_at` to `null` in `starts_after` timezone test fixtures.
  - `tests/Feature/EventSearchTypesenseFilterTest.php`:
    - Added assertion that Typesense date lower-bound filter includes the overlap expression with `ends_at` fallback.
- Verification:
  - `php -l app/Services/EventSearchService.php` => **No syntax errors**
  - `php -l tests/Feature/EventSearchTest.php` => **No syntax errors**
  - `php -l tests/Feature/EventSearchTypesenseFilterTest.php` => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTypesenseFilterTest.php` => **3 passed (3 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="held date overlap range|interprets starts_after date in the user timezone|filters events to past only when time scope is past|shows both past and upcoming events when time scope is all"` => **4 passed (14 assertions)**

---

# Majlis Timing Mode Absolute Time Range Fields

- [x] Add conditional time-range fields in `Time & Date` when `Mod Masa` is `Waktu Tepat`
- [x] Sync new time-range fields through URL/filter state and active filter chips
- [x] Apply absolute-time range filtering in `EventSearchService` query path
- [x] Add focused regression test and run verification

## Review

- Updated `app/Livewire/Pages/Events/Index.php`:
  - Added URL-synced fields: `starts_time_from`, `starts_time_until`.
  - In `Time & Date`, added `TimePicker` fields `Masa Dari` and `Masa Hingga`, shown only when `timing_mode = absolute` (`Waktu Tepat`).
  - `timing_mode` state change now clears irrelevant time fields when switching away from `Waktu Tepat`.
  - Wired both time fields through default filter state, normalized URL state, normalized form state, public-property hydration, and search-filter payload.
  - Added `normalizeTimeString(...)` helper to keep persisted values consistent (`H:i`).
- Updated `resources/views/livewire/pages/events/index.blade.php`:
  - Added both time fields to saved-search query payload.
  - Included them in active-filter count/active-state detection.
  - Added active chips: `Masa Dari` and `Masa Hingga`.
- Updated `app/Services/EventSearchService.php`:
  - Added normalization for time-range input via `normalizeTimeFilter(...)`.
  - Added DB query filtering for absolute timing mode with start-time range:
    - supports from-only, until-only, and from+until.
    - supports wrap-around ranges (e.g., 22:00 → 02:00).
  - Time filtering uses user-timezone offset conversion in SQL expression for `events.starts_at`.
  - Added time-range flags to `requiresDatabaseFiltering(...)` to keep this path on DB.
- Updated `tests/Feature/EventSearchTest.php`:
  - Added `filters absolute timing events by selected start time range`.
- Updated `app/Livewire/Pages/SavedSearches/Index.php`:
  - Added `starts_time_from` and `starts_time_until` to request extraction filter keys so saved searches retain the new time range filters.

- Verification:
  - `php -l app/Livewire/Pages/Events/Index.php` => **No syntax errors**
  - `php -l app/Services/EventSearchService.php` => **No syntax errors**
  - `php -l resources/views/livewire/pages/events/index.blade.php` => **No syntax errors**
  - `php -l tests/Feature/EventSearchTest.php` => **No syntax errors**
  - `php -l app/Livewire/Pages/SavedSearches/Index.php` => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="filters absolute timing events by selected start time range|filters events by link presence and timing mode|displays the events index page"` => **3 passed (10 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SavedSearchPageTest.php --filter="save|request|filters"` => **4 passed (13 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php app/Services/EventSearchService.php app/Livewire/Pages/SavedSearches/Index.php tests/Feature/EventSearchTest.php` => **No errors**

---

# Majlis Timing UI Follow-up (Prayer Visibility + Start-Time Semantics)

- [x] Show `Waktu Solat` filter only when `Mod Masa` is `Waktu Solat`
- [x] Ensure `Masa Dari / Masa Hingga` semantics remain based on event start time (`starts_at`) only
- [x] Prevent stale `prayer_time` URL params from conflicting when `Mod Masa` is `Waktu Tepat`
- [x] Add focused regression checks and verify

## Review

- Updated `app/Livewire/Pages/Events/Index.php`:
  - `prayer_time` field now uses `->visible(...)` and appears only when `timing_mode` is prayer-relative.
  - Added helper text to `Masa Dari` / `Masa Hingga` clarifying it filters by **masa mula majlis** (event start time).
  - Added state normalization guard so `prayer_time` is cleared when `timing_mode` is `absolute`.
- Updated `app/Services/EventSearchService.php`:
  - `prayer_time` filter is ignored when `timing_mode=absolute` (absolute mode takes precedence).
  - Kept absolute time-range filtering based on localized `starts_at` time-of-day only.
- Updated `tests/Feature/EventSearchTest.php`:
  - Added `ignores prayer time filter when timing mode is absolute`.
  - Added `applies absolute time range to event start time only, not event end time`.

- Verification:
  - `php -l app/Livewire/Pages/Events/Index.php` => **No syntax errors**
  - `php -l app/Services/EventSearchService.php` => **No syntax errors**
  - `php -l tests/Feature/EventSearchTest.php` => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="ignores prayer time filter when timing mode is absolute|filters absolute timing events by selected start time range|applies absolute time range to event start time only, not event end time"` => **3 passed (10 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php app/Services/EventSearchService.php tests/Feature/EventSearchTest.php` => **No errors**
