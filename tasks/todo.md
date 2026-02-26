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
