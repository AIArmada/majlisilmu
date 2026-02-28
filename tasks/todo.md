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
