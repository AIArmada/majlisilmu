# Event Settings Default Audit Follow-Up

- [x] Audit commit `d6f7b95eaa6d30de99b63511d448267f471de9ac` for root-cause gaps after the registration-default fix
- [x] Fix the remaining unsafe `event_settings.registration_required` schema default at the database level
- [x] Add focused regression coverage and rerun the relevant event-creation verification suite

## Review
- Audit found one remaining root-cause gap after the commit-level PHP fixes: the database schema in [2026_01_30_194223_create_event_settings_table.php](/Users/Saiffil/Herd/majlisilmu/database/migrations/2026_01_30_194223_create_event_settings_table.php) still defaults `event_settings.registration_required` to `true`. The touched app paths now explicitly persist `false`, but any uncaptured or future `EventSettings` creation that omits the flag could still silently recreate the original bug.
- Added [2026_04_01_062532_set_event_settings_registration_required_default_false.php](/Users/Saiffil/Herd/majlisilmu/database/migrations/2026_04_01_062532_set_event_settings_registration_required_default_false.php) to change the column default to `false` for existing installs while keeping rollback symmetry.
- Extended [EventActionsTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/EventActionsTest.php) with a direct `EventSettings::create(...)` regression that proves a bare settings insert now hydrates `registration_required = false` instead of inheriting an unsafe truthy default.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/EventActionsTest.php` => **6 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/AdvancedEventCreationTest.php` => **5 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/SubmitEventEntityAccessTest.php` => **5 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/SubmitEventParentProgramTest.php` => **2 passed**
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi database/migrations/2026_04_01_062532_set_event_settings_registration_required_default_false.php app/Actions/Events/ResolveAdvancedBuilderContextAction.php app/Actions/Events/SyncEventResourceRelationsAction.php app/Actions/Events/CreateAdvancedParentProgramAction.php tests/Feature/EventActionsTest.php` => **No errors**
  - `XDEBUG_MODE=off vendor/bin/pint --dirty --format agent` => **pass**

# Institution Event Card Speaker Context

- [x] Trace institution show-page event card rendering and its loaded event relations
- [x] Update the institution event card to show speaker and role names while omitting redundant institution location text
- [x] Add focused regression coverage and run targeted verification

## Review
- Updated [⚡show.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/institutions/⚡show.blade.php) so institution event cards now eager-load `keyPeople.speaker`, render a `Penceramah: ...` summary line plus a compact non-speaker role summary like `Moderator: ...`, and stop repeating the current institution name in the location line when the event is hosted at that same institution.
- Tightened the institution-page location helper so cards only show the venue name when a separate venue exists; otherwise they fall back to the address hierarchy only. This removes redundant output like `Masjid ... • Shah Alam, Petaling, Selangor` inside `/institusi/*` while preserving venue-specific cards.
- Added focused regression coverage in [InstitutionShowPageTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/InstitutionShowPageTest.php) to prove institution event cards show speaker and moderator names from `event_key_people` and no longer emit the self-referential institution-name location string.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/InstitutionShowPageTest.php` => **27 passed**
  - `XDEBUG_MODE=off vendor/bin/pint --test resources/views/components/pages/institutions/⚡show.blade.php tests/Feature/InstitutionShowPageTest.php tasks/todo.md` => **pass**
  - `git diff --check -- resources/views/components/pages/institutions/⚡show.blade.php tests/Feature/InstitutionShowPageTest.php tasks/todo.md` => **clean**

# Event Map Display Without Google API

- [x] Trace the current `/majlis/*` map rendering path and compare it with `/institusi/*`
- [x] Replace the event-page API-backed map display with the same non-API rendering approach used by institutions
- [x] Add focused regression coverage and run targeted verification

## Review
- Traced the difference between [show.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/livewire/pages/events/show.blade.php) and [⚡show.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/institutions/⚡show.blade.php). Institutions already render a public Google Maps iframe via `https://www.google.com/maps?q=...&output=embed`, while the event page still preferred `https://www.google.com/maps/embed/v1/place?key=...` and an API-backed static map image.
- Updated [show.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/livewire/pages/events/show.blade.php) so the `/majlis/*` sidebar map preview now uses the same public non-API embed style as institutions. The keyed Embed API branch and the keyed Static Maps fallback were both removed.
- Added focused regression coverage in [EventShowPageTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/EventShowPageTest.php) to prove event pages render the public embed URL and do not emit either `maps/embed/v1` or `maps.googleapis.com/maps/api/staticmap`, even when a Google Maps API key is configured.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/EventShowPageTest.php --filter='uses a public google maps embed on event show pages instead of platform api urls|displays waze and google maps navigation buttons when coordinates exist'` => **2 passed**
  - `XDEBUG_MODE=off vendor/bin/pint --test resources/views/livewire/pages/events/show.blade.php tests/Feature/EventShowPageTest.php tasks/todo.md` => **pass**
  - `git diff --check -- resources/views/livewire/pages/events/show.blade.php tests/Feature/EventShowPageTest.php tasks/todo.md` => **clean**


# Tag Form Enum Crash Fix

- [x] Confirm the tag form enum hydration path and the exact crash condition
- [x] Patch the tag form helper text to handle both raw strings and hydrated TagType enums
- [x] Add focused regression coverage and run targeted verification

## Review
- Confirmed the crash came from [TagForm.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Tags/Schemas/TagForm.php): the `type` select uses `->enum(TagType::class)`, so Filament can pass a hydrated `TagType` instance into the helper-text closure. The old code then called `TagType::tryFrom($state)` unconditionally, which throws when `$state` is already a `TagType`.
- Fixed the form by adding a narrow normalization path in [TagForm.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Tags/Schemas/TagForm.php) and routing the helper text through `TagForm::typeDescription(...)`, which safely accepts either a hydrated enum, a raw backing value, or empty/unknown state.
- Added focused regression coverage in [TagTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/TagTest.php) to prove the tag-form helper description resolves correctly for both hydrated enum state and raw string state without throwing.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/TagTest.php` => **9 passed**
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi app/Filament/Resources/Tags/Schemas/TagForm.php tests/Feature/TagTest.php` => **No errors**
  - `XDEBUG_MODE=off vendor/bin/pint --test app/Filament/Resources/Tags/Schemas/TagForm.php tests/Feature/TagTest.php tasks/todo.md` => **pass**
  - `git diff --check -- app/Filament/Resources/Tags/Schemas/TagForm.php tests/Feature/TagTest.php tasks/todo.md` => **clean**

# Institution QR Thumbnail Cleanup

- [x] Trace the QR thumbnail wrapper on the public institution page
- [x] Remove the rounded border shell around the QR thumbnail trigger
- [x] Run focused verification for the institution donation-channel UI

## Review
- Updated [⚡show.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/institutions/⚡show.blade.php) so the `/institusi/*` donation-channel QR trigger no longer renders the decorative rounded gold border and shadow shell around the thumbnail; the QR image and click-to-open modal behavior stay unchanged.
- Added a focused regression in [InstitutionShowPageTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/InstitutionShowPageTest.php) that renders an institution donation channel with QR media and asserts the old rounded-border class string is gone.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/InstitutionShowPageTest.php --filter='displays donation channels|renders donation qr thumbnails without the rounded border shell'` => **2 passed**
  - `XDEBUG_MODE=off vendor/bin/pint --test resources/views/components/pages/institutions/⚡show.blade.php tests/Feature/InstitutionShowPageTest.php tasks/todo.md` => **pass**
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi` => **No errors**
  - `git diff --check -- resources/views/components/pages/institutions/⚡show.blade.php tests/Feature/InstitutionShowPageTest.php tasks/todo.md` => **clean**

# Institution Update Cover Upload

- [x] Trace the `/sumbangan/institusi/*/kemas-kini` institution update flow and confirm why the cover uploader is missing
- [x] Add the missing institution cover upload for safe direct-maintainer edits on the public institution update page
- [x] Add focused regression coverage and run targeted verification for the updated institution cover workflow

## Review
- Traced the public update page to [SuggestUpdate.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/Contributions/SuggestUpdate.php) and confirmed the missing upload came from the institution branch forcing `includeMedia: false`, so the `cover` uploader never rendered on `/sumbangan/institusi/*/kemas-kini`.
- Updated [SuggestUpdate.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/Contributions/SuggestUpdate.php) to support Filament media actions/uploads, inject an institution-only `cover` uploader into the maintainer/direct-edit form, detect cover-only changes, and persist them with `saveRelationships()` so owners can update the institution cover from the public edit route.
- Kept the community suggestion path unchanged on purpose. During debugging, the shared form proved that `getState()` eagerly stores a Spatie media upload onto the bound live institution record before the contribution request is created, so exposing cover uploads to non-maintainers on this page would bypass review. The field is therefore shown only when the user already has direct edit access.
- Added focused regression coverage in [ContributionPagesTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/ContributionPagesTest.php) to prove the cover uploader is hidden for non-maintainers, visible for maintainers, that the update page exposes the required Filament action handlers, and that a maintainer can upload a cover image directly from the public update page.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/ContributionPagesTest.php` => **23 passed**
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi app/Models/ContributionRequest.php app/Actions/Contributions/ApproveContributionRequestAction.php app/Livewire/Pages/Contributions/SuggestUpdate.php tests/Feature/ContributionPagesTest.php` => **No errors**
  - `XDEBUG_MODE=off vendor/bin/pint --test app/Livewire/Pages/Contributions/SuggestUpdate.php tests/Feature/ContributionPagesTest.php app/Actions/Contributions/ApproveContributionRequestAction.php app/Models/ContributionRequest.php tasks/todo.md` => **pass**
  - `git diff --check -- app/Livewire/Pages/Contributions/SuggestUpdate.php tests/Feature/ContributionPagesTest.php app/Actions/Contributions/ApproveContributionRequestAction.php app/Models/ContributionRequest.php tasks/todo.md` => **clean**

# Uncommitted Slug Audit Follow-Up

- [x] Audit the current uncommitted speaker/country slug diff for real regressions
- [x] Fix every confirmed issue without disturbing unrelated in-progress changes
- [x] Re-run focused verification on the repaired create, approval, and quick-create flows

## Review
- Audit found one real fallback-approval bug in [ApproveContributionRequestAction.php](/Users/Saiffil/Herd/majlisilmu/app/Actions/Contributions/ApproveContributionRequestAction.php): unstaged speaker create approvals were passing the full payload into the speaker slug action, so nested `address.country_id` data was ignored and verified speakers could be created without the required `-my` style suffix.
- Audit also found one persistence mismatch across the new required-country flows: [SharedFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/SharedFormSchema.php) still refused to create an address row when `country_id` was the only location field present, which meant institution, speaker, and venue quick-create flows plus staged institution creation could generate country-based slugs now but later lose the country suffix during slug backfills because no address country had actually been stored.
- Fixed the fallback-approval path by extracting address payloads consistently before slug generation and by persisting that submitted address on fallback-created institution and speaker records.
- Fixed the country-only persistence gap by extending [SharedFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/SharedFormSchema.php) with an explicit `allowCountryOnly` path and enabling it only where the new URL contract depends on country data: [InstitutionFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/InstitutionFormSchema.php), [SpeakerFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/SpeakerFormSchema.php), [VenueFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/VenueFormSchema.php), [ContributionEntityMutationService.php](/Users/Saiffil/Herd/majlisilmu/app/Services/ContributionEntityMutationService.php), and the fallback approval path above.
- Added regression coverage in [SpeakerSlugGenerationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/SpeakerSlugGenerationTest.php), [InstitutionSlugGenerationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/InstitutionSlugGenerationTest.php), and [SharedFormSchemaTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/SharedFormSchemaTest.php) for unstaged speaker approvals, staged institution country-only addresses, and institution/venue quick-create country-only addresses.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/SpeakerSlugGenerationTest.php` => **8 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/InstitutionSlugGenerationTest.php` => **8 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php` => **26 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/SpeakerCreateOptionSchemaTest.php` => **2 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/ContributionPagesTest.php --filter='renders the dedicated institution contribution page|renders the dedicated speaker contribution page|exposes Filament action handlers required by public contribution media uploads'` => **3 passed**
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi app/Actions/Contributions/ApproveContributionRequestAction.php app/Forms/SharedFormSchema.php app/Forms/InstitutionFormSchema.php app/Forms/SpeakerFormSchema.php app/Forms/VenueFormSchema.php app/Services/ContributionEntityMutationService.php tests/Feature/SpeakerSlugGenerationTest.php tests/Feature/InstitutionSlugGenerationTest.php tests/Feature/SharedFormSchemaTest.php` => **No errors**
  - `XDEBUG_MODE=off vendor/bin/pint --test app/Actions/Contributions/ApproveContributionRequestAction.php app/Forms/SharedFormSchema.php app/Forms/InstitutionFormSchema.php app/Forms/SpeakerFormSchema.php app/Forms/VenueFormSchema.php app/Services/ContributionEntityMutationService.php tests/Feature/SpeakerSlugGenerationTest.php tests/Feature/InstitutionSlugGenerationTest.php tests/Feature/SharedFormSchemaTest.php tasks/todo.md` => **pass**
  - `git diff --check -- app/Actions/Contributions/ApproveContributionRequestAction.php app/Forms/SharedFormSchema.php app/Forms/InstitutionFormSchema.php app/Forms/SpeakerFormSchema.php app/Forms/VenueFormSchema.php app/Services/ContributionEntityMutationService.php tests/Feature/SpeakerSlugGenerationTest.php tests/Feature/InstitutionSlugGenerationTest.php tests/Feature/SharedFormSchemaTest.php tasks/todo.md` => **clean**

# Queued Slug Backfill On Current PostgreSQL

- [x] Confirm the current app connection is the live local PostgreSQL database and the queue backend is Redis
- [x] Run `institutions:queue-slug-backfill` on the current PostgreSQL database with a disposable proof institution
- [x] Prove the real Redis queue/job/worker path and clean up the proof record afterward

## Review
- Confirmed the current app is using PostgreSQL (`database.default = pgsql`, database `majlisilmu`) and Redis queues (`queue.default = redis`, queue `default`) in the live local environment.
- Verified the live Redis queue started empty and no unique backfill lock was present, then inserted a disposable proof institution directly on the current PostgreSQL database with ID `7dbde508-5a17-429b-88f5-820505d0eb69` and legacy slug `legacy-proof-slug-current-pg`.
- Ran `php artisan institutions:queue-slug-backfill` on the current app. It printed `Queued institution slug backfill job.`, the live Redis `queues:default` length moved from `0` to `1`, and the queued payload showed `displayName: App\\Jobs\\BackfillInstitutionSlugs`.
- Ran `php artisan queue:work redis --queue=default --once --tries=1` on the same live app. The worker processed `App\Jobs\BackfillInstitutionSlugs` on the real dataset and completed in `1 minit 54 saat`.
- After completion, the live Redis queue length returned to `0`, and the proof institution slug on PostgreSQL changed from `legacy-proof-slug-current-pg` to `queue-backfill-proof-current-pg-20260331`.
- Cleaned up the disposable proof address and institution row afterward so the current PostgreSQL database does not keep the audit fixture.

# Queued Slug Backfill Proof

- [x] Trace `institutions:queue-slug-backfill` and confirm it dispatches the queued backfill job
- [x] Run the command in an isolated queued environment so it does not mutate the working local dataset
- [x] Prove the job enqueued, ran, drained the queue, and rewrote a legacy institution slug

## Review
- Used an isolated SQLite database at `/tmp/majlis-slug-backfill-proof-20260331.sqlite` together with an isolated Redis prefix (`majlis-slug-backfill-proof-20260331-`) and isolated cache prefix so the proof run would not touch the working local application state.
- Migrated that disposable app state, created a fixture institution named `Queue Backfill Proof 20260331` with the legacy slug `legacy-proof-slug`, and confirmed the isolated Redis `default` queue length started at `0`.
- Ran `php artisan institutions:queue-slug-backfill` under that isolated env. It printed `Queued institution slug backfill job.`, the isolated Redis queue length moved to `1`, and the queued payload showed `displayName: App\\Jobs\\BackfillInstitutionSlugs`.
- Ran `php artisan queue:work redis --queue=default --once --tries=1` under the same isolated env. The worker processed `App\Jobs\BackfillInstitutionSlugs`, the isolated queue length returned to `0`, and the fixture institution slug changed from `legacy-proof-slug` to `queue-backfill-proof-20260331-my`.
- Cleaned up the disposable SQLite file after verification.

# Public Market Audit Follow-Up

- [x] Audit the uncommitted public-market and hidden-country diff for logic regressions beyond the initial focused implementation
- [x] Fix any issues found in the new market preference and country inference flow
- [x] Re-run targeted verification for the corrected market/resolver and public listing paths

## Review
- Audit found one real resolver-precedence bug in [PublicMarketPreference.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Location/PublicMarketPreference.php): invalid or disabled saved market values were being coerced into an explicit Malaysia selection instead of being treated as absent. That could mask the later `CF-IPCountry` step in [PreferredCountryResolver.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Location/PreferredCountryResolver.php), which breaks the intended `timezone -> selected market -> CF-IPCountry -> Malaysia` order once additional markets are enabled.
- Fixed that by making `selectedKey()` return `null` for invalid or disabled persisted values, while still letting `currentKey()` display the enabled default market in the header. I also hardened [PublicMarketRegistry.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Location/PublicMarketRegistry.php) so a disabled configured default no longer becomes the active default.
- Added direct regression coverage in [PublicMarketPreferenceTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Unit/PublicMarketPreferenceTest.php) for stale/disabled saved market values and the `CF-IPCountry` fallback path.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Unit/PublicMarketPreferenceTest.php` => **3 passed**
  - `vendor/bin/pest --parallel --compact tests/Unit/PreferredCountryResolverTest.php` => **5 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicMarketSelectorTest.php` => **2 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionContributionLocationPickerTest.php` => **6 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php` => **15 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php` => **69 passed**
  - `vendor/bin/phpstan analyse --ansi app/Support/Location/PublicMarketRegistry.php app/Support/Location/PublicMarketPreference.php app/Support/Location/PreferredCountryResolver.php app/Http/Controllers/PublicMarketController.php tests/Unit/PublicMarketPreferenceTest.php tests/Unit/PreferredCountryResolverTest.php tests/Feature/PublicMarketSelectorTest.php` => **No errors**
  - `vendor/bin/pint app/Support/Location/PublicMarketRegistry.php app/Support/Location/PublicMarketPreference.php app/Support/Location/PreferredCountryResolver.php app/Http/Controllers/PublicMarketController.php tests/Unit/PublicMarketPreferenceTest.php tests/Unit/PreferredCountryResolverTest.php tests/Feature/PublicMarketSelectorTest.php` => **pass**
  - `git diff --check` => **clean**

# Public Market Selector And Country Inference

- [x] Add a separate public market registry/preference flow and header-only selector beside the language switcher
- [x] Update preferred-country resolution to `real timezone -> selected market -> CF-IPCountry -> Malaysia` with enabled-market gating
- [x] Remove the legacy public country visibility toggle and keep public `country_id` fields hidden/internal everywhere
- [x] Rewrite focused tests for Malaysia-only fallback, header market UI, and removed toggle behavior
- [x] Run focused verification and document the result

## Review
- Added a dedicated public market layer with [public-markets.php](/Users/Saiffil/Herd/majlisilmu/config/public-markets.php), [PublicMarketRegistry.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Location/PublicMarketRegistry.php), [PublicMarketPreference.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Location/PublicMarketPreference.php), and [PublicMarketController.php](/Users/Saiffil/Herd/majlisilmu/app/Http/Controllers/PublicMarketController.php). The public header in [app.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/layouts/app.blade.php) now shows a separate market selector beside the existing language selector, with Malaysia selectable and Brunei, Singapore, and Indonesia rendered as disabled “Coming soon” placeholders.
- Reworked [PreferredCountryResolver.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Location/PreferredCountryResolver.php) so country inference now resolves in this order: real timezone, explicitly selected market, `CF-IPCountry`, then Malaysia. Every candidate is normalized through the four-country market whitelist and then filtered through the enabled-market set, so non-supported or disabled-market values currently collapse to Malaysia.
- Removed the old country-visibility toggle path by deleting [PublicCountryFilterVisibility.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Location/PublicCountryFilterVisibility.php), stripping the dashboard setting from [AccountSettings.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/Dashboard/AccountSettings.php), switching bootstrap cookie handling to the new market cookie in [app.php](/Users/Saiffil/Herd/majlisilmu/bootstrap/app.php), and forcing public country fields to stay hidden in [SharedFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/SharedFormSchema.php), [InstitutionFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/InstitutionFormSchema.php), [VenueFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/VenueFormSchema.php), [InstitutionContributionFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/InstitutionContributionFormSchema.php), [Index.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/Events/Index.php), [AdvancedFiltersPanel.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/Events/AdvancedFiltersPanel.php), and [⚡index.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/institutions/⚡index.blade.php). Explicit `country_id` deep links still work internally; the selector UI is just gone.
- Added and updated focused regression coverage in [PublicMarketSelectorTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/PublicMarketSelectorTest.php), [PreferredCountryResolverTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Unit/PreferredCountryResolverTest.php), [AccountSettingsPageTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/AccountSettingsPageTest.php), [SharedFormSchemaTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/SharedFormSchemaTest.php), [InstitutionContributionLocationPickerTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/InstitutionContributionLocationPickerTest.php), [EventSearchTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/EventSearchTest.php), [InstitutionIndexTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/InstitutionIndexTest.php), and [Laravel13CacheSerializationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/Laravel13CacheSerializationTest.php), plus locale copy updates in [en.json](/Users/Saiffil/Herd/majlisilmu/resources/lang/en.json), [ms.json](/Users/Saiffil/Herd/majlisilmu/resources/lang/ms.json), and [ms_MY.json](/Users/Saiffil/Herd/majlisilmu/resources/lang/ms_MY.json).
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Unit/PreferredCountryResolverTest.php` => **5 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicMarketSelectorTest.php` => **2 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/AccountSettingsPageTest.php` => **12 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php` => **22 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionContributionLocationPickerTest.php` => **6 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php` => **69 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php` => **15 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/Laravel13CacheSerializationTest.php` => **4 passed**
  - `vendor/bin/phpstan analyse --ansi app/Http/Controllers/PublicMarketController.php app/Support/Location/PublicMarketPreference.php app/Support/Location/PublicMarketRegistry.php app/Support/Location/PreferredCountryResolver.php app/Livewire/Pages/Dashboard/AccountSettings.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php app/Forms/SharedFormSchema.php app/Forms/InstitutionFormSchema.php app/Forms/VenueFormSchema.php app/Forms/InstitutionContributionFormSchema.php bootstrap/app.php routes/web.php tests/Unit/PreferredCountryResolverTest.php tests/Feature/PublicMarketSelectorTest.php tests/Feature/AccountSettingsPageTest.php tests/Feature/SharedFormSchemaTest.php tests/Feature/InstitutionContributionLocationPickerTest.php tests/Feature/EventSearchTest.php tests/Feature/InstitutionIndexTest.php tests/Feature/Laravel13CacheSerializationTest.php` => **No errors**
  - `vendor/bin/pint app/Http/Controllers/PublicMarketController.php app/Support/Location/PublicMarketPreference.php app/Support/Location/PublicMarketRegistry.php app/Support/Location/PreferredCountryResolver.php app/Livewire/Pages/Dashboard/AccountSettings.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php app/Forms/SharedFormSchema.php app/Forms/InstitutionFormSchema.php app/Forms/VenueFormSchema.php app/Forms/InstitutionContributionFormSchema.php bootstrap/app.php routes/web.php tests/Unit/PreferredCountryResolverTest.php tests/Feature/PublicMarketSelectorTest.php tests/Feature/AccountSettingsPageTest.php tests/Feature/SharedFormSchemaTest.php tests/Feature/InstitutionContributionLocationPickerTest.php tests/Feature/EventSearchTest.php tests/Feature/InstitutionIndexTest.php tests/Feature/Laravel13CacheSerializationTest.php` => **pass**
  - `php artisan view:clear` => **compiled views cleared**

# Creation Country Requirement

- [x] Require `country_id` in institution and venue public creation forms instead of hiding it behind a default
- [x] Require `country_id` in institution and venue admin creation forms as well
- [x] Extend the shared-form regression coverage to prove all institution, venue, and speaker create flows now require country

## Review
- Updated [InstitutionFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/InstitutionFormSchema.php), [VenueFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/VenueFormSchema.php), and [InstitutionContributionFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/InstitutionContributionFormSchema.php) so institution and venue public creation flows now render a real `country_id` select and mark it as required instead of hiding a Malaysia default.
- Updated [SharedFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/SharedFormSchema.php) with an explicit `requireCountryField` option so the requirement is declared by the caller instead of being implied by hidden defaults.
- Updated the admin address sections in [InstitutionForm.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Institutions/Schemas/InstitutionForm.php) and [VenueForm.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Venues/Schemas/VenueForm.php) to require `country_id` too, matching the speaker correction.
- Extended [SharedFormSchemaTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/SharedFormSchemaTest.php) so it now proves required-country behavior for institution, venue, and speaker public creation forms plus the admin institution, venue, and speaker forms.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php --filter='requires country fields in institution and venue public creation forms|requires country fields in the admin institution and venue forms|requires country fields in speaker public creation forms|requires country fields in the admin speaker form'` => **4 passed**
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi app/Forms/InstitutionFormSchema.php app/Forms/VenueFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Filament/Resources/Institutions/Schemas/InstitutionForm.php app/Filament/Resources/Venues/Schemas/VenueForm.php tests/Feature/SharedFormSchemaTest.php` => **No errors**
  - `XDEBUG_MODE=off vendor/bin/pint --test app/Forms/InstitutionFormSchema.php app/Forms/VenueFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Filament/Resources/Institutions/Schemas/InstitutionForm.php app/Filament/Resources/Venues/Schemas/VenueForm.php tests/Feature/SharedFormSchemaTest.php tasks/lessons.md` => **pass**
  - `git diff --check -- app/Forms/InstitutionFormSchema.php app/Forms/VenueFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Filament/Resources/Institutions/Schemas/InstitutionForm.php app/Filament/Resources/Venues/Schemas/VenueForm.php tests/Feature/SharedFormSchemaTest.php tasks/lessons.md` => **clean**

# Speaker Slug Normalization

- [x] Add a shared speaker slug generator that builds slugs as `name[-sequence]-countrycode`
- [x] Route all new speaker creation entrypoints through the shared slug generator, including public quick-create, public speaker submission/contribution staging, contribution approval fallback creation, and Filament admin creation
- [x] Add a queued backfill command/job to regenerate existing speaker slugs in the new format
- [x] Add focused regression coverage and run verification for the speaker slug rules and backfill path

## Review
- Added [GenerateSpeakerSlugAction.php](/Users/Saiffil/Herd/majlisilmu/app/Actions/Speakers/GenerateSpeakerSlugAction.php) to centralize the new speaker slug rule. It builds slugs as `name[-sequence]-countrycode`, skips the country suffix when no country can be resolved, scopes duplicate numbering to exact-name matches inside the same country suffix, and keeps backfill ordering stable for existing duplicates by assigning sequence from deterministic `created_at + id` order.
- Routed speaker creation through that action in every new-speaker entrypoint:
  - [SpeakerFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/SpeakerFormSchema.php) quick-create
  - [ContributionEntityMutationService.php](/Users/Saiffil/Herd/majlisilmu/app/Services/ContributionEntityMutationService.php) staged public speaker creation used by `/sumbangan/penceramah/baru`
  - [ApproveContributionRequestAction.php](/Users/Saiffil/Herd/majlisilmu/app/Actions/Contributions/ApproveContributionRequestAction.php) fallback speaker creation during approval
  - [CreateSpeaker.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Speakers/Pages/CreateSpeaker.php) for Filament admin create
- Required `country_id` in the speaker public creation forms and the admin speaker form via [SpeakerContributionFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/SpeakerContributionFormSchema.php), [SharedFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/SharedFormSchema.php), and [SpeakerForm.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Speakers/Schemas/SpeakerForm.php), so new speaker records always persist the country that the slug depends on.
- Added queued backfill support with [BackfillSpeakerSlugs.php](/Users/Saiffil/Herd/majlisilmu/app/Jobs/BackfillSpeakerSlugs.php) and [QueueBackfillSpeakerSlugs.php](/Users/Saiffil/Herd/majlisilmu/app/Console/Commands/QueueBackfillSpeakerSlugs.php). Run `php artisan speakers:queue-slug-backfill` to enqueue the speaker slug regeneration job.
- Tightened speaker address handling in [SpeakerFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/SpeakerFormSchema.php) and [ContributionEntityMutationService.php](/Users/Saiffil/Herd/majlisilmu/app/Services/ContributionEntityMutationService.php) so speaker create flows can preserve a country-only address when needed for slug stability, without broadening the same behavior across other entity types.
- Added focused coverage in [SpeakerSlugGenerationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/SpeakerSlugGenerationTest.php) for quick-create, staged duplicate numbering, admin Filament create, queued backfill logic, command registration, null-country omission, and the literal numbered-name collision edge case.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/SpeakerSlugGenerationTest.php` => **7 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/SpeakerCreateOptionSchemaTest.php --filter='stores biography and institution pivot position when creating a speaker via create option'` => **1 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/ContributionWorkflowServiceTest.php --filter='creates staged pending speaker records with structured relation data'` => **1 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php --filter='requires country fields in speaker public creation forms|requires country fields in the admin speaker form'` => **2 passed**
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi app/Actions/Speakers/GenerateSpeakerSlugAction.php app/Console/Commands/QueueBackfillSpeakerSlugs.php app/Jobs/BackfillSpeakerSlugs.php app/Forms/SpeakerFormSchema.php app/Services/ContributionEntityMutationService.php app/Actions/Contributions/ApproveContributionRequestAction.php app/Filament/Resources/Speakers/Pages/CreateSpeaker.php tests/Feature/SpeakerSlugGenerationTest.php` => **No errors**
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi app/Forms/SharedFormSchema.php app/Forms/SpeakerContributionFormSchema.php app/Filament/Resources/Speakers/Schemas/SpeakerForm.php tests/Feature/SharedFormSchemaTest.php` => **No errors**
  - `XDEBUG_MODE=off vendor/bin/pint --test app/Actions/Speakers/GenerateSpeakerSlugAction.php app/Console/Commands/QueueBackfillSpeakerSlugs.php app/Jobs/BackfillSpeakerSlugs.php app/Forms/SpeakerFormSchema.php app/Forms/SharedFormSchema.php app/Forms/SpeakerContributionFormSchema.php app/Services/ContributionEntityMutationService.php app/Actions/Contributions/ApproveContributionRequestAction.php app/Filament/Resources/Speakers/Pages/CreateSpeaker.php app/Filament/Resources/Speakers/Schemas/SpeakerForm.php tests/Feature/SpeakerSlugGenerationTest.php tests/Feature/SharedFormSchemaTest.php tasks/lessons.md tasks/todo.md` => **pass**
  - `php artisan list --raw | rg speakers:queue-slug-backfill` => **command registered**
  - `git diff --check -- app/Actions/Speakers/GenerateSpeakerSlugAction.php app/Console/Commands/QueueBackfillSpeakerSlugs.php app/Jobs/BackfillSpeakerSlugs.php app/Forms/SpeakerFormSchema.php app/Forms/SharedFormSchema.php app/Forms/SpeakerContributionFormSchema.php app/Services/ContributionEntityMutationService.php app/Actions/Contributions/ApproveContributionRequestAction.php app/Filament/Resources/Speakers/Pages/CreateSpeaker.php app/Filament/Resources/Speakers/Schemas/SpeakerForm.php tests/Feature/SpeakerSlugGenerationTest.php tests/Feature/SharedFormSchemaTest.php tasks/lessons.md tasks/todo.md` => **clean**

# Institution Submission Mobile Spacing

- [x] Inspect the institution submission page shell and identify the mobile-specific border/spacing density
- [x] Reduce the nested card feel on mobile without changing the desktop layout direction
- [x] Verify the contribution page still renders and the Blade diff stays clean

## Review
- Tuned [submit-institution.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/livewire/pages/contributions/submit-institution.blade.php) for mobile by flattening the outer shell spacing, reducing container padding, and making the action buttons full-width on small screens so the page no longer feels like a stack of tightly nested bordered cards.
- Added a page-scoped mobile CSS block that softens the Filament form section chrome only on this route: smaller section radii, no shadow, tighter section spacing, and slightly rounder inputs/repeaters so the form reads as one mobile flow instead of several heavy card layers.
- Tightened the lower helper cards for mobile too, with smaller gaps and lighter framing while keeping the desktop card treatment unchanged from `sm` upward.
- Verification:
  - `vendor/bin/pint --test resources/views/livewire/pages/contributions/submit-institution.blade.php` => **pass**
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionPagesTest.php --filter='renders the dedicated institution contribution page|exposes Filament action handlers required by public contribution media uploads'` => **2 passed**
  - `git diff --check -- resources/views/livewire/pages/contributions/submit-institution.blade.php` => **clean**

# Horizon Installation And Configuration

- [x] Audit the current queue, Redis, scheduler, and admin access setup for Horizon readiness
- [x] Install `laravel/horizon` and publish the package assets/configuration
- [x] Switch queue runtime configuration to Redis-backed Horizon processing and align local/dev commands
- [x] Wire Horizon dashboard authorization and scheduler/deploy integration for this app
- [x] Run focused verification and document the result

## Review
- Installed `laravel/horizon` and published the Horizon scaffolding, then aligned the generated setup with Majlis Ilmu’s actual runtime instead of keeping the stock defaults. Queue execution now defaults to Redis in [queue.php](/Users/Saiffil/Herd/majlisilmu/config/queue.php), queued media conversions default to a dedicated `media` queue in [media-library.php](/Users/Saiffil/Herd/majlisilmu/config/media-library.php), and the local/example environment files now opt into Redis-backed queues with the matching retry and media queue variables.
- Reworked [horizon.php](/Users/Saiffil/Herd/majlisilmu/config/horizon.php) into three supervisors: one for `default`, one for the app’s existing notification queues (`notifications-inbox`, `notifications-mail`, `notifications-push`, `notifications-whatsapp`), and one for queued media conversions. Added a wildcard environment fallback plus per-queue wait thresholds so Horizon can monitor each named queue cleanly.
- Wired [HorizonServiceProvider.php](/Users/Saiffil/Herd/majlisilmu/app/Providers/HorizonServiceProvider.php) to the app’s existing admin-access rule (`User::hasApplicationAdminAccess()`), added the provider in [providers.php](/Users/Saiffil/Herd/majlisilmu/bootstrap/providers.php), and scheduled `horizon:snapshot` every five minutes in [console.php](/Users/Saiffil/Herd/majlisilmu/routes/console.php) so the dashboard throughput and wait-time graphs populate per the Laravel Horizon docs.
- Updated the local developer flow to use Horizon instead of the old queue worker path: `composer dev` now runs `php artisan horizon:listen`, `package.json` includes `chokidar` for watch-mode restarts, and the internal runbooks in [EMAIL_FEATURES.md](/Users/Saiffil/Herd/majlisilmu/docs/EMAIL_FEATURES.md) and [MAJLISILMU_TECHNICAL_DOCUMENTATION.md](/Users/Saiffil/Herd/majlisilmu/docs/MAJLISILMU_TECHNICAL_DOCUMENTATION.md) now describe the Redis/Horizon path instead of `queue:work`.
- Added a focused regression in [HorizonConfigurationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/HorizonConfigurationTest.php) covering the Horizon gate and the registered supervisor / snapshot configuration.
- Verification:
  - `vendor/bin/pint --test bootstrap/providers.php config/queue.php config/media-library.php config/horizon.php app/Providers/HorizonServiceProvider.php routes/console.php tests/Feature/HorizonConfigurationTest.php` => **pass**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/HorizonConfigurationTest.php` => **2 passed**
  - `php artisan route:list --path=horizon` => **22 Horizon routes registered**
  - `php artisan schedule:list | rg -n "horizon:snapshot|horizon-snapshot"` => **snapshot scheduled every 5 minutes**
  - `vendor/bin/phpstan analyse --ansi` => **No errors**
  - `git diff --check` => **clean**

# Institution Slug Normalization

- [x] Add a shared institution slug generator that builds slugs from `name + subdistrict + district + state + country code` and scopes duplicate numbering to the same exact name within the same subdistrict
- [x] Route all institution creation entrypoints through the shared slug generator, including public quick-create, public institution submission/contribution staging, contribution approval fallback creation, and Filament admin creation
- [x] Add a queued backfill command/job to regenerate existing institution slugs in the new format
- [x] Add focused regression coverage and run verification for the slug rules and backfill path

## Review
- Added [GenerateInstitutionSlugAction.php](/Users/Saiffil/Herd/majlisilmu/app/Actions/Institutions/GenerateInstitutionSlugAction.php) to centralize the new institution slug rule. It resolves locality names from address IDs, builds `name[-sequence]-subdistrict-district-state-countrycode`, omits empty segments, starts duplicate numbering from exact-name matches in the same locality, and keeps regeneration stable for existing duplicates by assigning their sequence from a deterministic `created_at + id` order during backfill.
- Wired that action into every institution creation path the user named:
  - [InstitutionFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/InstitutionFormSchema.php) quick-create, which covers the submit-event organizer/location create-option flow
  - [ContributionEntityMutationService.php](/Users/Saiffil/Herd/majlisilmu/app/Services/ContributionEntityMutationService.php) staged public institution creation used by `/sumbangan/institusi/baru`
  - [ApproveContributionRequestAction.php](/Users/Saiffil/Herd/majlisilmu/app/Actions/Contributions/ApproveContributionRequestAction.php) fallback institution creation during approval when a pending entity does not already exist
  - [CreateInstitution.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Institutions/Pages/CreateInstitution.php) so Filament admin creation uses the geographic slug instead of any manually entered temporary value
- Registered the queue command at the application bootstrap level in [app.php](/Users/Saiffil/Herd/majlisilmu/bootstrap/app.php), and added queued backfill support with [BackfillInstitutionSlugs.php](/Users/Saiffil/Herd/majlisilmu/app/Jobs/BackfillInstitutionSlugs.php) and [QueueBackfillInstitutionSlugs.php](/Users/Saiffil/Herd/majlisilmu/app/Console/Commands/QueueBackfillInstitutionSlugs.php). Run `php artisan institutions:queue-slug-backfill` to enqueue the backfill job; it processes institutions serially in queue order, regenerates deterministic slugs, avoids touching `updated_at`, busts the public listings cache once at the end, and intentionally does not create aliases or redirects for old slugs.
- Added focused coverage in [InstitutionSlugGenerationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/InstitutionSlugGenerationTest.php) for quick-create, staged duplicate numbering, admin Filament create, the queued backfill logic, the runtime queueing command, null locality omission, and the uniqueness fallback when a literal numbered institution name already occupies the expected duplicate slot.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/InstitutionSlugGenerationTest.php` => **7 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php --filter='stores description and contacts when creating an institution via quick-create|stores nested institution quick-create address data when picker mode is used'` => **2 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/ContributionWorkflowServiceTest.php --filter='creates staged pending institution records with structured relation data|approves institution create requests and promotes the proposer to owner'` => **2 passed**
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi bootstrap/app.php app/Actions/Institutions/GenerateInstitutionSlugAction.php app/Jobs/BackfillInstitutionSlugs.php app/Console/Commands/QueueBackfillInstitutionSlugs.php app/Forms/InstitutionFormSchema.php app/Services/ContributionEntityMutationService.php app/Actions/Contributions/ApproveContributionRequestAction.php app/Filament/Resources/Institutions/Pages/CreateInstitution.php tests/Feature/InstitutionSlugGenerationTest.php` => **No errors**
  - `XDEBUG_MODE=off vendor/bin/pint --test bootstrap/app.php app/Actions/Institutions/GenerateInstitutionSlugAction.php app/Jobs/BackfillInstitutionSlugs.php app/Console/Commands/QueueBackfillInstitutionSlugs.php app/Forms/InstitutionFormSchema.php app/Services/ContributionEntityMutationService.php app/Actions/Contributions/ApproveContributionRequestAction.php app/Filament/Resources/Institutions/Pages/CreateInstitution.php tests/Feature/InstitutionSlugGenerationTest.php tasks/todo.md tasks/lessons.md` => **pass**
  - `php artisan list --raw | rg institutions:queue-slug-backfill` => **command registered**
  - `git diff --check -- bootstrap/app.php app/Actions/Institutions/GenerateInstitutionSlugAction.php app/Jobs/BackfillInstitutionSlugs.php app/Console/Commands/QueueBackfillInstitutionSlugs.php app/Forms/InstitutionFormSchema.php app/Services/ContributionEntityMutationService.php app/Actions/Contributions/ApproveContributionRequestAction.php app/Filament/Resources/Institutions/Pages/CreateInstitution.php tests/Feature/InstitutionSlugGenerationTest.php tasks/todo.md tasks/lessons.md` => **clean**

# Institution Maps Embed Audit

- [x] Trace the `/institusi/*` map rendering path and confirm why it is calling a Google Maps API surface
- [x] Replace the institution-page map display with a non-API path if the page only needs outbound map navigation
- [x] Verify the public institution page no longer shows the rejected API message

## Review
- The `/institusi/{slug}` page was not failing because of the visible Google Maps button. The failing surface was the inline preview block in [⚡show.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/institutions/⚡show.blade.php), which preferred `https://www.google.com/maps/embed/v1/place?...` whenever `services.google.maps_api_key` existed and then fell back to `maps.googleapis.com/maps/api/staticmap`.
- That page does not need Google Maps Platform APIs. It now always uses the public embeddable URL form `https://www.google.com/maps?q=...&output=embed` when a normalized maps query is available, and otherwise falls back to the existing outbound Google Maps link. The API-backed embed and static map paths were removed from the institution page.
- Added a regression in [InstitutionShowPageTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/InstitutionShowPageTest.php) that sets a fake API key and proves the page still renders the non-API embed URL while not rendering Embed API or Static Maps API URLs.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/InstitutionShowPageTest.php` => **25 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/PublicPagesTest.php --filter='loads public detail pages|shows share actions on public series and reference pages'` => **2 passed**
  - `git diff --check -- resources/views/components/pages/institutions/⚡show.blade.php tests/Feature/InstitutionShowPageTest.php tasks/todo.md` => **clean**

# Uncommitted Audit

- [x] Audit the current uncommitted diff for regressions across the touched public and dashboard flows
- [x] Fix every actionable issue found during the audit
- [x] Re-run focused verification and document the outcome

## Review
- Audit found one real dashboard regression in [institution-dashboard.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/livewire/pages/dashboard/institution-dashboard.blade.php): the `Add Child Event` shortcut had been redirected through the new institution-scoped submit flow, which would have overridden parent-program defaults for speaker-organized parents. Restored that link to the original parent-aware public submit route and tightened [DashboardPagesTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/DashboardPagesTest.php).
- Audit also found one flaky public-pages regression in [PublicPagesTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/PublicPagesTest.php): the series-card location assertion depended on the factory's random `event_format`, but the view intentionally hides venue text for online events. Locked the test to `EventFormat::Physical` so it matches the page contract.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/PublicPagesTest.php` => **22 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **20 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php` => **70 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/SubmitEventEntityAccessTest.php` => **5 passed**
  - `git diff --check -- tests/Feature/PublicPagesTest.php resources/views/livewire/pages/dashboard/institution-dashboard.blade.php tests/Feature/DashboardPagesTest.php` => **clean**

# Institution Dashboard Submission Flow

- [x] Replace the institution dashboard "Create Advanced Program" shortcut with an institution-scoped submit-event entrypoint
- [x] Lock the submit-event form to the selected institution in dashboard mode and auto-approve those submissions
- [x] Update the success-state copy and focused tests, then run verification

## Review
- Added a dedicated authenticated route at [web.php](/Users/Saiffil/Herd/majlisilmu/routes/web.php) for institution-scoped event submission and rewired the institution dashboard CTA plus parent-program child-event links in [institution-dashboard.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/livewire/pages/dashboard/institution-dashboard.blade.php).

# Venue, Reference, And Event Slug Normalization

- [x] Add shared slug generators for venue, reference, and event using the requested formats and duplicate-numbering rules
- [x] Route every new-creation entrypoint through those generators, including public quick-create flows, public submit-event persistence, advanced parent-program creation, and Filament admin create pages
- [x] Add queued backfill jobs and commands for venue, reference, and event slug regeneration
- [x] Add focused regression coverage for new slug formats, duplicate handling, and queued backfill behavior
- [x] Run targeted Pest coverage plus Pint and record the verification results

## Review
- Added shared slug actions for venues, references, and events in [GenerateVenueSlugAction.php](/Users/Saiffil/Herd/majlisilmu/app/Actions/Venues/GenerateVenueSlugAction.php), [GenerateReferenceSlugAction.php](/Users/Saiffil/Herd/majlisilmu/app/Actions/References/GenerateReferenceSlugAction.php), and [GenerateEventSlugAction.php](/Users/Saiffil/Herd/majlisilmu/app/Actions/Events/GenerateEventSlugAction.php) so each entity now uses a deterministic slug format with stable duplicate numbering.
- Routed venue, reference, and event creation through those generators across public and admin entrypoints, including [VenueFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/VenueFormSchema.php), [create.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/submit-event/create.blade.php), [CreateVenue.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Venues/Pages/CreateVenue.php), [CreateEvent.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Events/Pages/CreateEvent.php), and [CreateAdvancedParentProgramAction.php](/Users/Saiffil/Herd/majlisilmu/app/Actions/Events/CreateAdvancedParentProgramAction.php).
- Added queueable backfill jobs and console commands in [BackfillVenueSlugs.php](/Users/Saiffil/Herd/majlisilmu/app/Jobs/BackfillVenueSlugs.php), [BackfillReferenceSlugs.php](/Users/Saiffil/Herd/majlisilmu/app/Jobs/BackfillReferenceSlugs.php), [BackfillEventSlugs.php](/Users/Saiffil/Herd/majlisilmu/app/Jobs/BackfillEventSlugs.php), [QueueBackfillVenueSlugs.php](/Users/Saiffil/Herd/majlisilmu/app/Console/Commands/QueueBackfillVenueSlugs.php), [QueueBackfillReferenceSlugs.php](/Users/Saiffil/Herd/majlisilmu/app/Console/Commands/QueueBackfillReferenceSlugs.php), and [QueueBackfillEventSlugs.php](/Users/Saiffil/Herd/majlisilmu/app/Console/Commands/QueueBackfillEventSlugs.php).
- Updated the generated-slug admin forms in [InstitutionForm.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Institutions/Schemas/InstitutionForm.php), [VenueForm.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Venues/Schemas/VenueForm.php), and [EventForm.php](/Users/Saiffil/Herd/majlisilmu/app/Filament/Resources/Events/Schemas/EventForm.php) so create pages no longer require manual slug input before the server-side generators run.
- Added focused regression coverage in [VenueReferenceEventSlugGenerationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/VenueReferenceEventSlugGenerationTest.php) and kept the institution baseline covered by [InstitutionSlugGenerationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/InstitutionSlugGenerationTest.php).

- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/VenueReferenceEventSlugGenerationTest.php` => **15 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/InstitutionSlugGenerationTest.php` => **8 passed**

# Full Verification Gate

# Uncommitted Geolocation Audit

- [x] Audit the current uncommitted geolocation-permission diff for behavioral regressions
- [x] Fix the actionable issues found in the audit
- [x] Re-run focused verification and document the result

## Review
- Audited the current public geolocation-permission work and fixed one concrete regression in [⚡home.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/⚡home.blade.php): the mobile `Berdekatan Saya` shortcut was calling `document.querySelector('[x-data]')`, which resolves to the header Alpine root instead of the hero search component once multiple Alpine roots exist on the page.
- Replaced that brittle selector-based call with an Alpine event (`mi-home-nearby`) so the mobile shortcut triggers the same nearby flow as the desktop button without depending on internal `__x` state or DOM ordering.
- Re-verified the homepage denied-permission behavior after the fix:
  - mobile browser simulation on `https://majlisilmu.test/` with mocked denied geolocation now redirects to `/majlis`
  - desktop browser simulation on `https://majlisilmu.test/` still redirects to `/majlis` on denied geolocation
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/PublicPagesTest.php` => **22 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php` => **70 passed**
  - `git diff --check -- resources/views/components/pages/⚡home.blade.php resources/views/livewire/pages/events/index.blade.php tasks/todo.md tasks/lessons.md` => **No diff formatting issues**

# Public Nearby Permission Gate

- [x] Add a device-scoped geolocation-permission helper and expose it to public pages
- [x] Keep the nearby CTA visible on `/` and `/majlis`, but trigger the browser permission flow on click and show an inline note when access is denied
- [x] Hide the `/majlis` radius controls unless browser geolocation permission is granted
- [x] Run focused verification and document the result

## Review
- Added [PublicGeolocationPermission.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Location/PublicGeolocationPermission.php) plus a raw-cookie exemption in [bootstrap/app.php](/Users/Saiffil/Herd/majlisilmu/bootstrap/app.php) so public pages can persist browser geolocation permission server-side without pushing this preference into the database or cache.
- Updated [app.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/layouts/app.blade.php), [⚡home.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/⚡home.blade.php), and [index.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/livewire/pages/events/index.blade.php) so the `Berhampiran` CTA stays visible, attempts permission on click, and now falls back differently by surface: the homepage redirects into `/majlis` when location is denied, while `/majlis` itself keeps the inline localized note.
- Updated the homepage and `/majlis` click handlers so they no longer stop at `PermissionStatus.state === 'denied'`; each button press now re-invokes `navigator.geolocation.getCurrentPosition(...)`, allowing browsers that support re-prompting on repeat user gestures to do so.
- Gated the `/majlis` inline and advanced radius controls in [Index.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/Events/Index.php) and [AdvancedFiltersPanel.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/Events/AdvancedFiltersPanel.php) using the permission cookie and Alpine visibility hooks, while keeping the button itself available.
- Added localized denial copy in [en.json](/Users/Saiffil/Herd/majlisilmu/resources/lang/en.json), [ms.json](/Users/Saiffil/Herd/majlisilmu/resources/lang/ms.json), and [ms_MY.json](/Users/Saiffil/Herd/majlisilmu/resources/lang/ms_MY.json), and tightened the public regressions in [PublicPagesTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/PublicPagesTest.php) and [EventSearchTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/EventSearchTest.php).
- Verification:
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/PublicPagesTest.php` => **22 passed**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php` => **70 passed**
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi app/Support/Location/PublicGeolocationPermission.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php tests/Feature/PublicPagesTest.php tests/Feature/EventSearchTest.php` => **No errors**
  - `vendor/bin/pint --test tests/Feature/EventSearchTest.php` => **pass**
  - `git diff --check -- app/Support/Location/PublicGeolocationPermission.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php resources/views/layouts/app.blade.php resources/views/components/pages/⚡home.blade.php resources/views/livewire/pages/events/index.blade.php resources/lang/en.json resources/lang/ms.json resources/lang/ms_MY.json tests/Feature/PublicPagesTest.php tests/Feature/EventSearchTest.php bootstrap/app.php tasks/todo.md tasks/lessons.md` => **No diff formatting issues**
  - Browser verification via Chrome DevTools:
    - `https://majlisilmu.test/` with mocked denied geolocation kept the CTA visible and showed the inline Malay denial note
    - `https://majlisilmu.test/majlis` with mocked denied geolocation kept `Berhampiran` visible, showed the inline note, and kept `Radius (km)` hidden
    - `https://majlisilmu.test/majlis` with mocked granted geolocation redirected to `?lat=3.139&lng=101.6869&sort=distance` and revealed `Radius (km)`
    - `https://majlisilmu.test/` and `https://majlisilmu.test/majlis` with mocked denied permission both recorded **2 geolocation API calls after 2 clicks**, proving repeat taps still reach the browser geolocation request path instead of being blocked by the app
    - `https://majlisilmu.test/` with mocked denied geolocation now redirects to `https://majlisilmu.test/majlis` and does **not** leave the inline denial note behind on the homepage hero

- [x] Run full `phpstan`, `pest`, `rector`, and `pint` across the current worktree
- [x] Fix every failure exposed by the gate, including unrelated residual regressions
- [x] Re-run the full gate until all four tools pass
- [x] Document the final verification and audit results

## Review
- Cleared the full verification gate after fixing the remaining runtime and test regressions:
  - [database/seeders/EventSeeder.php](/Users/Saiffil/Herd/majlisilmu/database/seeders/EventSeeder.php) now reads the institution address `country_id` via `data_get(..., 132)` so phpstan sees a concrete integer fallback.
  - [tests/Feature/GeographyFormCountryCodeTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/GeographyFormCountryCodeTest.php) now seeds Malaysia as `id = 132`, matching the Malaysia-only federal-territory rule used by the app.
  - [resources/views/components/pages/speakers/⚡show.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/speakers/⚡show.blade.php) now imports `EventKeyPersonRole`, fixing the blade rendering failure caused by enum-string casting.
  - [tests/Feature/Laravel13CacheSerializationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/Laravel13CacheSerializationTest.php) now follows the live country-visibility cache path (`states_all_v1`) instead of the removed legacy key.
  - [tests/Feature/SignalsPrecisionAndFunnelsTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/SignalsPrecisionAndFunnelsTest.php) now asserts against the current millisecond-based session duration field instead of the removed `duration_seconds` attribute.
  - [tests/Feature/InstitutionShowPageTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/InstitutionShowPageTest.php) and [tests/Feature/SpeakerShowPageTimingTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/SpeakerShowPageTimingTest.php) now send the `user_timezone` cookie unencrypted, matching the current browser behavior after the raw-cookie exemption in [bootstrap/app.php](/Users/Saiffil/Herd/majlisilmu/bootstrap/app.php).
- Final full verification:
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi` => **pass**
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact` => **1050 passed (5380 assertions)**
  - `XDEBUG_MODE=off vendor/bin/rector process --ansi --clear-cache --no-progress-bar --output-format=console` => **pass**
  - `XDEBUG_MODE=off vendor/bin/pint --test` => **pass**

# Federal Territory Cleanup

- [x] Verify the live database no longer contains dummy district rows or district references under Kuala Lumpur, Putrajaya, and Labuan
- [x] Add a forward cleanup migration so already-migrated environments stay normalized to `state -> subdistrict`
- [x] Sweep public location rendering for stale district-only assumptions and normalize remaining paths
- [x] Re-run focused verification for the cleanup and document unrelated residual failures separately

## Review
- Verified the current local database is already clean for Malaysian federal territories: `districts = 0`, `subdistricts_with_districts = 0`, and `addresses_with_districts = 0` for Kuala Lumpur, Putrajaya, and Labuan.
- Added [2026_03_29_090000_cleanup_federal_territory_district_rows.php](/Users/Saiffil/Herd/majlisilmu/database/migrations/2026_03_29_090000_cleanup_federal_territory_district_rows.php) as an idempotent forward migration so environments that had already applied the earlier hierarchy migration still get the federal-territory cleanup re-enforced without relying on edited migration history.
- Normalized the last stale rendering paths to use the shared formatter:
  - [⚡show.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/speakers/⚡show.blade.php) now renders the speaker location badge through `AddressHierarchyFormatter`.
  - [_event-card.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/series/_event-card.blade.php) now renders series event-card locations through `AddressHierarchyFormatter`, so federal-territory events show `subdistrict, state` instead of dropping subdistricts.
- Added focused regressions in [PublicPagesTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/PublicPagesTest.php) and [SpeakerShowPageTimingTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/SpeakerShowPageTimingTest.php) for the normalized location output.
- Verification:
  - `php artisan migrate --force` => **applied `2026_03_29_090000_cleanup_federal_territory_district_rows`**
  - `php artisan tinker --execute="..."` cleanup audit => **districts: 0, subdistricts_with_districts: 0, addresses_with_districts: 0**
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicPagesTest.php --filter="loads public detail pages|shows federal territory event cards on series pages with subdistrict and state|shows share actions on public series and reference pages"` => **3 passed (18 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SpeakerShowPageTimingTest.php --filter="deduplicates matching speaker subdistrict and district labels in the speaker location badge|hides state when district is kuala lumpur putrajaya or labuan"` => **2 passed (6 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php --filter="loads subdistricts directly from federal territory states and clears district persistence|does not skip districts for non malaysian states with federal territory names"` => **2 passed (11 assertions)**
  - `vendor/bin/pint resources/views/components/pages/speakers/⚡show.blade.php` => **pass after auto-fix**
  - `git diff --check -- resources/views/components/pages/speakers/⚡show.blade.php resources/views/components/pages/series/_event-card.blade.php tests/Feature/PublicPagesTest.php tests/Feature/SpeakerShowPageTimingTest.php database/migrations/2026_03_29_090000_cleanup_federal_territory_district_rows.php` => **No diff formatting issues**
- Residual unrelated failures:
  - `vendor/bin/pest --parallel --compact tests/Feature/SpeakerShowPageTimingTest.php --filter="shows prayer-relative timing text on speaker page instead of absolute time|renders event end time in event timezone on speaker page"` still fails on pre-existing time-output expectations (`03:15 AM`, `8:40 PM`) and is not caused by the federal-territory cleanup.

# Malaysia-Only Federal Territory Scope Audit

- [x] Scope the federal-territory shortcut to Malaysia instead of matching same-named states globally
- [x] Add a regression that proves non-Malaysian `Kuala Lumpur` / `Putrajaya` / `Labuan` states still require districts
- [x] Run focused verification for the patched helper/schema behavior
- [x] Review and audit the current uncommitted diff for remaining risks

## Review
- Tightened [FederalTerritoryLocation.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Location/FederalTerritoryLocation.php) so the special `state -> subdistrict` path only applies to states whose `country_id` is Malaysia (`132`), instead of any state record worldwide whose name happens to be Kuala Lumpur, Putrajaya, or Labuan.
- Tightened [2026_03_28_140000_convert_federal_territories_to_state_subdistrict_hierarchy.php](/Users/Saiffil/Herd/majlisilmu/database/migrations/2026_03_28_140000_convert_federal_territories_to_state_subdistrict_hierarchy.php) with the same Malaysia scope so the one-off data migration cannot null out districts or delete rows for non-Malaysian states with those names.
- Added cache-safe regression coverage in [SharedFormSchemaTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/SharedFormSchemaTest.php) that flushes the helper cache per test and proves a non-Malaysian `Kuala Lumpur` still shows district options and only loads subdistricts after district selection.
- Audit also found a performance regression in [EventSearchService.php](/Users/Saiffil/Herd/majlisilmu/app/Services/EventSearchService.php): the new always-present `country_id` default on `/majlis` was forcing every event search onto the database path even though Typesense already supports `country_id`. Removed that fallback trigger and added a regression in [EventSearchTypesenseFilterTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/EventSearchTypesenseFilterTest.php).
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php` => **23 passed (112 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Unit/ResolveGooglePlaceSelectionActionTest.php` => **5 passed (33 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTypesenseFilterTest.php` => **7 passed (9 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="displays the events index page|filters events by country"` => **2 passed (11 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Support/Location/FederalTerritoryLocation.php app/Services/EventSearchService.php tests/Feature/SharedFormSchemaTest.php tests/Feature/EventSearchTypesenseFilterTest.php app/Forms/SharedFormSchema.php app/Actions/Location/ResolveGooglePlaceSelectionAction.php database/migrations/2026_03_28_140000_convert_federal_territories_to_state_subdistrict_hierarchy.php` => **No errors**
  - `vendor/bin/pint --test app/Support/Location/FederalTerritoryLocation.php app/Services/EventSearchService.php tests/Feature/SharedFormSchemaTest.php tests/Feature/EventSearchTypesenseFilterTest.php tasks/todo.md tasks/lessons.md database/migrations/2026_03_28_140000_convert_federal_territories_to_state_subdistrict_hierarchy.php` => **pass**
  - `git diff --check -- app/Support/Location/FederalTerritoryLocation.php app/Services/EventSearchService.php tests/Feature/SharedFormSchemaTest.php tests/Feature/EventSearchTypesenseFilterTest.php tasks/todo.md tasks/lessons.md database/migrations/2026_03_28_140000_convert_federal_territories_to_state_subdistrict_hierarchy.php` => **No diff formatting issues**

# Public Submission Country Visibility

- [x] Extend the public submission address schema so `country_id` is a real state input instead of a hard-coded Malaysia default
- [x] Apply the device-only country selector visibility toggle to `/sumbangan/institusi/baru` and the public submit-event institution/venue quick-create flows
- [x] Update picker/default-country behavior so those public flows keep `country_id` internally even when the selector is hidden
- [x] Add focused regression coverage and rerun the live browser verification on the requested routes

## Review
- Extended [SharedFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/SharedFormSchema.php) with public-submission country helpers so the same cookie-backed visibility rule can drive standalone submission pages and the submit-event quick-create forms without reintroducing a writable Malaysia-only default.
- Updated [InstitutionContributionFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/InstitutionContributionFormSchema.php), [InstitutionFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/InstitutionFormSchema.php), and [VenueFormSchema.php](/Users/Saiffil/Herd/majlisilmu/app/Forms/VenueFormSchema.php) so the country field is part of the public address state in both dedicated contribution pages and event-submission quick-create modals.
- Updated [SubmitInstitution.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/Contributions/SubmitInstitution.php) and [create.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/submit-event/create.blade.php) so picker-applied addresses carry the current hidden `country_id` as a fallback and use the correct cascade reset depth for visible vs hidden country fields.
- Generalized [ResolveGooglePlaceSelectionAction.php](/Users/Saiffil/Herd/majlisilmu/app/Actions/Location/ResolveGooglePlaceSelectionAction.php) to resolve country/state/district/subdistrict outside Malaysia, and removed the hard-coded Malaysia region lock from [institution-location-picker.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/filament/schemas/components/institution-location-picker.blade.php).
- Added focused regressions in [InstitutionContributionLocationPickerTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/InstitutionContributionLocationPickerTest.php), [SharedFormSchemaTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/SharedFormSchemaTest.php), and [ResolveGooglePlaceSelectionActionTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Unit/ResolveGooglePlaceSelectionActionTest.php).
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionContributionLocationPickerTest.php` => **8 passed (43 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php --filter="country fields in public location picker forms|defaults address country_id to malaysia when null is submitted directly to the model"` => **3 passed (8 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Unit/ResolveGooglePlaceSelectionActionTest.php` => **5 passed (33 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Forms/SharedFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Forms/InstitutionFormSchema.php app/Forms/VenueFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php app/Actions/Location/ResolveGooglePlaceSelectionAction.php tests/Feature/InstitutionContributionLocationPickerTest.php tests/Feature/SharedFormSchemaTest.php tests/Unit/ResolveGooglePlaceSelectionActionTest.php` => **No errors**
  - `vendor/bin/pint --test app/Forms/SharedFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Forms/InstitutionFormSchema.php app/Forms/VenueFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php app/Actions/Location/ResolveGooglePlaceSelectionAction.php tests/Feature/InstitutionContributionLocationPickerTest.php tests/Feature/SharedFormSchemaTest.php tests/Unit/ResolveGooglePlaceSelectionActionTest.php` => **pass**
  - `git diff --check -- app/Forms/SharedFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Forms/InstitutionFormSchema.php app/Forms/VenueFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php app/Actions/Location/ResolveGooglePlaceSelectionAction.php resources/views/components/pages/submit-event/create.blade.php resources/views/filament/schemas/components/institution-location-picker.blade.php tests/Feature/InstitutionContributionLocationPickerTest.php tests/Feature/SharedFormSchemaTest.php tests/Unit/ResolveGooglePlaceSelectionActionTest.php tasks/todo.md tasks/lessons.md` => **No diff formatting issues**
  - Chrome MCP live pass:
    - with the device toggle enabled in `/tetapan-akaun`, `/sumbangan/institusi/baru` shows the `Country` selector and the `/hantar-majlis` institution quick-create modal shows it too
    - after turning the toggle off and saving, `/sumbangan/institusi/baru` hides `Country`, and the `/hantar-majlis` institution quick-create modal hides it as well
    - restored the browser toggle to its original enabled state after verification

# Public Country Filter Visibility Preference

- [x] Add a shared device-scoped cookie helper for the public country-filter visibility preference
- [x] Expose the toggle on `/tetapan-akaun` without adding any database column
- [x] Hide the country controls on `/majlis` and `/institusi` by default while preserving internal `country_id` defaults and filtering
- [x] Add focused regression coverage and run targeted verification plus a live browser pass

## Review
- Added [PublicCountryFilterVisibility.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Location/PublicCountryFilterVisibility.php) as the single device-scoped cookie helper for this preference and exempted its raw cookie in [app.php](/Users/Saiffil/Herd/majlisilmu/bootstrap/app.php), matching the existing browser-managed timezone cookie pattern.
- Updated [AccountSettings.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/Dashboard/AccountSettings.php) to expose a device-only toggle in `/tetapan-akaun`, preload it from the cookie, and queue the cookie on save without adding any user column.
- Updated [Index.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/Events/Index.php), [AdvancedFiltersPanel.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/Events/AdvancedFiltersPanel.php), [index.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/livewire/pages/events/index.blade.php), and [⚡index.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/components/pages/institutions/⚡index.blade.php) so `/majlis` and `/institusi` hide the country selector by default, keep `country_id` internally resolved for query/filter logic, and still surface non-default country state when it is materially active.
- Added focused regression coverage in [AccountSettingsPageTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/AccountSettingsPageTest.php), [EventSearchTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/EventSearchTest.php), and [InstitutionIndexTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/InstitutionIndexTest.php).
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AccountSettingsPageTest.php` => **14 passed (115 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php --filter="shows location scope controls on institution index|shows the institution country selector when the device preference cookie enables it|filters institutions by country|defaults institutions country filter from an unencrypted browser timezone cookie"` => **4 passed (12 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="defaults the majlis country filter from an unencrypted browser timezone cookie|hides the majlis country selector unless the device preference cookie enables it|filters events by country|displays the events index page"` => **4 passed (15 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Support/Location/PublicCountryFilterVisibility.php app/Livewire/Pages/Dashboard/AccountSettings.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php tests/Feature/AccountSettingsPageTest.php tests/Feature/EventSearchTest.php tests/Feature/InstitutionIndexTest.php` => **No errors**
  - `vendor/bin/pint --test app/Support/Location/PublicCountryFilterVisibility.php app/Livewire/Pages/Dashboard/AccountSettings.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php bootstrap/app.php tests/Feature/AccountSettingsPageTest.php tests/Feature/EventSearchTest.php tests/Feature/InstitutionIndexTest.php "resources/views/components/pages/institutions/⚡index.blade.php" resources/views/livewire/pages/events/index.blade.php tasks/todo.md tasks/lessons.md` => **pass**
  - `git diff --check -- app/Support/Location/PublicCountryFilterVisibility.php app/Livewire/Pages/Dashboard/AccountSettings.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php bootstrap/app.php tests/Feature/AccountSettingsPageTest.php tests/Feature/EventSearchTest.php tests/Feature/InstitutionIndexTest.php "resources/views/components/pages/institutions/⚡index.blade.php" resources/views/livewire/pages/events/index.blade.php tasks/todo.md tasks/lessons.md` => **No diff formatting issues**
  - Chrome MCP live pass:
    - signed into `/tetapan-akaun`, confirmed the toggle is off by default, then verified `/institusi` and `/majlis` hide the country selector
    - enabled the toggle in `/tetapan-akaun`, saved it, then verified `/institusi` shows the country selector again and `/majlis` shows the country selector inside Advanced Filters
    - removed the temporary local verification user after the browser pass

# Event Filter Country Default

- [x] Add `country_id` to the public `/majlis` location filters and thread it through search + saved-search handoff
- [x] Auto-select the filter country from saved timezone, then `CF-IPCountry`, then Malaysia
- [x] Add focused regression coverage and run targeted verification

## Review
- Added [PreferredCountryResolver.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Location/PreferredCountryResolver.php) to resolve the default public country from the current request using user timezone first, Cloudflare `CF-IPCountry` second, and Malaysia (`132`) last.
- Updated the public events page and advanced-filters panel so `country_id` is a first-class location filter, state options depend on the selected country, and institution/venue search options are constrained by country as well.
- Updated [EventSearchService.php](/Users/Saiffil/Herd/majlisilmu/app/Services/EventSearchService.php) so `country_id` participates in both Typesense filter generation and the database fallback path, and forces the database path when used.
- Updated [index.blade.php](/Users/Saiffil/Herd/majlisilmu/resources/views/livewire/pages/events/index.blade.php) and [SavedSearches/Index.php](/Users/Saiffil/Herd/majlisilmu/app/Livewire/Pages/SavedSearches/Index.php) so save-search handoff and captured filter labels preserve the selected country.
- Added cache invalidation for the new `countries_all_v1` and `states_all_v1` keys in [PublicListingsCache.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Cache/PublicListingsCache.php).
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Unit/PreferredCountryResolverTest.php` => **3 passed (3 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTypesenseFilterTest.php` => **6 passed (8 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SavedSearchPageTest.php --filter="prefills country filter from query string when saving searches"` => **1 passed (1 assertion)**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="filters events by country"` => **1 passed (2 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Support/Location/PreferredCountryResolver.php app/Support/Cache/PublicListingsCache.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php app/Services/EventSearchService.php app/Livewire/Pages/SavedSearches/Index.php tests/Unit/PreferredCountryResolverTest.php tests/Feature/EventSearchTest.php tests/Feature/EventSearchTypesenseFilterTest.php tests/Feature/SavedSearchPageTest.php` => **No errors**
  - `vendor/bin/pint --test app/Support/Location/PreferredCountryResolver.php app/Support/Cache/PublicListingsCache.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php app/Services/EventSearchService.php app/Livewire/Pages/SavedSearches/Index.php tests/Unit/PreferredCountryResolverTest.php tests/Feature/EventSearchTest.php tests/Feature/EventSearchTypesenseFilterTest.php tests/Feature/SavedSearchPageTest.php` => **pass**
  - `git diff --check -- app/Support/Location/PreferredCountryResolver.php app/Support/Cache/PublicListingsCache.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php app/Services/EventSearchService.php resources/views/livewire/pages/events/index.blade.php app/Livewire/Pages/SavedSearches/Index.php tests/Unit/PreferredCountryResolverTest.php tests/Feature/EventSearchTest.php tests/Feature/EventSearchTypesenseFilterTest.php tests/Feature/SavedSearchPageTest.php tasks/lessons.md` => **No diff formatting issues**

# Address Country Constraint

- [x] Enforce `addresses.country_id` as `NOT NULL` with a safe backfill migration
- [x] Harden address writes so null `country_id` input is normalized before persistence
- [x] Add focused regression coverage and run verification

## Review
- Added [2026_03_28_210000_enforce_not_null_country_id_on_addresses_table.php](/Users/Saiffil/Herd/majlisilmu/database/migrations/2026_03_28_210000_enforce_not_null_country_id_on_addresses_table.php) to backfill existing null `addresses.country_id` values to Malaysia (`132`) and then enforce the column as `NOT NULL`.
- Added a model-level safeguard in [Address.php](/Users/Saiffil/Herd/majlisilmu/app/Models/Address.php) so normal model saves coerce a missing `country_id` to `132` before hitting the database.
- Added [EnsuresMalaysiaCountry.php](/Users/Saiffil/Herd/majlisilmu/database/factories/Concerns/EnsuresMalaysiaCountry.php) and updated the institution, speaker, and venue factories so address-producing test fixtures always point at a real Malaysia row instead of leaving admin forms with invalid relationship values.
- Reverted the accidental edit to the original [2026_01_21_114400_create_addresses_table.php](/Users/Saiffil/Herd/majlisilmu/database/migrations/2026_01_21_114400_create_addresses_table.php) migration and kept the constraint change only in the new forward migration.
- Updated [EventSearchTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/EventSearchTest.php) to match the current page payload, which now legitimately includes Filament notifications and actions assets.
- Verification:
  - `php artisan migrate --force` => **both new migrations applied**
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php --filter="defaults address country_id to malaysia when null is submitted directly to the model"` => **1 passed (2 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php --filter="creates an address when only a google maps url is provided"` => **1 passed (4 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicSubmissionLockActionsTest.php` => **9 passed (78 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="displays the events index page"` => **1 passed (9 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Models/Address.php tests/Feature/SharedFormSchemaTest.php database/migrations/2026_03_28_210000_enforce_not_null_country_id_on_addresses_table.php database/factories/Concerns/EnsuresMalaysiaCountry.php database/factories/InstitutionFactory.php database/factories/SpeakerFactory.php database/factories/VenueFactory.php` => **No errors**
  - `vendor/bin/pint --test tests/Feature/EventSearchTest.php database/migrations/2026_01_21_114400_create_addresses_table.php database/migrations/2026_03_28_210000_enforce_not_null_country_id_on_addresses_table.php database/factories/Concerns/EnsuresMalaysiaCountry.php database/factories/InstitutionFactory.php database/factories/SpeakerFactory.php database/factories/VenueFactory.php app/Models/Address.php` => **pass**
  - `git diff --check` => **No diff formatting issues**

# Federal Territory Hierarchy

- [x] Add a shared federal-territory location rule and use it across forms, filters, resolvers, and display helpers
- [x] Migrate Kuala Lumpur, Putrajaya, and Labuan from `state -> district -> subdistrict` to `state -> subdistrict`
- [x] Update seeders/importers and admin geography CRUD to support nullable `subdistrict.district_id` for those three states
- [x] Update public/admin address entry, search/filter, and formatting behavior for federal territories
- [x] Add focused migration, form, resolver, filter, and display regression coverage and run verification

## Review
- Added a shared `FederalTerritoryLocation` helper and applied it across shared forms, Filament address schemas, the public event filters, the public institution index, and Google place resolution so Kuala Lumpur, Putrajaya, and Labuan consistently bypass the district layer.
- Added a migration to make `subdistricts.district_id` nullable, null out federal-territory district references on both `subdistricts` and `addresses`, and remove the placeholder districts after detaching them. The migration matches both plain state names and `Wilayah Persekutuan ...` state names.
- Updated geography seeders and the generated postcode importer so federal-territory subdistricts attach directly to `state_id`, district seeding skips those three states, and postcode imports resolve subdistricts by state when no district should exist.
- Updated admin/public address entry and filtering so district is hidden for federal territories, subdistrict options load directly from state, and saved address payloads forcibly clear `district_id` for those states.
- Added focused regression coverage for resolver behavior, shared form helper behavior, admin federal-territory subdistrict creation, federal-territory event filtering, postcode import behavior, and the public location rendering cases.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Unit/ResolveGooglePlaceSelectionActionTest.php` => **3 passed (24 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php` => **19 passed (98 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/GeographyFormCountryCodeTest.php` => **3 passed (19 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventShowPageTest.php` => **18 passed (51 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionShowPageTest.php` => **24 passed (77 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SpeakerShowPageTimingTest.php` => **10 passed (33 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php` => **1 passed (43 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="federal territory subdistricts without requiring a district"` => **1 passed (3 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Support/Location/FederalTerritoryLocation.php app/Forms/SharedFormSchema.php app/Actions/Location/ResolveGooglePlaceSelectionAction.php app/Filament/Resources/Institutions/Schemas/InstitutionForm.php app/Filament/Resources/Speakers/Schemas/SpeakerForm.php app/Filament/Resources/Venues/Schemas/VenueForm.php app/Filament/Resources/Subdistricts/Schemas/SubdistrictForm.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php app/Models/State.php database/seeders/DistrictSeeder.php database/seeders/SubdistrictSeeder.php database/seeders/GeneratedFileFinalFixedPoskodSeeder.php tests/Unit/ResolveGooglePlaceSelectionActionTest.php tests/Feature/SharedFormSchemaTest.php tests/Feature/GeographyFormCountryCodeTest.php tests/Feature/EventSearchTest.php tests/Feature/EventShowPageTest.php tests/Feature/InstitutionShowPageTest.php tests/Feature/SpeakerShowPageTimingTest.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php` => **No errors**
  - `vendor/bin/pint --test app/Support/Location/FederalTerritoryLocation.php app/Forms/SharedFormSchema.php app/Actions/Location/ResolveGooglePlaceSelectionAction.php app/Filament/Resources/Institutions/Schemas/InstitutionForm.php app/Filament/Resources/Speakers/Schemas/SpeakerForm.php app/Filament/Resources/Venues/Schemas/VenueForm.php app/Filament/Resources/Subdistricts/Schemas/SubdistrictForm.php app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php app/Models/State.php database/migrations/2026_03_28_140000_convert_federal_territories_to_state_subdistrict_hierarchy.php database/seeders/DistrictSeeder.php database/seeders/SubdistrictSeeder.php database/seeders/GeneratedFileFinalFixedPoskodSeeder.php tests/Unit/ResolveGooglePlaceSelectionActionTest.php tests/Feature/SharedFormSchemaTest.php tests/Feature/GeographyFormCountryCodeTest.php tests/Feature/EventSearchTest.php tests/Feature/EventShowPageTest.php tests/Feature/InstitutionShowPageTest.php tests/Feature/SpeakerShowPageTimingTest.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php` => **pass**
  - `git diff --check` => **No diff formatting issues**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php` currently has an unrelated existing failure on the initial JS asset payload assertion (`/js/filament/notifications/notifications.js` is present in the response), so the new hierarchy-specific event filter was verified with a focused filter run instead of relying on the full file result.

# Legacy Geography Cache Cleanup

- [x] Remove the unused `states_my` cache key from runtime invalidation and tests
- [x] Keep coverage focused on the active `states_my_v2` cache path
- [x] Run targeted verification for the cache cleanup

## Review
- Removed the dead `states_my` cache key from [PublicListingsCache.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Cache/PublicListingsCache.php). No runtime code was reading it anymore; the public event filters already use only `states_my_v2`.
- Updated [PublicListingCacheInvalidationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/PublicListingCacheInvalidationTest.php) so the cache-busting assertions now track only active keys.
- Tightened [Laravel13CacheSerializationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/Laravel13CacheSerializationTest.php) to verify the current `states_my_v2` safe-cache path directly instead of keeping a legacy-key compatibility scenario for an unused key.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicListingCacheInvalidationTest.php` => **4 passed (1105 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/Laravel13CacheSerializationTest.php` => **4 passed (18 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Support/Cache/PublicListingsCache.php tests/Feature/PublicListingCacheInvalidationTest.php tests/Feature/Laravel13CacheSerializationTest.php` => **No errors**
  - `vendor/bin/pint --test app/Support/Cache/PublicListingsCache.php tests/Feature/PublicListingCacheInvalidationTest.php tests/Feature/Laravel13CacheSerializationTest.php` => **pass**
  - `git diff --check` => **No diff formatting issues**

# Geography Cache Invalidation

- [x] Bust frontend geography-related caches when countries, states, districts, or subdistricts are created, updated, or deleted in admin
- [x] Reuse the existing public listings cache invalidation path instead of duplicating cache-key logic
- [x] Add focused regression coverage and rerun targeted verification

## Review
- Added a shared `GeographyObserver` for `Country`, `State`, `District`, and `Subdistrict`, and registered it in [AppServiceProvider.php](/Users/Saiffil/Herd/majlisilmu/app/Providers/AppServiceProvider.php) alongside the existing public-listing observers.
- Reused the existing [PublicListingsCache.php](/Users/Saiffil/Herd/majlisilmu/app/Support/Cache/PublicListingsCache.php) path instead of introducing new cache-key duplication, so geography edits now clear the same frontend listing/filter caches that already include `states_my` / `states_my_v2` and the related event listing payloads.
- Extended [PublicListingCacheInvalidationTest.php](/Users/Saiffil/Herd/majlisilmu/tests/Feature/PublicListingCacheInvalidationTest.php) to prove create, update, and delete operations on geography records clear those frontend caches.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicListingCacheInvalidationTest.php` => **4 passed (1124 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Observers/GeographyObserver.php app/Providers/AppServiceProvider.php tests/Feature/PublicListingCacheInvalidationTest.php` => **No errors**
  - `vendor/bin/pint --test app/Observers/GeographyObserver.php app/Providers/AppServiceProvider.php tests/Feature/PublicListingCacheInvalidationTest.php` => **pass**
  - `git diff --check` => **No diff formatting issues**

# Geography Country Code Hardening

- [x] Remove manual `country_code` editing from the state, district, and subdistrict admin forms
- [x] Ensure those forms still persist `country_code` from the selected country record
- [x] Add focused regression coverage and run targeted verification

## Review
- Replaced the writable `country_code` text input in the state, district, and subdistrict admin schemas with a hidden dehydrated field derived from the selected `country_id`, so the user can no longer manipulate that value independently from the chosen country.
- Kept the rest of the cascading geography behavior unchanged: selecting a country still drives the available state and district options, but `country_code` is now always written from the backing `countries.iso2` record during form dehydration.
- Added `tests/Feature/GeographyFormCountryCodeTest.php` to verify both schema shape and actual create-page persistence, including a forged `country_code` payload being overridden by the selected country.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/GeographyFormCountryCodeTest.php` => **2 passed (15 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/States/Schemas/StateForm.php app/Filament/Resources/Districts/Schemas/DistrictForm.php app/Filament/Resources/Subdistricts/Schemas/SubdistrictForm.php tests/Feature/GeographyFormCountryCodeTest.php` => **No errors**
  - `vendor/bin/pint --test app/Filament/Resources/States/Schemas/StateForm.php app/Filament/Resources/Districts/Schemas/DistrictForm.php app/Filament/Resources/Subdistricts/Schemas/SubdistrictForm.php tests/Feature/GeographyFormCountryCodeTest.php` => **pass**
  - `git diff --check` => **No diff formatting issues**

# Geography Admin Management

- [x] Add admin resources for countries, states, districts, and subdistricts
- [x] Add safe deletion rules so geography records cannot be removed while still depended on by lower-level geography or addresses
- [x] Add targeted admin access and deletion-constraint tests, then run verification

## Review
- Added four new admin Filament resources under the admin panel for `Country`, `State`, `District`, and `Subdistrict`, each with create/list/edit pages, hierarchy-aware forms, and usage-count tables.
- Added a reusable `GetGeographyDeletionBlockReasonAction` plus a shared Filament delete-guard trait so delete actions stay available for cleanup but are disabled with a reason when a record still has dependent geography or address usage.
- Added direct `addresses()` relations on `Country`, `District`, and `Subdistrict` so usage counts and delete guards work without database foreign keys.
- Protected the default Malaysia country record (`id = 132`) from deletion because current public-location flows still intentionally assume that default country.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AdminResourcesCoverageTest.php` => **2 passed (29 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/GeographyDeletionRulesTest.php` => **5 passed (5 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Actions/Location/GetGeographyDeletionBlockReasonAction.php app/Filament/Resources/Geography/Concerns/HasGeographyDeletionGuard.php app/Filament/Resources/Countries app/Filament/Resources/States app/Filament/Resources/Districts app/Filament/Resources/Subdistricts app/Models/Country.php app/Models/District.php app/Models/Subdistrict.php tests/Feature/AdminResourcesCoverageTest.php tests/Feature/GeographyDeletionRulesTest.php` => **No errors**
  - `vendor/bin/pint app/Actions/Location/GetGeographyDeletionBlockReasonAction.php app/Filament/Resources/Geography/Concerns/HasGeographyDeletionGuard.php app/Filament/Resources/Countries app/Filament/Resources/States app/Filament/Resources/Districts app/Filament/Resources/Subdistricts app/Models/Country.php app/Models/District.php app/Models/Subdistrict.php tests/Feature/AdminResourcesCoverageTest.php tests/Feature/GeographyDeletionRulesTest.php` => **formatted**
  - `git diff --check` => **No diff formatting issues**

# Institution Media Layout

- [x] Make the public institution `Imej Latar` uploader span the full row like `Galeri`
- [x] Verify the public institution contribution page renders the media section with both uploaders full width

## Review
- Updated `app/Forms/InstitutionContributionFormSchema.php` so the public `cover` upload uses `->columnSpanFull()`, matching the existing full-width `gallery` field while keeping the change scoped to `/sumbangan/institusi/baru`.
- Browser verification on `https://majlisilmu.test/sumbangan/institusi/baru` shows both FilePond uploaders rendering at the same width (`1006px`).
- `vendor/bin/pest --parallel --compact tests/Feature/InstitutionContributionLocationPickerTest.php` => **5 passed (32 assertions)**

# Institution Cover Upload Regression

- [x] Reproduce the broken `Imej Latar` uploader on `/sumbangan/institusi/baru` and compare it with the working `Galeri` uploader
- [x] Trace the rendered uploader markup back to the institution contribution form schema and identify the regression source
- [x] Implement the minimal fix and verify it with a browser check plus targeted tests/static analysis

## Review
- Confirmed the public institution cover uploader rendered a FilePond drop label whose `for` attribute no longer pointed to the real `.filepond--browser` input, while the gallery uploader still pointed correctly.
- Traced the regression to `resources/js/filament/form-accessibility.js`: the shared label-repair helper was not prioritizing FilePond’s actual file input for the drop label and could let unrelated generated inputs inside the image editor steal the old target.
- Updated the helper to repair only the actual FilePond drop label to the `.filepond--browser` input while keeping nested image-editor labels bound to their own controls.
- Verification:
  - `npm run build` => pass
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionContributionLocationPickerTest.php` => **5 passed (32 assertions)**
  - Chrome MCP browser check on `https://majlisilmu.test/sumbangan/institusi/baru` => cover upload accepted `/var/folders/bt/sgc0xyln09v2t7by90v0tlhm0000gn/T/TemporaryItems/NSIRD_screencaptureui_vad2zD/Screenshot 2026-03-28 at 12.45.53 PM.png` after clicking the `Imej Latar` control

# Canonical Google Maps Normalization

- [x] Inspect the current Google Maps address write paths and consolidate the normalization plan
- [x] Add a shared server-side normalizer for canonical URL generation and conservative Places API (New) recovery
- [x] Wire the normalizer into picker selection, pasted-link blur/save, and admin/shared address persistence without duplicate lookups
- [x] Add regression coverage for canonicalization, deduped lookups, and save-time persistence
- [x] Run targeted tests, static analysis, formatting, and a browser sanity check if the UI flow changes materially

## Review
- Added `NormalizeGoogleMapsInputAction` as the single server-side normalizer for Google Maps inputs. It canonicalizes stored URLs, unwraps consent links, optionally resolves short/cid links, and only uses Places API (New) lookups when the current input still lacks enough structure.
- Wired the normalizer through picker selection, shared address creation, contribution mutation saves, and the admin institution/venue address relationship sections. Existing picker-selected `place_id` + coordinates now skip redundant server lookups on blur and save.
- Removed the old `Address` model hook that was still doing hidden URL mutation/network work on save, so Google link normalization is now explicit and testable instead of implicit in the model layer.
- Added regression coverage for picker canonicalization, short-link recovery, cid-link recovery, coordinate-only links, unchanged unresolved retry suppression, and shared form/institution submission persistence.
- Live browser sanity check on `https://majlisilmu.test/sumbangan/institusi/baru` confirmed blur-time canonicalization for direct coordinate links. Pasted short-link recovery stayed in graceful-warning mode until the server-side config is enabled, which matches the intended fallback behavior.

# Location Mode Separation

- [x] Separate institution submission location picker mode from manual fallback mode
- [x] Hide the raw Google Maps URL input when picker mode is enabled
- [x] Keep manual paste mode off the paid Places API while preserving local normalization
- [x] Update targeted tests and rerun browser verification

## Review
- The dedicated institution submission form now treats picker mode and manual fallback mode as distinct states instead of exposing the raw Google Maps URL input in both.
- When `services.google.places_enabled` is on for `/sumbangan/institusi/baru`, the page renders the Google Places picker, hides the editable `google_maps_url` input, and keeps the location requirement enforced through a hidden required field so submit still fails if no place was selected.
- When the picker is unavailable or explicitly disabled, the manual `Google Maps URL` field is shown again and the form keeps local normalization on but disables Places API lookups for that page. Pasted links can still be canonicalized and short links can still be resolved via redirects, but the fallback flow no longer calls the paid `places.googleapis.com` endpoints or auto-fills the visible address fields from API results.
- Added regression coverage for:
  - enabled page rendering without the raw Google Maps URL field
  - picker mode submit-time validation
  - manual fallback mode saving a pasted short link with local canonicalization but without Places API calls
  - schema-level picker/manual component switching and local-normalization-only persistence behavior
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionContributionLocationPickerTest.php` => **5 passed (31 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php` => **12 passed (65 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Forms/SharedFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php tests/Feature/InstitutionContributionLocationPickerTest.php tests/Feature/SharedFormSchemaTest.php` => **No errors**
  - `vendor/bin/pint --test app/Forms/SharedFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php tests/Feature/InstitutionContributionLocationPickerTest.php tests/Feature/SharedFormSchemaTest.php` => **pass**
  - `git diff --check` => **No diff formatting issues**
  - Chrome MCP live check on `https://majlisilmu.test/sumbangan/institusi/baru` => **picker visible, raw Google Maps URL field hidden, Waze field still present**

# Event Quick-Create Location Mode

- [x] Apply the same picker/manual-fallback split to the event submission quick-create institution form
- [x] Apply the same picker/manual-fallback split to the event submission quick-create venue form
- [x] Support nested quick-create address payloads while preserving existing non-picker entry points
- [x] Add focused schema and persistence tests plus a browser sanity check

## Review
- Extended `InstitutionFormSchema::createOptionForm()` and `VenueFormSchema::createOptionForm()` with an `includeLocationPicker` mode used only by `/hantar-majlis`, leaving existing admin and other quick-create entry points unchanged by default.
- In picker mode, both quick-create forms now render the Google location picker and hide the editable `google_maps_url` field when `services.google.places_enabled` is on. In manual fallback mode they show the raw field again, keep local normalization on, and keep paid Places API lookups off.
- Added a generic `applyLocationPickerSelection()` Livewire handler to the event submission page and updated the picker view to target whatever schema state path it is mounted under, so it works both on the dedicated institution page and inside Filament create-option modals.
- Updated institution and venue quick-create persistence to accept nested `address` payloads, allowing the modal picker mode to save normalized address data without changing older flat payload callers.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php` => **17 passed (88 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionContributionLocationPickerTest.php` => **5 passed (32 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Unit/NormalizeGoogleMapsInputActionTest.php` => **7 passed (33 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Forms/InstitutionFormSchema.php app/Forms/VenueFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php resources/views/components/pages/submit-event/create.blade.php tests/Feature/SharedFormSchemaTest.php` => **No errors**
  - `vendor/bin/pint --test app/Forms/InstitutionFormSchema.php app/Forms/VenueFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php tests/Feature/SharedFormSchemaTest.php` => **pass**
  - `git diff --check` => **No diff formatting issues**
  - Chrome MCP live check on `https://majlisilmu.test/hantar-majlis?step=form.penganjur-lokasi%3A%3Adata%3A%3Awizard-step` => **institution quick-create modal shows the picker and does not show the raw Google Maps URL field**

# Log Triage

- [x] Inspect Laravel and browser logs for repeated runtime errors
- [x] Trace the highest-signal errors back to current code paths
- [x] Summarize confirmed or likely bugs with evidence and likely fixes
- [x] Sweep every public frontend route in Chrome MCP with real navigation and route-appropriate interaction
- [x] Fix any regressions found during the public-route sweep and rerun targeted verification

## Review
- Confirmed a current public-flow regression: navigating from the home page to the submit-event page with `wire:navigate` leaves Filament Alpine helpers undefined, breaking the wizard UI even though a direct page load works. The affected path is the header submit link in the public layout plus the page-scoped Filament asset include on the submit page.
- Confirmed a current schema mismatch on addresses: long resolved Google Maps URLs can exceed the original `varchar(255)` column, and the widening migration to `text` exists but is still pending in this environment.
- Browser logs also show a repeated Filament rich-editor crash on the submit flow (`domFromPos` on `null`) across multiple Filament patch versions, which points to a remaining submit-page editor lifecycle issue worth reproducing and isolating separately from the asset-loading regression.
- Continued the public-route sweep in Chrome MCP across the canonical guest pages, legacy aliases, public auth pages, share/calendar utilities, and sitemap endpoints using live seeded records for event, institution, speaker, series, and reference detail routes.
- Confirmed a current locale regression on the public submit page: the English route still leaked Malay copy in the AI upload block because two submit-upload strings were missing from the JSON locale files.
- Confirmed a current public asset regression: browsers still issued a failing favicon request during the route sweep, creating avoidable 404 noise on public pages even though the visible page loads were otherwise healthy.
- Fixes applied:
  - moved the shared public Filament styles/scripts into the main public layout so Livewire navigation lands on already-bootstrapped pages
  - made the page-scoped Filament asset partial subtract the now-global bundles instead of re-emitting duplicates
  - hardened the submit-event rich editor by disabling the table toolbar button and floating toolbars on that public form
  - ran the pending `google_maps_url` widening migration so the addresses column now uses `text`
  - added the missing public submit-upload translation keys to the English and supported locale JSON files so `/hantar-majlis?lang=en` renders that upload helper block fully in English
  - added focused coverage in `tests/Feature/PublicPagesTest.php` for the English submit-page upload copy and the public favicon route
  - added a concrete root `public/favicon.ico` asset so public pages stop relying on the broken implicit fallback request path during browser navigation
- Verification:
  - Chrome MCP: opened `/`, clicked the header submit link, confirmed `/hantar-majlis` booted without Filament Alpine errors, typed into the rich editor, and rechecked the console with no JS errors
  - Chrome MCP: re-swept `/`, `/majlis`, `/majlis/{slug}`, `/institusi`, `/institusi/{slug}`, `/penceramah`, `/penceramah/{slug}`, `/siri/{slug}`, `/rujukan/{slug}`, `/tentang-kami`, `/hantar-majlis`, `/hantar-majlis?lang=en`, `/login`, `/register`, `/forgot-password`, `/submit-event`, `/submit-event/success`, `/events`, `/institutions`, `/speakers`, `/majlis/{slug}/kalendar.ics`, `/peta-laman.xml`, and `/kongsi/payload?...`
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicPagesTest.php`
  - `vendor/bin/pint --dirty --format agent`
  - `php artisan migrate --path=database/migrations/2026_03_25_203000_change_google_maps_url_to_text_on_addresses_table.php --force`
  - `php artisan tinker --execute="dump(DB::table('information_schema.columns')->where('table_schema', 'public')->where('table_name', 'addresses')->where('column_name', 'google_maps_url')->value('data_type'));"`
  - `vendor/bin/pest --parallel tests --filter='AddressGoogleMapsUrlNormalizationTest|SharedFormSchemaTest|SubmitEventMediaTest'`
  - `git diff --check`
- Residual note: Chrome DevTools still reports a generic `No label associated with a form field (count: 9)` issue on the public submit form. The remaining offenders appear to come from internal Filament composite controls such as searchable selects/date widgets rather than the page-level upload block that was fixed here, so that accessibility audit needs a dedicated follow-up instead of another broad route-sweep patch.

# Location Picker Browser Verification

- [x] Run a live browser test against `/sumbangan/institusi/baru` with a real Google Maps key and Places API (New)
- [x] Inspect the saved institution payload after submit to verify persisted geography IDs and coordinates
- [x] Fix any browser-only regressions found in the picker flow
- [x] Re-run targeted verification after the browser-driven fix

## Review
- Live browser testing confirmed the new Google picker loads correctly with the configured Maps JavaScript API key, returns Places API (New) predictions, renders the confirmation map, and submits the institution form end to end.
- The first live submit exposed a browser-only regression: `ResolveGooglePlaceSelectionAction` correctly resolved `district_id` and `subdistrict_id`, but Filament's dependent `state_id -> district_id -> subdistrict_id` reset hooks treated the autofill as a manual state change and cleared both IDs before submit.
- Fixed the issue by adding an internal non-dehydrated `cascade_reset_guard` field to the shared address schema and setting it during `applyPlaceSelection()`. The state and district reset hooks now skip exactly the two autofill-driven updates, while manual user changes still clear downstream selects as before.
- Final verification:
  - Chrome MCP: reran the live picker flow, confirmed the submit request preserved `district_id=144` and `subdistrict_id=1416`, and confirmed the saved address row persisted those IDs plus coordinates and `google_place_id`
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionContributionLocationPickerTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Forms/SharedFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php`
  - `vendor/bin/pint --test app/Forms/SharedFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php`
  - `git diff --check`

# Audit Follow-up Pass

- [x] Cover the remaining admin audit blind spots for media changes and direct Filament relationship syncs
- [x] Add focused regression coverage for media, Filament relationship syncs, and membership audit events
- [x] Review the full uncommitted diff and fix any issues found
- [x] Verify the admin audit flow in Chrome MCP
- [x] Pass `vendor/bin/phpstan analyse --ansi`, `vendor/bin/rector process --dry-run --no-progress-bar --memory-limit=2G`, `vendor/bin/pest --parallel --compact`, and `vendor/bin/pint --test`

## Review
- Extended the audit follow-up to capture media collection mutations and direct Filament relationship syncs with explicit custom audit events instead of leaving those admin-side changes as blind spots.
- Replaced the vendor audit UI surface with app-owned Filament audit resources/relation managers so the admin panel uses the app UUID audit model, keeps restore disabled, and renders predictable labels/routes.
- Fixed the last browser-visible rough edges discovered in Chrome MCP: broken integer audit record URLs, raw plugin-facing labels, and unreadable relation snapshot presentation.
- Stabilized the new media audit regression test under parallel Pest by preventing Media Library conversions from running inline in that audit-focused spec.
- Verification:
  - Chrome MCP on `https://admin.majlisilmu.test/audits` and a live audit record view
  - `vendor/bin/phpstan analyse --ansi`
  - `vendor/bin/pest --parallel --compact`
  - `vendor/bin/pint --test`
  - `vendor/bin/rector process --dry-run --no-progress-bar --memory-limit=2G app/Models/Concerns/AuditsModelChanges.php tests/Feature/AdminAuditFollowUpTest.php app/Filament/RelationManagers/AuditsRelationManager.php app/Filament/Resources/Audits/AuditResource.php app/Filament/Resources/Audits/Pages/ListAudits.php app/Filament/Resources/Audits/Pages/ViewAudit.php app/Observers/AuditedMediaObserver.php app/Support/Auditing/AuditValuePresenter.php app/Providers/AppServiceProvider.php`
  - `git diff --check`

# Filament Audit Integration

- [x] Confirm the Tapp Network Filament auditing plugin is compatible with the current Filament and Laravel Auditing versions
- [x] Install and configure the Filament auditing plugin for the admin UI
- [x] Expand Laravel Auditing coverage to the admin-managed models that are currently not auditable
- [x] Expose the audits relation manager on the relevant admin and ahli Filament resources
- [x] Add focused regression coverage for audit recording and Filament audit visibility
- [x] Run formatting, tests, static analysis, and command verification

## Review
- Installed and wired `tapp/filament-auditing` into the admin panel, registered the vendor theme source, and explicitly loaded the package views so the global Filament audit resource renders reliably in this app.
- Standardized the audited model integration through `App\Models\Concerns\AuditsModelChanges`, enabled array payload auditing, added value redaction for sensitive fields, and attached Laravel Auditing to the admin-managed models that were previously missing coverage.
- Added morph-map aliases for every newly auditable model so audit writes do not fail under the app's enforced morph-map policy, and overrode the plugin's permissive default `audit` / `restoreAudit` gates so audit access is restricted and restore stays disabled.
- Added focused regression coverage for audit relation-manager registration, audit gate access, morph-map coverage, tag update auditing, and password redaction.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AdminAuditIntegrationTest.php`
  - `vendor/bin/phpstan analyse --ansi`
  - `vendor/bin/pint $(git diff --name-only --diff-filter=ACMR -- '*.php')`
  - `git diff --check`
  - `php artisan route:list --name=filament.admin.resources.audits.index`
- Known limitation: this integration audits model mutations. Pivot-only changes and Spatie Media Library attachment rows are not yet first-class audit records under the current UUID-based `audits` schema, so those would need a separate follow-up if you want them captured with the same fidelity.

# Google Consent Maps URL Unwrapping

- [x] Detect and unwrap `https://consent.google.com/ml?continue=...` before saving Google Maps URLs
- [x] Cover the consent redirect case in focused address normalization tests
- [x] Verify the resolver and record the result

## Review
- Root cause: Google short-link expansion can stop on `https://consent.google.com/ml?continue=...`, and the resolver treated that as the final URL. That left the consent wrapper persisted instead of the underlying canonical Maps URL.
- Fix: `Address::resolveGoogleMapsUrl()` now unwraps Google consent URLs before host detection and again after the `maps.app.goo.gl` HTTP resolution path, so both directly pasted consent URLs and short links that land on consent resolve to the real Maps URL before normalization/extracting coordinates.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Unit/AddressGoogleMapsUrlNormalizationTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Models/Address.php tests/Unit/AddressGoogleMapsUrlNormalizationTest.php`
  - `vendor/bin/pint --test app/Models/Address.php tests/Unit/AddressGoogleMapsUrlNormalizationTest.php tasks/lessons.md`
  - `git diff --check`

# Google Maps Place URL Preservation

- [x] Trace why shortened Google Maps place links are being collapsed into `maps/search` URLs
- [x] Align the address schema and normalization so canonical place URLs are preserved without an arbitrary string cap
- [x] Add/update focused tests and record verification

## Review
- Root cause: `Address::resolveGoogleMapsUrl()` correctly resolves `maps.app.goo.gl` short links to the canonical Google place URL, but the old `addresses.google_maps_url` string column and matching normalization logic imposed an arbitrary length ceiling that collapsed longer canonical URLs into a `maps/search` query URL.
- Fix:
  - changed `addresses.google_maps_url` to a `text` column via a safe migration
  - removed the Google Maps URL compaction logic from `Address` so resolved canonical URLs are preserved as-is
  - removed the Google Maps `maxLength(...)` caps from the shared address form plus the admin institution and venue forms
- Verification:
  - `php artisan migrate --pretend --path=database/migrations/2026_03_25_203000_change_google_maps_url_to_text_on_addresses_table.php`
  - `vendor/bin/pest --parallel --compact tests/Unit/AddressGoogleMapsUrlNormalizationTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Models/Address.php app/Forms/SharedFormSchema.php app/Filament/Resources/Institutions/Schemas/InstitutionForm.php app/Filament/Resources/Venues/Schemas/VenueForm.php tests/Unit/AddressGoogleMapsUrlNormalizationTest.php`
  - `vendor/bin/pint --test app/Models/Address.php app/Forms/SharedFormSchema.php app/Filament/Resources/Institutions/Schemas/InstitutionForm.php app/Filament/Resources/Venues/Schemas/VenueForm.php tests/Unit/AddressGoogleMapsUrlNormalizationTest.php database/migrations/2026_03_25_203000_change_google_maps_url_to_text_on_addresses_table.php`
  - `git diff --check`

# S3 Public Media Access Fix

- [x] Confirm why public S3 media URLs were returning `AccessDenied`
- [x] Apply the minimal safe fix for public media without exposing private evidence uploads
- [x] Verify the reported object URL is publicly readable

## Review
- Root cause: the bucket had no public bucket policy, while S3 Block Public Access had `BlockPublicAcls=true` and `IgnorePublicAcls=true`. The app-generated URL was correct, but the object remained ACL-private and anonymous reads were denied.
- Important constraint: forcing Laravel/Flysystem object visibility to `public` is the wrong fix here. `PutObjectAcl` is explicitly blocked on this bucket, so uploads must continue without public ACLs.
- Fix: added a bucket policy that grants anonymous `s3:GetObject` only for the public media prefixes generated by `MediaPathGenerator`: `events/*`, `institutions/*`, `speakers/*`, `venues/*`, `series/*`, `references/*`, `inspirations/*`, and `donation-channels/*`. Sensitive prefixes such as `reports/*` and `membership-claims/*` remain private.
- Verification:
  - `Storage::disk('s3')->exists(...)` => `true`
  - `Storage::disk('s3')->getVisibility(...)` still reports `private`, which is expected because access now comes from bucket policy rather than ACLs
  - `curl -I` on `https://majlisilmu.s3.ap-southeast-5.amazonaws.com/institutions/019d/019d1da4-b976-710f-9a76-536320389343/cover/masjid-wilayah-persekutuan-01kmgqep.jpg?v=1774382963` => `HTTP/1.1 200 OK`
  - `GetBucketPolicyStatus` => `IsPublic=true`

# S3 Media Driver Fix

- [x] Confirm the runtime S3 adapter is missing from the installed Composer dependencies
- [x] Add the required Flysystem S3 adapter as a production dependency
- [x] Verify the adapter class resolves and document the result

## Review
- Root cause: the app is configured with `MEDIA_DISK=s3`, and the media models all call `->useDisk(config('media-library.disk_name'))`, but the root project did not require `league/flysystem-aws-s3-v3`. That left production installs without `League\Flysystem\AwsS3V3\PortableVisibilityConverter`, so Laravel failed while constructing the `s3` disk.
- Fix: added `league/flysystem-aws-s3-v3` as a root runtime dependency. Composer installed the matching AWS SDK stack transitively.
- Verification:
  - `php -r "require 'vendor/autoload.php'; echo class_exists('League\\Flysystem\\AwsS3V3\\PortableVisibilityConverter') ? 'yes' : 'no';"` => `yes`
  - `php artisan tinker --execute="dump(get_class(Storage::disk('s3')->getAdapter()));"` => `League\\Flysystem\\AwsS3V3\\AwsS3V3Adapter`
  - `composer show | rg '^(aws/aws-sdk-php|league/flysystem-aws-s3-v3)\\s'`
  - `vendor/bin/pest --parallel --compact tests/Feature/MediaConversionsTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/SubmitEventMediaTest.php` currently fails on a separate `CouldNotLoadImage` test fixture/conversion issue in the existing suite
  - `git diff --check`

# Filament Scoped Assets Follow-up

- [x] Trace the scoped Filament asset helper against app-registered Filament assets
- [x] Restore the custom public helper assets without reintroducing the full global Filament bundle
- [x] Add regression coverage and rerun focused verification

## Review
- `resources/views/partials/filament-assets.blade.php` now renders the `app` Filament package separately before the page-specific package list, so public pages keep the custom helper scripts registered in `AppServiceProvider` (`close-on-select` and `user-timezone`) without falling back to the old global `@filamentScripts` behavior.
- This keeps the scoped asset-loading strategy intact for `/majlis` and other public pages while restoring the runtime contract for custom Filament extensions on public forms such as `/submit-event`.
- Added a focused regression test in `tests/Feature/SubmitEventReviewPreviewTest.php` that asserts the submit-event page ships both helper assets, which would have failed with the broken helper.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/SubmitEventReviewPreviewTest.php`
  - `vendor/bin/pest --parallel --compact --filter='(displays the events index page|primes the default events search cache for the unfiltered first page|displays the homepage successfully)' tests/Feature`
  - `vendor/bin/phpstan analyse --ansi resources/views/partials/filament-assets.blade.php tests/Feature/SubmitEventReviewPreviewTest.php`
  - `vendor/bin/pint --test resources/views/partials/filament-assets.blade.php tests/Feature/SubmitEventReviewPreviewTest.php`
  - Runtime spot-check via `curl` on `https://majlisilmu.test/submit-event` confirms both `close-on-select.js` and `user-timezone.js` are emitted again alongside `x-close-on-select`.

# Majlis Page Performance Investigation

- [x] Profile `https://majlisilmu.test/majlis` in the browser to capture network, rendering, and interaction bottlenecks
- [x] Trace the Laravel/Livewire code path, queries, and caching for the `/majlis` page
- [x] Apply the smallest justified changes to improve perceived and measured responsiveness
- [x] Verify the impact with targeted performance checks, tests, and static analysis as needed

## Review
- The unfiltered first-page cache path was effectively bypassed because `Index::events()` always passed `time_scope=upcoming` into `EventSearchService`, so `usesDefaultSearchCache()` never saw an empty filter set. Normalizing the default upcoming scope back to `null` restored the existing `default_events_search_v2` hot path for the public `/majlis` landing state.
- `EventSearchService::cardRelationships()` was eager-loading speaker avatar media for every event card even though the `/majlis` card UI never reads event speakers. Removing that relationship cut unnecessary queries and model hydration from both initial loads and Livewire search updates.
- `layouts.app` was also shipping the Flux runtime on every public page even though the app layout does not render Flux components. Removing `@fluxScripts` eliminated one unused `flux.js` request from `/majlis`.
- Session and cache were still backed by PostgreSQL, so every page load paid for session reads and cache I/O on the primary database. The app now defaults to non-database stores (`file` without Redis, `redis` when production Redis is configured), and the local environment now runs `SESSION_DRIVER=redis` plus `CACHE_STORE=redis`.
- Filament assets are no longer injected globally from `layouts.app`. Public and mixed pages now opt in explicitly through a shared `partials.filament-assets` include, and `/majlis` only requests the packages it actually uses (`filament/support` and `filament/schemas` on first paint, with form component scripts lazy-loaded when the advanced filter panel opens).
- Browser verification on `https://majlisilmu.test/majlis` after the changes:
  - warm document response improved from about `580 ms app / 225 ms DB` to about `303 ms app / 52 ms DB`
  - warm performance trace improved from about `LCP 1063 ms / TTFB 643 ms` to about `LCP 725 ms / TTFB 287 ms`
  - Livewire search update for `fiqh` improved from about `513 ms app / 184 ms DB` to about `340 ms app / 68 ms DB`
- Browser verification on `https://majlisilmu.test/majlis` after the second pass:
  - warm document response improved again to about `281 ms app / 25 ms DB`
  - reload trace improved to about `LCP 598 ms / TTFB 381 ms`
  - Livewire search update for `fiqh` improved again to about `323 ms app / 40 ms DB`
  - initial `/majlis` asset payload no longer includes `flux.js`, `filament/actions.js`, `filament/notifications.js`, or `filament/tables.js`
  - homepage `/` now ships no Filament assets at all
- Remaining bottlenecks are now mostly environmental and asset-level rather than page-specific code:
  - render blocking is now mostly the font CSS chain (`fonts.googleapis.com` -> `fonts.gstatic.com`) plus Filament's shared stylesheet/font on pages that still use Filament UI
  - `livewire.js` remains a meaningful fixed cost on public Livewire pages
  - search itself is still database-backed because Scout remains on the `collection` driver instead of Typesense
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='(displays the events index page|primes the default events search cache for the unfiltered first page|rehydrates the default events search cache safely from the database cache store)'`
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(displays the events index page|primes the default events search cache for the unfiltered first page|displays the homepage successfully|contains livewire components on the homepage)'`
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(shows a submission preview section on submit event page|renders the account settings page with profile and notifications tabs|renders the dedicated institution contribution page|renders the dedicated speaker contribution page|keeps reviewer context fields on update suggestion pages|shows the reported institution clearly on the public report page|redirects guests to login for membership claim routes)'`
  - `php artisan view:cache`
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php app/Services/EventSearchService.php tests/Feature/EventSearchTest.php tests/Feature/HomePageTest.php`
  - `vendor/bin/pint --test config/cache.php config/session.php tests/Feature/EventSearchTest.php tests/Feature/HomePageTest.php`
  - `git diff --check`

# Account Prayer Institutions

- [x] Inspect the current account-settings flow and existing institution-select patterns
- [x] Add daily and Friday prayer institution preferences to the user model and account-settings page
- [x] Extend account-settings coverage for optional saves, invalid picks, and stale saved preferences
- [x] Run verification for migration safety, formatting, static analysis, and tests

## Review
- Added two nullable user-level institution preferences, `daily_prayer_institution_id` and `friday_prayer_institution_id`, to keep the data as first-class profile state rather than opaque notification metadata.
- Extended the existing account-settings profile form to include a dedicated prayer-institutions section with async searchable institution selectors. Search is limited to active verified institutions, while saved-label resolution still works for historical IDs so the form can load even if an institution later becomes inactive or unverified.
- Kept the behavior private and settings-only: the new preferences only save through the profile form and do not affect notification, event-builder, or public-directory behavior.
- Added feature coverage for render, searchable institution lookup on both selectors, daily-only / Friday-only / same / different / clear flows, invalid selections, inactive or pending institutions, and stale saved selections surviving unrelated profile updates.
- Verification:
  - `php artisan migrate --pretend --path=database/migrations/2026_03_24_000000_add_prayer_institution_preferences_to_users_table.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/AccountSettingsPageTest.php`
  - `vendor/bin/pest --parallel --compact`
  - `vendor/bin/phpstan analyse --ansi`
  - `vendor/bin/pint --test app/Livewire/Pages/Dashboard/AccountSettings.php app/Models/User.php tests/Feature/AccountSettingsPageTest.php database/migrations/2026_03_24_000000_add_prayer_institution_preferences_to_users_table.php resources/views/livewire/pages/dashboard/account-settings.blade.php`
  - `git diff --check`
- Result:
  - migration SQL is clean: nullable UUID columns plus indexes only, with no DB-level constraints
  - focused account-settings feature coverage passed
  - full Pest suite passed: `955 passed (4184 assertions)`
  - PHPStan passed with no errors
  - Pint passed
  - `git diff --check` passed

# Verification Sweep

- [x] Inspect the failing Blaze login assertion against the current conditional social-login view behavior
- [x] Apply the minimal fixes for the failing Blaze test and Rector dry-run suggestion
- [x] Re-run the targeted and full verification commands to confirm the suite state

## Review
- `BlazeIntegrationTest` was asserting the Google OAuth link unconditionally even though the login view only renders that CTA when `services.google` is fully configured. The test now seeds the Google config inline before asserting the link so it verifies the real runtime contract under Blaze.
- Applied Rector's only reported dry-run change in `GeneratedFileFinalFixedPoskodSeeder` by replacing the small manual `foreach`/`return` helper with `array_any(...)`.
- Verification:
  - `vendor/bin/rector process --dry-run --no-progress-bar --debug --memory-limit=2G`
  - `vendor/bin/pest --parallel --compact`
  - `vendor/bin/phpstan analyse --ansi`
  - `vendor/bin/pint --test`
- Result:
  - full Pest suite passed: `951 passed`
  - PHPStan passed with no errors
  - Pint passed
  - Rector dry-run passed with no remaining changes

# Postcode Institution Slugs

- [x] Replace `generated-poskod-{row}` institution slugs with readable canonical slugs derived from the imported names
- [x] Remove the legacy/backfill compatibility paths for the old postcode slugs
- [x] Verify the importer and current database rewrite on the canonical slug path only

## Review
- Extracted the postcode import naming/slug rules into `App\Support\Institutions\GeneratedPoskodInstitutionData` so the dedicated seeder can normalize names, normalize address text, and generate deterministic canonical slugs like `abdul-rahman-putra-kariah-keladi-6809` from the CSV row data.
- Simplified `GeneratedFileFinalFixedPoskodSeeder` back to a pure canonical import path: it now persists only the readable slug and no longer contains the temporary dual-lookup/backfill behavior for old `generated-poskod-{row}` records.
- Removed the legacy runtime compatibility work for the old postcode slugs, including the public redirect routes and the extra institution subject-resolution/sharing fallback logic, so the codebase only resolves canonical institution slugs now.
- Kept the focused regression coverage on the canonical importer path in `GeneratedFileFinalFixedPoskodSeederTest`; the temporary legacy-routing spec was removed along with the compatibility behavior it existed to prove.
- Verification:
  - `vendor/bin/pint --format=agent app/Support/Institutions/GeneratedPoskodInstitutionData.php app/Actions/Contributions/ResolveContributionSubjectAction.php app/Actions/Membership/ResolveMembershipClaimSubjectAction.php app/Services/ShareTracking/ShareTrackingUrlService.php database/seeders/GeneratedFileFinalFixedPoskodSeeder.php routes/web.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Support/Institutions/GeneratedPoskodInstitutionData.php app/Actions/Contributions/ResolveContributionSubjectAction.php app/Actions/Membership/ResolveMembershipClaimSubjectAction.php app/Services/ShareTracking/ShareTrackingUrlService.php database/seeders/GeneratedFileFinalFixedPoskodSeeder.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `vendor/bin/pest --compact tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `php artisan db:seed --class=Database\\Seeders\\GeneratedFileFinalFixedPoskodSeeder --ansi`
  - Post-seed verification:
    - DB row `Abdul Rahman Putra Kariah Keladi` now stores slug `abdul-rahman-putra-kariah-keladi-6809`
    - canonical route `https://majlisilmu.test/institusi/abdul-rahman-putra-kariah-keladi-6809`

# Postcode Sentence Case

- [x] Normalize imported postcode institution names and address lines to sentence case through the seeder
- [x] Rerun the dedicated postcode seeder so the imported postcode institutions are updated in the database
- [x] Verify the rewritten rows and the seeder regression coverage

## Review
- Updated `GeneratedFileFinalFixedPoskodSeeder` to normalize imported postcode `Nama` and `Alamat` values to sentence case at import time instead of storing the source CSV's all-uppercase strings. The existing bracket-prefix stripping and `(ESTATE)` suffix move still run first, then the final text is title-cased with a small exception map for known acronyms like `UiTM`, `FELDA`, `PDRM`, and `ESTATE`.
- Reran the dedicated postcode seeder against the current database, which rewrote the imported postcode institutions in place through `updateOrCreate` without touching unrelated data.
- Extended the focused seeder test to assert sentence-cased storage on representative rows, including row `28 => Masjid Ajil / Ajil, Hulu Terengganu`, row `106 => Masjid Abu Bakar Temerloh / Bandar Temerloh`, row `5667 => Masjid Kampung Bukit Lada`, and row `6412 => Masjid Al-Muhajirin (ESTATE)`.
- Verification:
  - `vendor/bin/pint --format=agent database/seeders/GeneratedFileFinalFixedPoskodSeeder.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `vendor/bin/phpstan analyse --ansi database/seeders/GeneratedFileFinalFixedPoskodSeeder.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `vendor/bin/pest --compact tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `php artisan db:seed --class=Database\\Seeders\\GeneratedFileFinalFixedPoskodSeeder --ansi`
  - Post-seed data checks via local PHP/tinker:
    - row `28 => Masjid Ajil / Ajil, Hulu Terengganu / Hulu Terengganu / Ajil`
    - row `106 => Masjid Abu Bakar Temerloh / Bandar Temerloh / Pahang / Temerloh / Temerloh`
    - imported uppercase audit => `uppercase_name_rows=0`, `uppercase_line1_rows=1` where the lone remaining uppercase line is the acronym-only `P/S. 25`

# Location Hierarchy Dedupe

- [x] Dedupe identical subdistrict and district labels in public location displays
- [x] Make postcode subdistrict matching prefer specific localities over same-name district subdistricts
- [x] Verify the importer and institution pages against the duplicate-location regression

## Review
- Added `App\Support\Location\AddressHierarchyFormatter` as the shared public location formatter so institution, event, series, reference, and speaker pages all build address hierarchies with one rule set. It preserves the existing federal-territory state hiding and now removes identical adjacent hierarchy labels case-insensitively, so outputs like `Temerloh, Temerloh, Pahang` collapse to `Temerloh, Pahang`.
- Updated `GeneratedFileFinalFixedPoskodSeeder` to treat same-name district subdistricts as a fallback instead of a first-choice match. When address text matches both a specific subdistrict and the district-name alias, the importer now prefers the specific locality, so `AJIL, HULU TERENGGANU` resolves to district `Hulu Terengganu` and subdistrict `Ajil`.
- Extended regression coverage in `InstitutionShowPageTest`, `InstitutionIndexTest`, and `GeneratedFileFinalFixedPoskodSeederTest` to lock in both behaviors with real rendered pages and the production postcode import path.
- Verification:
  - `vendor/bin/pint --format=agent app/Support/Location/AddressHierarchyFormatter.php database/seeders/GeneratedFileFinalFixedPoskodSeeder.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php tests/Feature/InstitutionShowPageTest.php tests/Feature/InstitutionIndexTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Support/Location/AddressHierarchyFormatter.php database/seeders/GeneratedFileFinalFixedPoskodSeeder.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php tests/Feature/InstitutionShowPageTest.php tests/Feature/InstitutionIndexTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionShowPageTest.php`
  - `vendor/bin/pest --compact tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`

# Postcode Name Normalization

- [x] Normalize bracketed numeric prefixes out of postcode CSV mosque names
- [x] Move leading `(ESTATE)` markers to the end of postcode CSV mosque names
- [x] Cover the new name normalization in the dedicated postcode seeder test and verify the import path

## Review
- Rewrote `database/seeders/Generated_File_Final_Fixed_Poskod.csv` so all 31 leading bracketed numeric prefixes were removed from `Nama` values and all 27 leading `(ESTATE)` markers were moved to the end of the name. Post-cleanup scan results are `remaining_bracket_prefixes=0` and `remaining_estate_prefixes=0`.
- Updated `GeneratedFileFinalFixedPoskodSeeder` to normalize imported institution names with the same rules at seed time, so future CSV drift on those two patterns does not leak into stored institution names. The importer also now strips a UTF-8 BOM from CSV headers defensively.
- Extended `tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php` to assert both normalization rules through the real import path: row `5667 => Masjid Kampung Bukit Lada` and row `6412 => Masjid Al-Muhajirin (ESTATE)`.
- Verification:
  - `python3` CSV audit: first row header still reads correctly as `No.`, `remaining_bracket_prefixes=0`, `remaining_estate_prefixes=0`
  - `vendor/bin/pint --format=agent database/seeders/GeneratedFileFinalFixedPoskodSeeder.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `vendor/bin/phpstan analyse --ansi database/seeders/GeneratedFileFinalFixedPoskodSeeder.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `php artisan db:seed --class=Database\\Seeders\\GeneratedFileFinalFixedPoskodSeeder --ansi`
  - Post-seed `php -r` verification on the current database: `institutions=6935`, `name_5667=MASJID KAMPUNG BUKIT LADA`, `name_6412=MASJID AL-MUHAJIRIN (ESTATE)`

# Dedicated Postcode CSV Seeder

- [x] Add a dedicated seeder for `Generated_File_Final_Fixed_Poskod.csv`
- [x] Resolve imported rows onto integer-backed state/district/subdistrict geography IDs without row skips
- [x] Add focused coverage for the dedicated seeder
- [x] Verify with `migrate:fresh`, `ProductionSeeder`, and the dedicated seeder until the import completes cleanly

## Review
- Added `GeneratedFileFinalFixedPoskodSeeder` as a deterministic, idempotent importer for `database/seeders/Generated_File_Final_Fixed_Poskod.csv`. It creates or updates one verified `masjid` institution per CSV row using deterministic canonical slugs derived from the imported names plus the row number, then writes a single `main` address per institution.
- The importer resolves the CSV onto the app's actual geography schema, where `state_id`, `district_id`, and `subdistrict_id` are integer foreign IDs rather than UUIDs. It preloads the seeded Malaysia geography, normalizes state aliases, maps `PUSA` onto `Betong`, handles the ambiguous `JENGKA` cluster with deterministic district/subdistrict rules, and ensures the missing `Pusa` subdistrict exists under `Betong`.
- Added `tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php` to seed `ProductionSeeder`, run the dedicated importer, assert all 6,935 rows import with 6,935 addresses, check the one intentionally unresolved junk row (`6082`), and lock in the trickiest mappings (`Jerantut/Bandar Pusat Jengka`, `Maran/Bandar Tun Abdul Razak`, `Betong/Pusa`, `Kuala Kangsar/Padang Rengas`).
- Verification:
  - `vendor/bin/pint --format=agent database/seeders/GeneratedFileFinalFixedPoskodSeeder.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `vendor/bin/phpstan analyse --ansi database/seeders/GeneratedFileFinalFixedPoskodSeeder.php tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/GeneratedFileFinalFixedPoskodSeederTest.php`
  - `php artisan migrate:fresh --ansi`
  - `php artisan db:seed --class=Database\\Seeders\\ProductionSeeder --ansi`
  - `php artisan db:seed --class=Database\\Seeders\\GeneratedFileFinalFixedPoskodSeeder --ansi`
  - Post-seed Eloquent verification via `php -r`: `institutions=6935`, `addresses=6935`, `null_district_rows=1`, `subdistrict_rows=3537`; sampled rows confirmed `1880 => Jerantut / Bandar Pusat Jengka`, `1882 => Maran / Bandar Tun Abdul Razak`, `4437 => Betong / Pusa`, `6082 => Sarawak / null / null`

# Production Seeder And Postcode CSV Cleanup

- [x] Add `UserSeeder` to the production seeding pipeline
- [x] Normalize high-confidence `Generated_File_Final_Fixed_Poskod.csv` issues using the app's canonical geography data
- [x] Run focused verification and record the outcome

## Review
- Added `UserSeeder` to `ProductionSeeder` so production bootstrap seeding now creates the baseline user accounts after permissions and roles are in place, and updated the focused production seeder expectation to match the actual production seed list plus that new user batch.
- Rewrote `database/seeders/Generated_File_Final_Fixed_Poskod.csv` with deterministic corrections only: fixed the single malformed 8-column row, filled the one blank state row that was recoverable from `BANDARAYA KUANTAN`, left-padded all 511 four-digit postcodes to five digits, normalized 2,141 district aliases to the app's canonical district names, and inferred 29 blank districts from unambiguous address tokens backed by the existing district/subdistrict data.
- Post-cleanup CSV audit results: no malformed rows remain, no blank states remain, no four-digit or non-numeric postcodes remain, and the only unresolved non-canonical district labels left are `JENGKA` (30 rows) and `PUSA` (10 rows); 15 rows still have blank districts because the dataset did not provide enough unambiguous location detail to map them safely.
- Verification:
  - `vendor/bin/pint --format=agent database/seeders/ProductionSeeder.php tests/Feature/ProductionSeederTest.php tasks/todo.md`
  - `vendor/bin/pest --parallel --compact tests/Feature/ProductionSeederTest.php`
  - `vendor/bin/phpstan analyse --ansi database/seeders/ProductionSeeder.php tests/Feature/ProductionSeederTest.php`
  - `git diff --check -- database/seeders/ProductionSeeder.php tests/Feature/ProductionSeederTest.php database/seeders/Generated_File_Final_Fixed_Poskod.csv tasks/todo.md`
  - CSV integrity audit via local PHP scripts: `bad_length=0`, `blank_state=0`, `blank_district=15`, `four_digit_postcode=0`, remaining invalid districts limited to `JENGKA=30` and `PUSA=10`

# Google OAuth Login Guard

- [x] Trace why Google login can still reach Google with an unusable OAuth configuration
- [x] Guard the Google OAuth entrypoint and auth UI so the button only appears when the provider is configured
- [x] Remove the hard-coded local Google callback URL and verify the guarded/configured flows

## Review
- The immediate failure mode was that Google OAuth was always exposed in the auth UI and the Socialite controller always attempted the redirect, even when a runtime environment could be missing or serving stale Google credentials.
- Added `App\Support\Auth\SocialiteProviderConfiguration` as the single capability check, then used it both in `SocialiteController` and in all login/register Blade views so Google sign-in is hidden unless `client_id`, `client_secret`, and `redirect` are all present.
- Changed `config/services.php` to derive the Google callback URL from `GOOGLE_REDIRECT_URI` or `APP_URL` instead of the old hard-coded `https://majlisilmu.test/...` value, and documented those env vars in `.env.example`.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/SocialiteAuthTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Http/Controllers/Auth/SocialiteController.php app/Support/Auth/SocialiteProviderConfiguration.php tests/Feature/SocialiteAuthTest.php`
  - `vendor/bin/pint --format=agent app/Http/Controllers/Auth/SocialiteController.php app/Support/Auth/SocialiteProviderConfiguration.php tests/Feature/SocialiteAuthTest.php resources/views/auth/login.blade.php resources/views/auth/register.blade.php resources/views/livewire/auth/login.blade.php resources/views/livewire/auth/register.blade.php config/services.php`
  - `curl -skI https://majlisilmu.test/oauth/google/redirect`
  - `php artisan tinker --execute="dump(config('services.google.redirect'));"`
  - `git diff --check`

# Review Findings Fixes

- [x] Preserve requested region timezones when rehydrating cached prayer times
- [x] Clear the actual verified submit-tag cache keys in the Pest bootstrap
- [x] Re-run focused cache/prayer regression coverage and static checks

## Review
- Kept the existing primitive-safe prayer-time cache payload, but changed `PrayerTimeService` to rehydrate cached ISO timestamps back into the requested region timezone. That keeps warm-cache behavior aligned with cold-cache behavior for timezone identity and DST-sensitive regions like `America/New_York`.
- Fixed the Pest bootstrap cache cleanup so it forgets the real verified submit-tag cache keys (`submit_tags_discipline_verified_*_safe_v1` and `submit_tags_issue_verified_*_safe_v1`) used by the submit-event page, removing the order-dependent stale-cache path from the test suite.
- Strengthened the prayer cache regression in `tests/Feature/Laravel13CacheSerializationTest.php` to assert the cached `Maghrib` result retains the `America/New_York` timezone name after the second, warm-cache read.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(rehydrates cached prayer times safely from the database cache store|fetches prayer times from Aladhan API|calculates start time with offset|handles Immediately offset correctly|returns null on API failure)'`
  - `vendor/bin/phpstan analyse --ansi`
  - `vendor/bin/pint --format=agent app/Providers/Filament/AdminPanelProvider.php app/Services/PrayerTimeService.php tests/Pest.php tests/Feature/Laravel13CacheSerializationTest.php`
  - `php artisan config:cache`
  - `php artisan config:clear`
  - `git diff --check`

# Filament Authz Config Cache Fix

- [x] Trace the `config:cache` failure caused by a non-serializable `filament-authz.role_resource.scope_options` value
- [x] Keep dynamic role scope options for normal admin runtime while skipping the callback during config-serialization commands
- [x] Verify `php artisan config:cache` succeeds and restore the local environment afterward

## Review
- The deployment failure came from `AdminPanelProvider` registering `roleScopeOptionsUsing()` with a closure. `aiarmada/filament-authz` stores that value straight into config during plugin registration, so `config:cache` tried to serialize a `Closure` at `filament-authz.role_resource.scope_options`.
- Reworked `app/Providers/Filament/AdminPanelProvider.php` to build the authz plugin first, then register the dynamic scope-options callback only outside the `config:cache` / `optimize` serialization commands. Normal web/admin runtime still gets the lazy DB-backed scope filtering, but deployment config caching no longer receives a closure in config.
- Verification:
  - `php artisan config:cache`
  - `php artisan config:clear`
  - `vendor/bin/pint --format=agent app/Providers/Filament/AdminPanelProvider.php`
  - `git diff --check`

# Speaker Follow Scroll Reveal Fix

- [x] Reproduce the speaker follow rerender bug and identify which sections still drop out after `toggleFollow`
- [x] Patch the shared scroll-reveal sidebar components to render in a revealed state across Livewire rerenders
- [x] Re-run focused follow regressions and browser verification on the affected speaker page

## Review
- The speaker and institution show pages already rendered their main content sections with `revealed`, but the shared sidebar inspiration card and guest CTA still depended on `x-intersect.once` to add that class client-side.
- Livewire follow toggles re-render those shared blocks from server HTML, which stripped the client-added class and left the sidebar content at `opacity: 0` under the hero on subsequent renders.
- Updated `resources/views/components/sidebar-inspiration.blade.php` and `resources/views/components/join-majlisilmu-cta.blade.php` to server-render `revealed`, matching the safer pattern already used on the event page and the patched page-specific sections.
- Tightened the speaker and institution follow regressions to seed an `Inspiration` record and assert the exact shared sidebar inspiration wrapper still renders with `scroll-reveal reveal-right revealed` before and after `toggleFollow`.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(allows an authenticated user to follow a speaker|keeps the speaker detail sections revealed after following|allows an authenticated user to follow and unfollow an institution|keeps institution detail sections revealed after following)'`
  - `vendor/bin/phpstan analyse --ansi`
  - `vendor/bin/pint --format=agent tests/Feature/SpeakerFollowTest.php tests/Feature/InstitutionShowPageTest.php`
  - `git diff --check`
  - Browser check on `https://majlisilmu.test/penceramah/amina-binti-rashid-bhiqccr`: after clicking `Ikuti`, `main .scroll-reveal` had no hidden elements remaining

# Cache Regression Fixes

- [x] Restore safe public-listing/home/search caches using primitive payloads instead of raw Eloquent objects
- [x] Expand cache busting to cover the restored v2 keys
- [x] Replace the weak GET-only regression check with Livewire/update-path coverage

## Review
- Restored the hot-path caches removed during the Laravel 13 cache-serialization fix, but now they store only primitive payloads: filter lookups cache raw model attributes and rehydrate with `Model::hydrate()`, while featured/home/default-search caches store ordered event IDs or paginator snapshots instead of full Eloquent objects.
- Added new `*_v2` cache keys for the restored caches so production can ignore any legacy object-serialized entries such as `states_my` or `default_events_search`, while `PublicListingsCache` now forgets both legacy and v2 keys.
- Replaced the old GET-only state-cache regression with a Livewire component update test, and added coverage for the default search cache path under the database cache store.
- Verification:
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php app/Livewire/Pages/Home/KimiHome.php app/Services/EventSearchService.php app/Support/Cache/SafeModelCache.php app/Support/Cache/PublicListingsCache.php tests/Feature/Laravel13CacheSerializationTest.php tests/Feature/PublicListingCacheInvalidationTest.php`
  - `vendor/bin/pest --parallel --compact tests --filter='(updates the events index livewire component even when legacy cached state collections exist|rehydrates the default events search cache safely from the database cache store|rehydrates cached prayer times safely from the database cache store|clears majlis listing cache)'`
  - `composer validate --no-check-publish`
  - `git diff --check`

# Filament Dashboard Route Fix

- [x] Trace the missing `filament.{panel}.pages.*-dashboard` route against Filament 5 panel routing
- [x] Give the custom panel dashboards a real page route so navigation no longer points at a missing named route
- [x] Add regression coverage for loading the admin event edit page with Filament navigation mounted

## Review
- Filament 5 keeps `/` as the panel home redirect (`filament.{panel}.home`), so the custom `AdminDashboard` and `AhliDashboard` pages no longer registered a concrete `pages.*-dashboard` route while navigation still tried to generate one.
- Introduced a shared `App\Filament\Pages\PanelDashboard` base class that assigns both custom dashboards to `/dashboard`, which restores stable `filament.admin.pages.admin-dashboard` and `filament.ahli.pages.ahli-dashboard` routes without creating a `/ -> /` redirect loop.
- Added a regression in `tests/Feature/AdminDashboardTest.php` that hits the actual admin event edit HTTP page to exercise Filament navigation rendering, and updated the existing dashboard test to assert the expected `/admin -> /dashboard` redirect before rendering the dashboard.
- Verification:
  - `php artisan route:list --name=filament.admin.pages.admin-dashboard --json`
  - `php artisan route:list --name=filament.ahli.pages.ahli-dashboard --json`
  - `vendor/bin/phpstan analyse --ansi app/Filament/Pages/PanelDashboard.php app/Filament/Pages/AdminDashboard.php app/Filament/Pages/AhliDashboard.php tests/Feature/AdminDashboardTest.php tests/Feature/AhliDashboardTest.php tests/Feature/AhliNavigationTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(admin dashboard|missing dashboard navigation routes|ahli dashboard|ahli workspace wrapper)'`
  - `vendor/bin/pint --format=agent app/Filament/Pages/PanelDashboard.php app/Filament/Pages/AdminDashboard.php app/Filament/Pages/AhliDashboard.php tests/Feature/AdminDashboardTest.php`
  - `git diff --check`

# Submit Event Cache Fixes

- [x] Audit the submit-event Filament option caches for remaining object-serialized payloads
- [x] Move submit-event option caches onto primitive-safe versioned keys
- [x] Add a Livewire regression test for submit-event updates with legacy cache entries present

## Review
- The submit-event page still had several Filament `Select::options()` closures caching `Collection` objects or reusing legacy cache keys, especially for languages, domain/discipline/source/issue tags, and venues.
- Converted those caches to array-only payloads and moved them onto `*_safe_v1` keys in `resources/views/components/pages/submit-event/create.blade.php`, so stale legacy cache entries like `submit_languages_v2` and `submit_tags_domain_ms` are ignored after deploy.
- Added submit-event regression coverage to `tests/Feature/Laravel13CacheSerializationTest.php` and expanded `tests/Pest.php` cache cleanup for the new versioned submit caches.
- Verification:
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php app/Livewire/Pages/Events/AdvancedFiltersPanel.php app/Livewire/Pages/Home/KimiHome.php app/Services/EventSearchService.php app/Support/Cache/SafeModelCache.php app/Support/Cache/PublicListingsCache.php tests/Feature/Laravel13CacheSerializationTest.php tests/Feature/PublicListingCacheInvalidationTest.php`
  - `vendor/bin/pest --parallel --compact tests --filter='(updates the events index livewire component even when legacy cached state collections exist|updates the submit event livewire component even when legacy select option caches exist|rehydrates the default events search cache safely from the database cache store|rehydrates cached prayer times safely from the database cache store|clears majlis listing cache)'`
  - `git diff --check`

# Dependency Refresh

- [x] Audit current Composer and npm dependencies, including direct major-version candidates
- [x] Upgrade all compatible dependencies to their latest available releases
- [x] Verify dependency resolution and run focused validation after the updates

## Review
- `npm outdated --json` is clean, so no frontend dependency changes were needed.
- Composer direct dependencies are now current on stable releases. The only meaningful stable upgrades available were `laravel/scout` `10.25.0 -> 11.1.0`, `spatie/laravel-query-builder` `6.4.4 -> 7.0.1`, and `typesense/typesense-php` `5.2.0 -> 6.0.0`; these were applied.
- The remaining entries shown by `composer outdated --direct --major-only` are branch-head/dev-channel suggestions such as `dev-master`, `0.x-dev`, or `5.x-dev`, not stable releases that should be taken automatically in this app.
- `spatie/laravel-query-builder` 7 tightened `allowedFilters()`, `allowedIncludes()`, and `allowedSorts()` to variadic signatures, so `app/Http/Controllers/Api/EventController.php` was updated to pass the same definitions via splats without changing runtime behavior.
- Verification:
  - `composer update --with-all-dependencies`
  - `vendor/bin/phpstan analyse --ansi app/Http/Controllers/Api/EventController.php app/Models/Event.php app/Services/EventSearchService.php app/Console/Commands/IndexEventsToTypesense.php tests/Unit/EventTest.php tests/Feature/EventSearchTypesenseFilterTest.php`
  - `vendor/bin/pest --parallel --compact tests --filter='(toSearchableArray|shouldBeSearchable|buildTypesenseFilterParts)'`
  - `composer validate --no-check-publish`
  - `composer audit`
  - `composer outdated --direct --format=json`
  - `npm outdated --json`
  - `git diff --check -- composer.json composer.lock app/Http/Controllers/Api/EventController.php tasks/todo.md tasks/lessons.md`

# AIArmada Packagist Cleanup

- [x] Verify Packagist metadata and identify which `aiarmada/*` packages can resolve without custom repository overrides
- [x] Replace the custom `aiarmada/*` repository overrides with the smallest production-safe Composer setup
- [x] Refresh lock data and verify Composer no longer depends on local `../commerce/packages` paths

## Review
- Verified that all five `aiarmada/*` package names are available on Packagist and that Composer can resolve the app’s `dev-main` requirements without any custom `aiarmada/*` repository entries once the existing `akaunting/laravel-money` compatibility override remains in place.
- Removed the custom inline package definitions for `aiarmada/affiliates`, `aiarmada/signals`, `aiarmada/filament-signals`, and `aiarmada/filament-authz`, and then removed the temporary `aiarmada/commerce-support` `vcs` fallback as well, so the app now resolves all `aiarmada/*` packages through normal Packagist/Composer discovery.
- Refreshed `composer.lock`; the app now locks `aiarmada/affiliates` to `f4278f1`, `aiarmada/commerce-support` to `ed64b11`, `aiarmada/filament-authz` to `626a6c8`, `aiarmada/filament-signals` to `aaae404`, and `aiarmada/signals` to `9040fae`.
- Verification:
  - `composer update aiarmada/affiliates aiarmada/commerce-support aiarmada/filament-authz aiarmada/filament-signals aiarmada/signals --with-all-dependencies`
  - `composer update aiarmada/affiliates aiarmada/commerce-support aiarmada/filament-authz aiarmada/filament-signals aiarmada/signals --with-all-dependencies --dry-run --no-scripts` against a copy of the app manifest with no `aiarmada/commerce-support` `vcs` repository
  - `composer validate --no-check-publish`
  - `rg -n '\.\./commerce/packages|api\.github\.com/repos/AIArmada/commerce/zipball|"type": "path"' composer.json composer.lock`
  - `git diff --check -- composer.json composer.lock tasks/todo.md tasks/lessons.md`

# Laravel 13 Upgrade

- [x] Audit and record the Laravel 13 framework, package, and skeleton deltas that apply to this app
- [x] Upgrade Composer constraints and lock data to Laravel 13-compatible releases
- [x] Apply the Laravel 13 config and structure deltas that matter here (`cache`, `session`, and related skeleton updates)
- [x] Replace direct deprecated CSRF middleware references and address any other app-level Laravel 13 compatibility issues
- [x] Verify Composer resolution, targeted runtime behavior, PHPStan, Pest, and diff hygiene

## Review
- Upgraded the app from Laravel 12 to Laravel 13.1.1 and refreshed the dependency graph to Laravel 13-compatible releases, including `laravel/ai`, `laravel/boost`, `laravel/tinker`, `spatie/eloquent-sortable`, and the Vite 8 / `laravel-vite-plugin` 3 frontend toolchain.
- Compared the app against the Laravel 13 upgrade guide and a fresh `laravel/laravel` 13.x skeleton, then applied the relevant skeleton deltas here: `config/cache.php` now sets `serializable_classes` to `false`, `config/session.php` uses JSON session serialization, and deprecated CSRF middleware references were replaced with `PreventRequestForgery`.
- Kept app-specific structure intact where it intentionally diverges from the stock skeleton, including the existing `bootstrap/app.php`, route setup, Filament providers, and custom frontend/build layout.
- Added Composer repository metadata overrides for `akaunting/laravel-money` and `bezhansalleh/filament-language-switch` so their current upstream code can resolve cleanly on Laravel 13 until those packages publish explicit support metadata, while preserving the local `aiarmada/*` path packages on `dev-main`.
- `phpseclib/phpseclib` was updated to 3.0.50 to clear the Composer security advisory chain pulled in through `laravel/socialite`, and `axios` was bumped to 1.13.6 to clear the frontend audit warning.
- Verification:
  - `composer update laravel/framework laravel/ai laravel/boost laravel/tinker spatie/eloquent-sortable akaunting/laravel-money bezhansalleh/filament-language-switch aiarmada/commerce-support aiarmada/filament-authz aiarmada/affiliates aiarmada/signals aiarmada/filament-signals --with-all-dependencies`
  - `composer update phpseclib/phpseclib`
  - `composer validate --no-check-publish`
  - `composer audit`
  - `npm install`
  - `npm audit --json`
  - `npm run build`
  - `vendor/bin/pint --dirty --format=agent`
  - `vendor/bin/phpstan analyse --ansi`
  - `vendor/bin/pest --parallel --compact --stop-on-failure`
  - `git diff --check -- . ':(exclude)AGENTS.md'`
- Notes:
  - `git diff --check` only reports pre-existing trailing whitespace in `AGENTS.md`; the upgrade changes themselves are clean.
  - Vite build still warns about `/images/about/islamic_geometry.png` and `/images/pattern-bg.png` being resolved at runtime, but the production build completes successfully.

# Full Suite Cleanup

- [x] Capture the current dirty worktree and run the full Rector, Pest, Pint, and PHPStan suite
- [x] Fix all issues reported by the repo-wide verification pass without reverting unrelated changes
- [x] Re-run the full suite until Rector, Pest, Pint, and PHPStan all pass cleanly
- [x] Document the final verification results and any notable residual notes here

## Review
- Fixed a late Ahli reference edit test mismatch by using the model route key instead of forcing the UUID into a slug-bound Filament page mount.
- Applied the 7 repo-wide Rector fixes that were actually pending, then normalized formatting with Pint.
- Fixed a PHPStan regression from the Rector cleanup by restoring the missing `@param list<string>` annotation on `parseInstagram()`.
- Updated institution and speaker public-index tests to match the current CTA copy and the dedicated authenticated contribution pages instead of removed inline modal actions.
- Fixed a real runtime bug in `ContributionEntityMutationService`: institution type and speaker gender submissions can now safely accept enum-backed form state, and enum arrays are normalized before persistence.
- Fixed a real speaker persistence bug in `Speaker::booted()` so explicit `post_nominal` values are preserved when there are no qualifications to derive from, while qualification-derived post-nominals still work.
- Updated the speaker create-option schema test to flatten nested Filament sections before asserting field presence, matching the current schema shape.
- Verification:
  - `XDEBUG_MODE=off vendor/bin/rector process app bootstrap config database routes tests --dry-run --debug --ansi --no-progress-bar`
  - `XDEBUG_MODE=off vendor/bin/rector process app/Filament/Resources/ContributionRequests/ContributionRequestResource.php app/Filament/Resources/ContributionRequests/Support/ContributionRequestPresenter.php app/Filament/Resources/MembershipClaims/MembershipClaimResource.php app/Forms/Components/Select.php app/Forms/InstitutionContributionFormSchema.php app/Models/Reference.php app/Models/Speaker.php app/Models/Venue.php app/Providers/AppServiceProvider.php app/Services/ContributionEntityMutationService.php app/Support/SocialMedia/SocialMediaLinkResolver.php tests/Feature/AhliPanelInstitutionEditingTest.php tests/Feature/InstitutionIndexTest.php tests/Feature/SharedFormSchemaTest.php tests/Feature/SpeakerCreateOptionSchemaTest.php tests/Feature/SpeakerIndexTest.php tests/Feature/QuickAddSelectTest.php --dry-run --debug --ansi --no-progress-bar`
  - `XDEBUG_MODE=off vendor/bin/pint`
  - `XDEBUG_MODE=off vendor/bin/phpstan analyse --ansi`
  - `XDEBUG_MODE=off vendor/bin/pest --parallel --compact --stop-on-failure`
  - `git diff --check`

# Internalize Filament Quick Add Select

- [x] Audit the current external quick-add dependency usage and preserve the required behavior in-app
- [x] Implement the quick-add and close-on-select behavior inside the application's internal Filament select component
- [x] Remove the external Composer package and delete dead bootstrapping/macro glue
- [x] Add focused regression coverage for the internal quick-add behavior
- [x] Re-run targeted verification, then document the outcome here

## Review
- Replaced the last remaining vendor `quickAdd()` dependency with an internal implementation inside `App\Forms\Components\Select`, while keeping `closeOnSelect()` as a concrete app-level method instead of a runtime macro.
- The internal quick-add behavior now wraps Filament's existing search-results and option-label callbacks instead of replacing them blindly, so relationship selects keep their native query behavior while gaining the inline create option.
- Removed the provider-level `Select` macro registration from `AppServiceProvider`, since the custom app select component now owns the custom behavior directly.
- Removed `cocosmos/filament-quick-add-select` from `composer.json` and `composer.lock`, then regenerated Composer autoloading without PSR-4 warnings.
- Added internal translation files for the quick-add label under `resources/lang/en/quick_add.php` and `resources/lang/ms/quick_add.php`, matching the application's actual `lang_path()`.
- Added focused regression coverage proving the internal select prepends a quick-add option when needed, suppresses it on exact matches, and creates/selects the related record when chosen.
- Verification:
  - `rg -n -F "cocosmos/filament-quick-add-select" composer.json composer.lock app resources tests`
  - `php -l app/Forms/Components/Select.php`
  - `php -l app/Providers/AppServiceProvider.php`
  - `php -l tests/Feature/QuickAddSelectTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/QuickAddSelectTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Forms/Components/Select.php app/Providers/AppServiceProvider.php tests/Feature/QuickAddSelectTest.php`
  - `vendor/bin/pint --format=agent app/Forms/Components/Select.php app/Providers/AppServiceProvider.php tests/Feature/QuickAddSelectTest.php`
  - `composer dump-autoload -o --no-scripts`
  - `git diff --check`

# Membership Claim Entry Point Rework

- [x] Remove membership-claim CTAs from public institution and speaker pages
- [x] Add a searchable membership-claim entry form on `/sumbangan`
- [x] Update coverage for the new entry flow and re-verify affected pages

## Review
- Removed `Tuntut Keahlian` and pending-claim badges from the public institution and speaker detail sidebars so those pages now stay focused on broad public feedback actions: `Cadang Kemaskini` and `Lapor`.
- Turned `/sumbangan` into the authenticated claim entry hub by adding a searchable Filament form on the contributions page. Users now pick `Institusi` or `Penceramah`, search by record name, and continue into the existing canonical claim form route.
- Kept the existing claim form and claim-history pages intact; the change is purely about moving the entry point to a more appropriate authenticated surface.
- Added regression coverage for starting a claim from `/sumbangan` and for ensuring the public institution/speaker pages no longer render claim CTAs.
- Verification:
  - `vendor/bin/pest --compact tests/Feature/MembershipClaimPagesTest.php tests/Feature/ContributionPagesTest.php tests/Feature/PublicPagesTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Contributions/Index.php app/Livewire/Pages/MembershipClaims/Create.php tests/Feature/MembershipClaimPagesTest.php tests/Feature/ContributionPagesTest.php tests/Feature/PublicPagesTest.php`
  - `vendor/bin/pint --format=agent app/Livewire/Pages/Contributions/Index.php tests/Feature/MembershipClaimPagesTest.php`
  - `git diff --check`
  - Browser snapshots:
    - `https://majlisilmu.test/penceramah/amina-binti-rashid-bhiqccr`
    - `https://majlisilmu.test/institusi/kompleks-islam-senawang-usx9tbl`

# Audit Fixes For Uncommitted Changes

- [x] Review the uncommitted membership-claim and public contribution/report changes against commit `d666ca998017848a17abb2f76531d9fb2ba6b106`
- [x] Fix the broken nested address handling on contribution update pages
- [x] Add regression coverage for direct maintainer address edits on the suggest-update flow
- [x] Re-run targeted feature coverage, PHPStan, and diff checks

## Review
- The main regression in the uncommitted batch was not inside the new membership-claim workflow itself; it was in the reused contribution update schemas for institutions and speakers.
- `SuggestUpdate` was filling and diffing nested `address` data from `ContributionEntityMutationService`, but the reused contribution form schemas still exposed flat address fields like `line1` and `state_id`. That mismatch meant update requests silently dropped address edits and owner direct-edit flows could not apply address changes either.
- Fixed this by introducing a nested-address schema mode in `SharedFormSchema`, then opting `SuggestUpdate` into `address`-scoped address fields for institution and speaker update pages while leaving create/quick-create flows unchanged.
- Added a regression test proving owner maintainers can now directly update institution addresses from the public suggest-update page again.
- Verification:
  - `vendor/bin/pest --compact tests/Feature/ContributionPagesTest.php tests/Feature/SharedFormSchemaTest.php tests/Feature/MembershipClaimActionsTest.php tests/Feature/MembershipClaimPagesTest.php tests/Feature/MembershipClaimAdminResourceTest.php tests/Feature/PublicPagesTest.php tests/Feature/ReportAdminResourceTest.php tests/Feature/AdminResourcesCoverageTest.php tests/Feature/RefactorTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Forms/SharedFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Forms/SpeakerContributionFormSchema.php app/Livewire/Pages/Contributions/SuggestUpdate.php app/Actions/Membership app/Livewire/Pages/MembershipClaims app/Filament/Resources/MembershipClaims app/Models/MembershipClaim.php app/Support/Membership`
  - `git diff --check`

# Membership Claims Migration Fix

- [x] Confirm whether the speaker page crash was caused by an unapplied `membership_claims` migration
- [x] Apply the pending `membership_claims` migration to the local development database
- [x] Remove the temporary membership-claims schema guards and return to the normal migrated-schema assumption
- [x] Re-verify the affected speaker page and targeted membership-claim coverage

## Review
- The speaker page crash came from authenticated CTA rendering querying `membership_claims` before the new migration had been applied locally.
- Applied `2026_03_19_000000_create_membership_claims_table` with `php artisan migrate --force`, which restored the missing table on the current database.
- Removed the temporary `tableExists()` guard from the model, membership claim pages, admin resource, and public speaker/institution CTA blocks, as requested. The code now assumes the schema exists once the migration has been run.
- Verification:
  - `php artisan migrate:status | rg "2026_03_19_000000_create_membership_claims_table"`
  - `php -l app/Models/MembershipClaim.php`
  - `php -l app/Livewire/Pages/MembershipClaims/Create.php`
  - `php -l app/Livewire/Pages/MembershipClaims/Index.php`
  - `php -l app/Filament/Resources/MembershipClaims/MembershipClaimResource.php`
  - `vendor/bin/phpstan analyse --ansi app/Models/MembershipClaim.php app/Livewire/Pages/MembershipClaims/Create.php app/Livewire/Pages/MembershipClaims/Index.php app/Filament/Resources/MembershipClaims/MembershipClaimResource.php`
  - `vendor/bin/pest --parallel --compact --tmp-dir=storage/framework/testing/paratest tests/Feature/MembershipClaimPagesTest.php`
  - Browser snapshot at `https://majlisilmu.test/penceramah/amina-binti-rashid-bhiqccr`
  - `git diff --check`

# Event Sidebar Review CTA Layout

- [x] Inspect the event detail desktop layout and compare it with the speaker detail sidebar pattern
- [x] Move the `Bantu Semak Majlis` CTA block into the desktop right-column sidebar stack
- [x] Verify the change with a local browser snapshot and diff checks

## Review
- The event detail page had the `Bantu Semak Majlis` block rendered after the main grid, which forced it below the content instead of inside the sidebar on desktop.
- Moved that CTA block into the existing sticky sidebar stack in `resources/views/livewire/pages/events/show.blade.php`, so it now sits in the right column beside the main content, matching the speaker page behavior more closely.
- Verification:
  - `vendor/bin/pint --dirty --format agent`
  - `git diff --check`
  - Browser snapshot at `https://majlisilmu.test/majlis/kelas-daurah-riyadus-salihin-poivejj` showing `BANTU SEMAK MAJLIS` inside the `complementary` sidebar region on desktop

# Membership Claim Moderation Flow

- [x] Add a persisted `membership_claims` workflow for speaker and institution self-claims
- [x] Add authenticated public claim submission and claim-history pages
- [x] Add a dedicated admin moderation resource with approve/reject actions and reviewer-selected roles
- [x] Surface claim CTAs on public speaker and institution pages
- [x] Add focused coverage and run targeted verification

## Review
- Added a new `MembershipClaim` model, migration, factory, status enum, and media support so speaker and institution claims can store reviewer state plus uploaded evidence in the `evidence` collection.
- Added shared membership-claim actions for resolving public subjects, submitting claims, approving with `editor`, `admin`, or `owner`, rejecting, and claimant-side cancellation. Approval reuses the existing membership attach/role flow and intentionally uses the central protected-role path so `owner` can be granted from the moderation surface.
- Added authenticated public routes and Livewire pages for `/tuntut-keahlian/{subjectType}/{subjectId}` and `/tuntutan-keahlian`, then linked the flow from the public speaker and institution pages with CTA hiding for existing members and a pending-state label for already-submitted claims.
- Added a new admin `MembershipClaimResource` under the `Moderation` group with a pending navigation badge, subject and evidence context, and approve/reject actions on both the index and record view pages.
- Added focused tests for claim actions, public claim submission/history, public CTA visibility, admin moderation, admin resource coverage, the new schema table, and the new claim-specific media conversion.
- Verification:
  - `php -l app/Enums/MemberSubjectType.php`
  - `php -l app/Enums/MembershipClaimStatus.php`
  - `php -l app/Models/MembershipClaim.php`
  - `php -l app/Support/Membership/MembershipClaimPresenter.php`
  - `php -l app/Actions/Membership/ResolveMembershipClaimSubjectAction.php`
  - `php -l app/Actions/Membership/ResolveMembershipClaimSubjectPresentationAction.php`
  - `php -l app/Actions/Membership/SubmitMembershipClaimAction.php`
  - `php -l app/Actions/Membership/ApproveMembershipClaimAction.php`
  - `php -l app/Actions/Membership/RejectMembershipClaimAction.php`
  - `php -l app/Actions/Membership/CancelMembershipClaimAction.php`
  - `php -l app/Livewire/Pages/MembershipClaims/Create.php`
  - `php -l app/Livewire/Pages/MembershipClaims/Index.php`
  - `php -l app/Filament/Resources/MembershipClaims/MembershipClaimResource.php`
  - `php -l app/Filament/Resources/MembershipClaims/Pages/ListMembershipClaims.php`
  - `php -l app/Filament/Resources/MembershipClaims/Pages/ViewMembershipClaim.php`
  - `php -l app/Filament/Resources/MembershipClaims/Tables/MembershipClaimsTable.php`
  - `php -l app/Filament/Resources/MembershipClaims/Schemas/MembershipClaimInfolist.php`
  - `php -l app/Providers/AppServiceProvider.php`
  - `php -l routes/web.php`
  - `php -l tests/Feature/MembershipClaimActionsTest.php`
  - `php -l tests/Feature/MembershipClaimPagesTest.php`
  - `php -l tests/Feature/MembershipClaimAdminResourceTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Enums/MemberSubjectType.php app/Enums/MembershipClaimStatus.php app/Models/MembershipClaim.php app/Support/Authz/MemberRoleCatalog.php app/Support/Membership/MembershipClaimPresenter.php app/Actions/Membership/ResolveMembershipClaimSubjectAction.php app/Actions/Membership/ResolveMembershipClaimSubjectPresentationAction.php app/Actions/Membership/SubmitMembershipClaimAction.php app/Actions/Membership/ApproveMembershipClaimAction.php app/Actions/Membership/RejectMembershipClaimAction.php app/Actions/Membership/CancelMembershipClaimAction.php app/Livewire/Pages/MembershipClaims/Create.php app/Livewire/Pages/MembershipClaims/Index.php app/Filament/Resources/MembershipClaims routes/web.php tests/Feature/MembershipClaimActionsTest.php tests/Feature/MembershipClaimPagesTest.php tests/Feature/MembershipClaimAdminResourceTest.php tests/Feature/AdminResourcesCoverageTest.php tests/Feature/MediaConversionsTest.php tests/Feature/RefactorTest.php`
  - `vendor/bin/pint --dirty --format agent`
  - `vendor/bin/pest --parallel --compact tests/Feature/MembershipClaimActionsTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/MembershipClaimPagesTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/MembershipClaimAdminResourceTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/AdminResourcesCoverageTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/MediaConversionsTest.php --filter='registers media conversions for MembershipClaim model'`
  - `vendor/bin/pest --parallel --compact tests/Feature/RefactorTest.php`
  - `git diff --check`
- Note:
  - Focused Pest runs were initially blocked by stale generated testing cache files in `bootstrap/cache/packages.testing.php` and `bootstrap/cache/services.testing.php` that referenced `RyanChandler\\BladeCaptureDirective\\BladeCaptureDirectiveServiceProvider`. Removing those generated caches restored normal test boot.

# Contribution URL Canonicalization

- [x] Change public event contribution/report URLs to use `majlis` instead of `event`
- [x] Add slug-based public reference URLs so `rujukan` pages stop using UUIDs
- [x] Keep old event/reference contribution URLs resolving while redirecting canonical public routes to the new segments and slugs
- [x] Run focused verification for canonical route generation, slug resolution, and public page links

## Review
- Updated `ContributionSubjectType` so event contribution/report URLs now generate `/sumbangan/majlis/{slug}/kemas-kini` and `/lapor/majlis/{slug}` instead of the English `event` segment.
- Added a real `slug` attribute for references directly in the base references migration and kept the runtime/model layer responsible for generating slugs on save for development data going forward.
- Updated the `Reference` model to generate slugs automatically, use slug-based route keys, and continue resolving old UUID-based reference URLs through explicit route binding and slug-or-UUID subject resolution.
- Canonicalized the public contribution/report routes for references so `/sumbangan/rujukan/{uuid}/kemas-kini` and `/lapor/rujukan/{uuid}` now redirect to the slug URL, while the public reference page, event page, and share-tracking reference targets all generate slug-based URLs going forward.
- Verification:
  - `php -l app/Enums/ContributionSubjectType.php`
  - `php -l routes/web.php`
  - `php -l database/migrations/2026_01_23_031834_create_references_table.php`
  - `php -l database/factories/ReferenceFactory.php`
  - `php -l app/Models/Reference.php`
  - `php -l app/Actions/Contributions/ResolveContributionSubjectAction.php`
  - `php -l app/Services/ShareTracking/ShareTrackingUrlService.php`
  - `php -l app/Livewire/Pages/Contributions/SuggestUpdate.php`
  - `php -l app/Livewire/Pages/Reports/Create.php`
  - `php -l tests/Feature/PublicPagesTest.php`
  - `php -l tests/Feature/ContributionPagesTest.php`
  - `php -l tests/Feature/ContributionWorkflowActionsTest.php`
  - `vendor/bin/pest --parallel --compact --filter='(renders reference contribution links with rujukan route segments|renders event contribution links with majlis route segments|stores reference reports from the public report page|redirects guests to login before opening report and suggest update pages|redirects uuid-based reference contribution and report pages to the canonical slug url|resolves contribution update context from slug and uuid subjects|resolves contribution subjects from slug and uuid identifiers through the action layer)'`
  - `vendor/bin/phpstan analyse --ansi app/Enums/ContributionSubjectType.php routes/web.php app/Models/Reference.php app/Actions/Contributions/ResolveContributionSubjectAction.php app/Services/ShareTracking/ShareTrackingUrlService.php app/Livewire/Pages/Contributions/SuggestUpdate.php app/Livewire/Pages/Reports/Create.php tests/Feature/PublicPagesTest.php tests/Feature/ContributionPagesTest.php tests/Feature/ContributionWorkflowActionsTest.php`
  - `php artisan tinker --execute='echo App\Enums\ContributionSubjectType::Event->publicRouteSegment(), PHP_EOL; echo App\Enums\ContributionSubjectType::Reference->publicRouteSegment(), PHP_EOL; $reference = App\Models\Reference::factory()->make(["slug" => "kitab-tafsir"]); echo route("references.show", $reference), PHP_EOL; echo route("contributions.suggest-update", ["subjectType" => App\Enums\ContributionSubjectType::Event->publicRouteSegment(), "subjectId" => "seminar-konvensyen-bersama-asatizah-uhblxgg"]), PHP_EOL; echo route("reports.create", ["subjectType" => App\Enums\ContributionSubjectType::Reference->publicRouteSegment(), "subjectId" => "kitab-tafsir"]), PHP_EOL;'`
  - `vendor/bin/pint --format agent app/Enums/ContributionSubjectType.php routes/web.php database/migrations/2026_01_23_031834_create_references_table.php database/factories/ReferenceFactory.php app/Models/Reference.php app/Actions/Contributions/ResolveContributionSubjectAction.php app/Services/ShareTracking/ShareTrackingUrlService.php app/Livewire/Pages/Contributions/SuggestUpdate.php app/Livewire/Pages/Reports/Create.php resources/views/livewire/pages/events/show.blade.php resources/views/components/pages/references/⚡show.blade.php tests/Feature/PublicPagesTest.php tests/Feature/ContributionPagesTest.php tests/Feature/ContributionWorkflowActionsTest.php`
  - `git diff --check`

# Reference Route And Report Queue Clarity

- [x] Localize public reference contribution/report URLs to use `rujukan`
- [x] Make the admin reports queue show the reported subject clearly with a direct admin link
- [x] Run focused verification for route generation, public pages, and admin report moderation surface

## Review
- Updated `ContributionSubjectType` so canonical public contribution/report URLs for references now use `/sumbangan/rujukan/{id}/kemas-kini` and `/lapor/rujukan/{id}`, while the old `/reference` variants redirect to the Malay path.
- Updated the public reference page CTA links to generate the localized `rujukan` routes instead of leaking the English `reference` segment.
- Kept the event moderation queue event-only, but completed the generic reports backend by adding a subject column and direct admin-record links in the reports resource so moderators can immediately open the reported reference, institution, speaker, event, or donation channel from `/admin/reports`.
- Added focused regression coverage for the public reference links, the legacy redirect behavior, and the admin reports subject link.
- Verification:
  - `php -l app/Enums/ContributionSubjectType.php`
  - `php -l routes/web.php`
  - `php -l resources/views/components/pages/references/⚡show.blade.php`
  - `php -l app/Filament/Resources/Reports/Support/ReportPresenter.php`
  - `php -l app/Filament/Resources/Reports/ReportResource.php`
  - `php -l app/Filament/Resources/Reports/Tables/ReportsTable.php`
  - `php -l tests/Feature/PublicPagesTest.php`
  - `php -l tests/Feature/ContributionPagesTest.php`
  - `php -l tests/Feature/ReportAdminResourceTest.php`
  - `vendor/bin/pest --parallel --compact --filter='(renders reference contribution links with rujukan route segments|stores reference reports from the public report page|redirects guests to login before opening report and suggest update pages|shows the reported subject title and admin link on the reports index)'`
  - `vendor/bin/phpstan analyse --ansi app/Enums/ContributionSubjectType.php app/Filament/Resources/Reports/Support/ReportPresenter.php app/Filament/Resources/Reports/ReportResource.php app/Filament/Resources/Reports/Tables/ReportsTable.php tests/Feature/PublicPagesTest.php tests/Feature/ContributionPagesTest.php tests/Feature/ReportAdminResourceTest.php`
  - `php artisan tinker --execute="echo route('contributions.suggest-update', ['subjectType' => App\\Enums\\ContributionSubjectType::Reference->publicRouteSegment(), 'subjectId' => '019cec11-e2d9-7356-b88a-98611b504687']); echo PHP_EOL; echo route('reports.create', ['subjectType' => App\\Enums\\ContributionSubjectType::Reference->publicRouteSegment(), 'subjectId' => '019cec11-e2d9-7356-b88a-98611b504687']); echo PHP_EOL;"`
  - `git diff --check`

# Admin Contribution Request Queue

- [x] Add a dedicated admin Filament resource for `ContributionRequest` records under the moderation group
- [x] Expose approve/reject review actions and readable request payload details for backend reviewers
- [x] Add focused admin access and moderation-action coverage, then run targeted verification

## Review
- Added a dedicated admin moderation resource for `ContributionRequest` records with list and view pages, pending-count navigation badge, readable entity labels/links, request notes, and pretty-printed original/proposed payload previews.
- Wired approve/reject moderation actions directly to the existing contribution workflow actions so admin moderation uses the same domain logic as the public contributions inbox instead of duplicating request-state transitions.
- Registered the resource in admin coverage and added focused tests for moderator approve/reject flows plus the resource view-link behavior.
- Verification:
  - `php -l app/Filament/Resources/ContributionRequests/Support/ContributionRequestPresenter.php`
  - `php -l app/Filament/Resources/ContributionRequests/Schemas/ContributionRequestInfolist.php`
  - `php -l app/Filament/Resources/ContributionRequests/Tables/ContributionRequestsTable.php`
  - `php -l app/Filament/Resources/ContributionRequests/Pages/ListContributionRequests.php`
  - `php -l app/Filament/Resources/ContributionRequests/Pages/ViewContributionRequest.php`
  - `php -l app/Filament/Resources/ContributionRequests/ContributionRequestResource.php`
  - `php -l tests/Feature/ContributionRequestAdminResourceTest.php`
  - `php -l tests/Feature/AdminResourcesCoverageTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/ContributionRequests app/Filament/Resources/ContributionRequests/ContributionRequestResource.php tests/Feature/ContributionRequestAdminResourceTest.php tests/Feature/AdminResourcesCoverageTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionRequestAdminResourceTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/AdminResourcesCoverageTest.php`
  - `vendor/bin/pint --format agent app/Filament/Resources/ContributionRequests tests/Feature/ContributionRequestAdminResourceTest.php tests/Feature/AdminResourcesCoverageTest.php`
  - `git diff --check`

# Report Page Subject Visibility

- [x] Extend the shared public report-page context with concrete subject display data
- [x] Render a visible subject summary on `/lapor/*` pages for speaker, institution, and event records
- [x] Add focused regression coverage and run targeted verification

## Review
- Extended the shared contribution/report presentation payload so report pages now receive both the generic subject label and the concrete subject title/name for the resolved record.
- Added a prominent summary panel near the top of the public report page that shows which record is being reported and links back to that record before submission.
- Added focused regression coverage for the report context action and the public institution, speaker, and event report pages so the highlighted subject stays visible.
- Verification:
  - `php -l app/Actions/Contributions/ResolveContributionSubjectPresentationAction.php`
  - `php -l app/Actions/Reports/ResolveReportFormContextAction.php`
  - `php -l app/Livewire/Pages/Reports/Create.php`
  - `php -l tests/Feature/ReportActionsTest.php`
  - `php -l tests/Feature/ContributionPagesTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/ResolveContributionSubjectPresentationAction.php app/Actions/Reports/ResolveReportFormContextAction.php app/Livewire/Pages/Reports/Create.php tests/Feature/ReportActionsTest.php tests/Feature/ContributionPagesTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/ReportActionsTest.php --filter='resolves report form context for public subjects through the action layer'`
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionPagesTest.php --filter='(shows the reported institution clearly on the public report page|shows the reported speaker clearly on the public report page|shows the reported event clearly on the public report page|resolves institution slugs on the report page without uuid casting errors|stores reference reports from the public report page)'`
  - `git diff --check`

# Speaker Create Parity

- [x] Compare the dedicated speaker contribution page against the submit-event speaker quick-create form
- [x] Align the shared speaker field set and required/optional behavior across both entry points
- [x] Add focused regression coverage and verify in the browser

## Review
- The speaker pair had broader drift than the institution pair: the event quick-create modal only exposed a partial speaker profile while `/sumbangan/penceramah/baru` already included biography, languages, full base/location details, qualifications, gallery, contacts, and social links.
- Updated `SpeakerFormSchema::createOptionForm()` to reuse the dedicated speaker contribution sections so both entry points now collect the same core speaker data and keep the same required/optional treatment for shared fields.
- Kept the event quick-create-only affiliation convenience by appending the existing `Affiliated Institution` fields on top of the shared speaker sections instead of removing event-context functionality.
- Extended `SpeakerFormSchema::createOptionUsing()` so the richer quick-create modal now persists qualifications, languages, address details, contacts, and social links in addition to the existing media and institution affiliation handling.
- Verification:
  - `php -l app/Forms/SpeakerContributionFormSchema.php`
  - `php -l app/Forms/SpeakerFormSchema.php`
  - `php -l tests/Feature/SharedFormSchemaTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Forms/SpeakerContributionFormSchema.php app/Forms/SpeakerFormSchema.php tests/Feature/SharedFormSchemaTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php`
  - Browser check at `https://majlisilmu.test/hantar-majlis?step=form.penceramah-media%3A%3Adata%3A%3Awizard-step`: the `Cipta` speaker modal now includes `Biografi`, `Bahasa`, `Alamat Baris 1`, `URL Google Maps`, `Qualifications`, `Galeri`, `Contact Details`, and `Media Sosial`, matching the dedicated speaker contribution page’s core sections.

# Institution Create Requiredness Alignment

- [x] Compare the dedicated institution contribution page against the submit-event institution quick-create form
- [x] Align the dedicated create page so `Google Maps URL` is required there as well
- [x] Run focused verification and capture the result

## Review
- The actual mismatch was not the asterisk styling itself. Both surfaces already use Filament's default required-marker UI.
- The mismatch was field requiredness: the submit-event institution quick-create modal required `Google Maps URL`, while `/sumbangan/institusi/baru` left it optional.
- Kept the institution update/suggestion flow unchanged by making the dedicated create page opt into the stricter address schema instead of changing every caller of `InstitutionContributionFormSchema`.
- Verification:
  - `php -l app/Forms/InstitutionContributionFormSchema.php`
  - `php -l app/Livewire/Pages/Contributions/SubmitInstitution.php`
  - `php -l tests/Feature/SharedFormSchemaTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Forms/InstitutionContributionFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php tests/Feature/SharedFormSchemaTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php`
  - Browser check at `https://majlisilmu.test/sumbangan/institusi/baru`: `URL Google Maps*` now renders as required, matching the submit-event institution quick-create modal.

# Create Submission Note Cleanup And Approval Audit

- [x] Remove create-time submission notes from dedicated institution and speaker submission forms
- [x] Revert the event-side institution quick-create submission note wiring
- [x] Add focused regression coverage that create forms omit reviewer-context fields while update forms keep them
- [x] Audit the full event-approval side effects through the moderation service, state transition, observers, and notification pipeline

## Review
- Removed create-time submission note UI from the dedicated institution and speaker contribution forms, and removed the temporary event-side institution quick-create note/contribution-request wiring so new submissions rely on the submitted payload itself.
- Kept reviewer context on update flows only: suggestion/update pages still expose `proposer_note` because that field is useful for explaining deltas rather than brand-new records.
- Confirmed the dedicated institution contribution flow is not a draft-only staging model: it creates the real institution and related rows immediately with `status = pending`, then creates a `contribution_requests` row to moderate that live pending record.
- Confirmed event approval side effects live primarily in `ApproveEvent`: it creates a moderation review, marks the event approved, sets `published_at`, auto-verifies pending related speaker/institution/venue/tag/reference records, indexes the event for Scout, dispatches submission/publication notifications, and then `ModerationService` records moderation telemetry. The event save also triggers `EventObserver::updated()`, which busts public-listing cache and checks for material change notifications.
- Verification:
  - `php -l app/Actions/Contributions/SubmitContributionCreateRequestAction.php`
  - `php -l app/Forms/InstitutionFormSchema.php`
  - `php -l app/Livewire/Pages/Contributions/SubmitInstitution.php`
  - `php -l app/Livewire/Pages/Contributions/SubmitSpeaker.php`
  - `php -l resources/views/components/pages/submit-event/create.blade.php`
  - `php -l tests/Feature/SharedFormSchemaTest.php`
  - `php -l tests/Feature/ContributionPagesTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/SubmitContributionCreateRequestAction.php app/Forms/InstitutionFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php app/Livewire/Pages/Contributions/SubmitSpeaker.php tests/Feature/SharedFormSchemaTest.php tests/Feature/ContributionPagesTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/ModerationServiceTest.php --filter='Event Approval'`
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionPagesTest.php --filter='(renders the dedicated institution contribution page|renders the dedicated speaker contribution page|keeps reviewer context fields on update suggestion pages|exposes Filament action handlers required by public contribution media uploads)'`
  - `vendor/bin/pint --dirty --format agent`
  - `git diff --check`

# Institution Form Alignment

- [x] Align institution description fields in the public contribution form and event quick-create flow
- [x] Remove the institution logo upload from the public contribution form
- [x] Reuse the full institution contacts repeater and persistence in the event quick-create flow
- [x] Add focused regression coverage for the shared form expectations

## Review
- Switched the institution description field to `RichEditor` in both the public contribution schema and the event wizard institution quick-create schema so the two flows expose the same editing surface.
- Removed the public institution `logo` upload while keeping `cover` and `gallery` media support intact.
- Extracted the institution contact-details repeater into `SharedFormSchema` and reused it in both flows, then added contact persistence to institution quick-create creation so the event wizard now stores the same structured contact data as the contribution form.
- Verification:
  - `php -l app/Forms/SharedFormSchema.php`
  - `php -l app/Forms/InstitutionContributionFormSchema.php`
  - `php -l app/Forms/InstitutionFormSchema.php`
  - `php -l tests/Feature/SharedFormSchemaTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Forms/SharedFormSchema.php app/Forms/InstitutionContributionFormSchema.php app/Forms/InstitutionFormSchema.php tests/Feature/SharedFormSchemaTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionPagesTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/SubmitEventEntityAccessTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicPagesTest.php`
  - `vendor/bin/pint --dirty --format agent`
  - `git diff --check`

# Public Contribution Media Upload Action Wiring

- [x] Inspect the failing public institution contribution page against the working media-upload host pattern
- [x] Add Filament action and Livewire file-upload support to the public institution and speaker contribution components
- [x] Add focused regression coverage for the missing `mountAction` surface and run targeted verification

## Review
- Matched the public institution and speaker contribution pages to the existing submit-event host pattern by adding `HasActions`, `InteractsWithActions`, and `WithFileUploads` to both Livewire page classes.
- This restores the `mountAction` method expected by Filament media-upload/image-editor UI on `/sumbangan/institusi/baru` and prevents the internal Livewire `MethodNotFoundException`.
- Added a focused regression test that asserts the public contribution components expose the Filament action handler surface required by their media-upload fields.
- Verification:
  - `php -l app/Livewire/Pages/Contributions/SubmitInstitution.php`
  - `php -l app/Livewire/Pages/Contributions/SubmitSpeaker.php`
  - `php -l tests/Feature/ContributionPagesTest.php`
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Contributions/SubmitInstitution.php app/Livewire/Pages/Contributions/SubmitSpeaker.php tests/Feature/ContributionPagesTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionPagesTest.php`
  - `vendor/bin/pint --dirty --format agent`
  - `git diff --check`

# Institution Contribution Route Consistency

- [x] Map public institution contribution/report URLs to `institusi`
- [x] Update institution-facing links and keep canonical subject handling as `institution`
- [x] Add focused regression coverage for canonical and legacy institution contribution URLs

## Review
- Extended `ContributionSubjectType` public route-segment mapping so institution contribution and report URLs now generate `institusi` while request handling still normalizes back to canonical `institution`.
- Updated the public institution profile page CTA links to use `/sumbangan/institusi/{slug}/kemas-kini` and `/lapor/institusi/{slug}`.
- Added legacy redirects for `/sumbangan/institution/{slug}/kemas-kini` and `/lapor/institution/{slug}` so old English URLs land on the canonical Malay path.
- Verification:
  - `vendor/bin/phpstan analyse --ansi app/Enums/ContributionSubjectType.php tests/Feature/ContributionPagesTest.php tests/Feature/PublicPagesTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionPagesTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicPagesTest.php`
  - `php artisan route:list --path=sumbangan`
  - `php artisan route:list --path=lapor`
  - `curl -I -s https://majlisilmu.test/lapor/institution/kompleks-islam-senawang-usx9tbl`
  - `curl -I -s https://majlisilmu.test/sumbangan/institution/kompleks-islam-senawang-usx9tbl/kemas-kini`
  - `vendor/bin/pint --dirty --format agent`
  - `git diff --check`

# Speaker Contribution Route Consistency

- [x] Add a public route-segment alias so speaker contribution/report URLs use `penceramah`
- [x] Update speaker-facing links and route resolution to keep internal subject handling canonical
- [x] Add focused regression coverage for canonical and legacy speaker contribution URLs

## Review
- Added a small `ContributionSubjectType` route-segment mapping so speaker contribution/report URLs now generate `penceramah` while Livewire and subject resolution still normalize back to the canonical `speaker` type.
- Updated the public speaker profile page to generate `/sumbangan/penceramah/{slug}/kemas-kini` and `/lapor/penceramah/{slug}`.
- Added legacy redirects for `/sumbangan/speaker/{slug}/kemas-kini` and `/lapor/speaker/{slug}` so old links continue to work and land on the canonical Malay segment.
- Verification:
  - `vendor/bin/phpstan analyse --ansi app/Enums/ContributionSubjectType.php app/Actions/Contributions/ResolveContributionSubjectAction.php app/Livewire/Pages/Contributions/SuggestUpdate.php app/Livewire/Pages/Reports/Create.php tests/Feature/ContributionPagesTest.php tests/Feature/PublicPagesTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionPagesTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicPagesTest.php`
  - `php artisan route:list --path=sumbangan`
  - `php artisan route:list --path=lapor`
  - `vendor/bin/pint --dirty --format agent`
  - `git diff --check`

# Speakers CTA Copy Cleanup

- [x] Remove the extra helper sentence below the public speakers CTA button
- [x] Verify the public `/penceramah` page no longer renders the removed sentence

## Review
- Removed the helper sentence under the speaker contribution CTA while keeping the button label, link target, and CTA container unchanged.
- Verified the rendered public page at `/penceramah`; the CTA still shows `Tambah ke direktori` and `Cadangkan penceramah baharu`, and the removed helper sentence no longer appears.

# Institutions CTA Copy Cleanup

- [x] Remove the extra helper sentence below the public institutions CTA button
- [x] Verify the public `/institusi` page no longer renders the removed sentence

## Review
- Removed the helper sentence under the institution contribution CTA while keeping the button label, link target, and CTA container unchanged.
- Verified the rendered public page at `/institusi`; the CTA still shows `Tambah ke direktori` and `Cadangkan institusi baharu`, and the removed helper sentence no longer appears.

# Institutions CTA Refresh

- [x] Move the add-institution CTA to the bottom of the public institutions page
- [x] Point the CTA to the dedicated institution contribution flow
- [x] Refresh the CTA layout so it matches the stronger bottom-of-page contribution pattern
- [x] Verify the rendered page locally

## Review
- Removed the old inline institution-submission form path from the public institutions index so the page stays focused on discovery and filtering.
- Added a bottom contribution panel that links to `route('contributions.submit-institution')`, which resolves to `/sumbangan/institusi/baru`.
- Verified the rendered page locally at `/institusi`; the CTA appears near the footer and, for logged-out users, the contribution route redirects to the login gate as expected.

# Speakers CTA Refresh

- [x] Move the add-speaker CTA to the bottom of the public speakers page
- [x] Point the CTA to `/sumbangan/penceramah/baru`
- [x] Refresh the CTA copy and styling so it feels more inviting and compelling
- [x] Verify the rendered page locally

## Review
- Removed the old inline speaker-submission form path from the public speakers index and turned the page back into a focused directory/search experience.
- Added a new bottom-of-page contribution panel that links to `route('contributions.submit-speaker')`, which resolves to `/sumbangan/penceramah/baru`.
- Verified the rendered page locally and confirmed the CTA displays at the bottom of `/penceramah`; unauthenticated navigation lands on the login gate for the protected contribution flow.

# Interest Removal Review Fixes

- [x] Restore the historical event-interest migration file so migration history stays intact
- [x] Add the updated planner copy to shipped locale JSON files and remove stale interest-only planner strings
- [x] Clean stale event-interest references from review docs
- [x] Re-run verification for the review-fix batch

## Review
- Restored `database/migrations/2026_01_16_213657_create_event_interests_table.php` so existing migration history remains reproducible while the new forward cleanup migration continues to remove the feature on upgraded installs.
- Added the new planner strings to shipped JSON locales and removed stale interest-only planner copy that no longer matches the dashboard UI.
- Trimmed the stale deleted-controller reference from `docs/MAJLISILMU_REVIEW_AND_ENHANCEMENT_PLAN.md`.
- Verification: `vendor/bin/rector process`, `vendor/bin/phpstan analyse --ansi`, `vendor/bin/pest --parallel`, `vendor/bin/pint --dirty --format agent`, `git diff --check`.

# Remove Event Interest

- [x] Remove the stale pre-interest naming that still pointed at the old event-interest surface
- [x] Remove event-interest runtime paths from API, Livewire, models, notifications, dashboard, and analytics
- [x] Remove event-interest UI copy, docs, and admin references
- [x] Remove event-interest tests and replace them with updated save/going expectations where needed
- [x] Add schema cleanup for `event_interests` and `events.interests_count`
- [x] Run Rector, PHPStan, Pest, and Pint verification for the removal batch

## Review
- Removed the event-interest feature end to end: API routes/controllers/actions, Livewire state, model relations, analytics outcomes, notifications, dashboard/admin views, and supporting tests.
- Deleted the stale `EventPledgeTest` and cleaned the remaining pre-interest wording from repository docs and task notes.
- Added `database/migrations/2026_03_15_120000_remove_event_interest_feature.php` to drop the legacy table/count column and purge `event_interest` affiliate conversions on upgraded installs.
- Verification: `vendor/bin/rector process`, `vendor/bin/phpstan analyse --ansi`, `vendor/bin/pest --parallel`, `vendor/bin/pint --dirty --format agent`.

# Registration Email Audit Fix

- [x] Remove duplicate verification-email dispatch from the registered-user listener
- [x] Add regression assertions that registration sends exactly one verification email
- [x] Run focused verification for the auth email flow changes

## Registration Email Audit Fix Review

- Removed the explicit verification send from `SendRegisteredUserEmails` and left Laravel's built-in `Registered` listener as the single source of verification email dispatch.
- Removed the manual `Registered`, `Verified`, `NotificationSent`, and `NotificationFailed` listener bindings from `AppServiceProvider` because those listeners already live under `app/Listeners` and were being auto-discovered, which doubled the welcome mail send and risked duplicate notification delivery logging/fallback handling.
- Tightened the web and API registration lifecycle tests to assert welcome and verification notifications are each sent exactly once.
- Verification:
  - `vendor/bin/phpstan analyse --ansi app/Providers/AppServiceProvider.php app/Listeners/Auth/SendRegisteredUserEmails.php tests/Feature/Auth/EmailLifecycleTest.php tests/Feature/Api/AuthEmailApiTest.php` => no errors
  - `vendor/bin/pest --parallel --compact tests/Feature/Auth/EmailLifecycleTest.php` => 1 passed
  - `vendor/bin/pest --parallel --compact tests/Feature/Api/AuthEmailApiTest.php` => 5 passed
  - `vendor/bin/pest --parallel --compact tests/Feature/NotificationDeliveryFlowTest.php` => 13 passed
  - `vendor/bin/pint --dirty --format agent` => pass
  - `git diff --check` => pass

# Unified Email Surface Implementation

- [x] Audit the exact auth, invitation, and notification hooks that will own outbound email
- [x] Implement the shared account lifecycle email foundation across web and API registration
- [x] Add invitation email delivery from the membership action layer
- [x] Add API password recovery and verification resend endpoints
- [x] Audit and standardize existing mail-capable notifications
- [x] Document the email inventory and local `log` mailer workflow
- [x] Run Rector, PHPStan, Pest, and Pint and fix anything they surface

## Unified Email Surface Implementation Review

- Made `User` a real email-verification notifiable, replaced the default verification and password-reset mail with branded queued notifications, and added a registered-user listener so welcome plus verification emails now fire consistently for both web and API signup.
- API auth now includes forgot-password, reset-password, and resend-verification endpoints, all reusing the same broker and notification paths as the web lifecycle.
- Member invitations now send queued routed email directly from `InviteSubjectMember`, so every invitation creation path delivers the same subject/role/link/expiry email without depending on Filament UI code.
- Standardized the remaining mail-capable operational surface by bringing `ReportResolvedNotification` onto the same queued/localized/action-url shape as the existing moderation mail notifications, and documented the full email inventory plus the local `MAIL_MAILER=log` + queue-worker workflow in `docs/EMAIL_FEATURES.md`.
- Verification:
  - `vendor/bin/rector process` => pass
  - `vendor/bin/phpstan analyse --ansi` => no errors
  - `vendor/bin/pest --parallel` => 910 passed
  - `vendor/bin/pint --format agent` => pass

# Uncommitted Change Audit And Verification Sweep

- [x] Review the full uncommitted working tree for regressions and contract drift
- [x] Fix issues found in the new auth and mobile API changes
- [x] Run Rector on the full working tree
- [x] Run PHPStan on the full application
- [x] Run Pest in parallel on the full suite
- [x] Run Pint and final diff hygiene checks

## Uncommitted Change Audit And Verification Sweep Review

- Audited the current uncommitted work across the checklist/doc rewrites and the new mobile API surface instead of relying only on targeted checks.
- Hardened API logout so `auth:sanctum` session-authenticated requests no longer risk null-token crashes; logout now safely no-ops when no personal access token is attached to the request.
- Added regression coverage for the session-authenticated logout path so the Sanctum mixed-auth behavior stays protected.
- Final verification:
  - `vendor/bin/rector process` => pass
  - `vendor/bin/phpstan analyse --ansi` => no errors
  - `vendor/bin/pest --parallel` => 901 passed
  - `vendor/bin/pint --format agent` => pass
  - `git diff --check` => pass

# Mobile API Audit And Expansion

- [x] Audit every current API endpoint against the Action-class refactors and current routes/controllers
- [x] Add missing native-client API endpoints for parity with existing web flows
- [x] Add regression coverage for the new mobile-facing endpoints
- [x] Publish an up-to-date mobile API reference for Android and iOS consumers

## Mobile API Audit And Expansion Review

- Added mobile bearer-token auth endpoints and documented Sanctum token usage for Android and iOS clients.
- Filled the biggest native-client parity gaps by exposing action-backed APIs for event going, event registration, registration status, and check-in state/check-in creation.
- Extracted shared check-in eligibility into `ResolveEventCheckInStateAction` so the API and event page now evaluate the same rules.
- Published `docs/MAJLISILMU_MOBILE_API_REFERENCE.md` and added pointers from the older technical docs so mobile developers have a current contract source.
- Verification:
  - `php artisan route:list --path=api/v1 --except-vendor` => pass
  - `vendor/bin/phpstan analyse --ansi app/Actions/Auth app/Actions/Events/MarkEventGoingAction.php app/Actions/Events/RemoveEventGoingAction.php app/Actions/Events/ResolveEventCheckInStateAction.php app/Http/Controllers/Api/AuthController.php app/Http/Controllers/Api/EventGoingController.php app/Http/Controllers/Api/EventRegistrationController.php app/Http/Controllers/Api/EventCheckInController.php app/Livewire/Pages/Events/Show.php tests/Feature/Api/AuthApiTest.php tests/Feature/Api/EventGoingApiTest.php tests/Feature/Api/EventRegistrationApiTest.php tests/Feature/Api/EventCheckInApiTest.php` => no errors
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(AuthApiTest|EventGoingApiTest|EventRegistrationApiTest|EventCheckInApiTest|EventSaveTest|EventPledgeTest|UserRegistrationsTest|RegistrationExportTest|NotificationCenterApiTest)'` => 50 passed

# V2 Roadmap Draft

- [x] Review the current MVP/v1 positioning and existing planning docs
- [x] Define the v2 product thesis and priority tracks
- [x] Write `V2_ROADMAP.md` with concrete `Now / Next / Later` sequencing

## V2 Roadmap Draft Review

- Reframed the next phase around organizer leverage instead of broadening the public product surface.
- Prioritized recurring programs, institution operations, moderation productivity, and attendance workflows as the core of v2.
- Kept retention and trust automation in the roadmap, but moved them behind stronger operator and reviewer tooling.
- Verification:
  - Documentation-only change; no test or static-analysis run was necessary.

# MVP Checklist Cleanup

- [x] Audit `MVP_CHECKLIST.md` against the live codebase
- [x] Separate true MVP gaps from intentional policy decisions and superseded architecture
- [x] Rewrite `MVP_CHECKLIST.md` so the remaining MVP tail is explicit and current

## MVP Checklist Cleanup Review

- Reclassified stale unchecked items such as member invitations and institution-scoped event workflows based on the implemented Ahli/dashboard flows.
- Moved intentional route-policy and superseded-resource items out of the active MVP backlog so the checklist no longer treats them as missing features.
- Reduced the true remaining MVP work to the small set that is still genuinely incomplete: institution self-service attendee export UX and moderation reviewer ergonomics.
- Verification:
  - Documentation-only change; no test or static-analysis run was necessary.

# Invitation Audit Follow-Up

- [x] Audit the full uncommitted invitation / Ahli panel batch end to end
- [x] Harden invitation acceptance against stale protected-role rows and deleted subjects
- [x] Re-run broad verification and fix any regressions before commit

## Invitation Audit Follow-Up Review

- Hardened invitation acceptance so stale or manually inserted protected-role invitations now fail with the same `This invitation is no longer valid.` validation path as the UI, instead of slipping through acceptance.
- Hardened the frontend invitation page and action layer for this app's no-FK setup so invitations tied to deleted institutions, speakers, events, or references now render a safe invalid state and reject acceptance cleanly instead of failing with a missing-model exception.
- Removed the unused `MemberRoleCatalog::roleSlugOptionsFor()` helper introduced during the invitation work to keep the catalog API lean.
- Broad verification:
  - `git diff --check` => pass
  - `vendor/bin/rector process ...` on the touched invitation / Ahli files => pass
  - `vendor/bin/phpstan analyse --ansi` => no errors
  - `vendor/bin/pest --parallel --compact` => 880 passed, 1 brittle UI assertion failed and was fixed
  - `vendor/bin/pest --parallel --compact --filter='(MemberInvitationUiTest|MemberInvitationActionsTest|AhliPanelInstitutionEditingTest|FilamentPanelAccessTest|AdminResourcesCoverageTest)'` => 43 passed
  - `vendor/bin/pint --dirty --format agent` => pass

# Scoped Invitation Permissions

- [x] Split member invitation authorization away from generic `manageMembers` so only scoped subject managers can invite
- [x] Add or expose Ahli subject surfaces for institution, speaker, event, and reference invitation workflows
- [x] Prevent local invitation flows from assigning protected owner / organizer roles
- [x] Add focused regression coverage for scoped-only invitation access and Ahli panel visibility
- [x] Run focused Rector, PHPStan, Pest, and Pint verification for the invitation permission batch

## Scoped Invitation Permissions Review

- Replaced the invitation relation manager's `manageMembers` gate with a dedicated `MemberInvitationGate`, so global roles no longer inherit invitation access through Spatie's gate hook while scoped institution/speaker/event/reference managers still can.
- Filtered local invitation role choices to non-protected roles only and hardened `InviteSubjectMember` so invite creation rejects protected `owner` / `organizer` targets even if called outside the UI.
- Exposed invitation management on Ahli institution editing and added Ahli speaker/reference resources so scoped members now have real non-admin invitation surfaces across all four subject types.
- Extended Ahli panel access to reference members and added focused regression coverage for scoped invitation creation, global-role exclusion, Ahli speaker/reference access, and reference Ahli visibility.
- Verification:
  - `vendor/bin/rector process app/Support/Authz/MemberRoleCatalog.php app/Support/Authz/MemberInvitationGate.php app/Actions/Membership/InviteSubjectMember.php app/Filament/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Ahli/Resources/Institutions/InstitutionResource.php app/Filament/Ahli/Resources/Speakers/SpeakerResource.php app/Filament/Ahli/Resources/Speakers/Pages/ListSpeakers.php app/Filament/Ahli/Resources/Speakers/Pages/ViewSpeaker.php app/Filament/Ahli/Resources/Speakers/Pages/EditSpeaker.php app/Filament/Ahli/Resources/References/ReferenceResource.php app/Filament/Ahli/Resources/References/Pages/ListReferences.php app/Filament/Ahli/Resources/References/Pages/EditReference.php app/Models/User.php tests/Feature/MemberInvitationActionsTest.php tests/Feature/MemberInvitationUiTest.php tests/Feature/AhliPanelInstitutionEditingTest.php tests/Feature/FilamentPanelAccessTest.php` => pass
  - `vendor/bin/phpstan analyse --ansi app/Support/Authz/MemberRoleCatalog.php app/Support/Authz/MemberInvitationGate.php app/Actions/Membership/InviteSubjectMember.php app/Filament/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Ahli/Resources/Institutions/InstitutionResource.php app/Filament/Ahli/Resources/Speakers/SpeakerResource.php app/Filament/Ahli/Resources/Speakers/Pages/ListSpeakers.php app/Filament/Ahli/Resources/Speakers/Pages/ViewSpeaker.php app/Filament/Ahli/Resources/Speakers/Pages/EditSpeaker.php app/Filament/Ahli/Resources/References/ReferenceResource.php app/Filament/Ahli/Resources/References/Pages/ListReferences.php app/Filament/Ahli/Resources/References/Pages/EditReference.php app/Models/User.php tests/Feature/MemberInvitationActionsTest.php tests/Feature/MemberInvitationUiTest.php tests/Feature/AhliPanelInstitutionEditingTest.php tests/Feature/FilamentPanelAccessTest.php` => no errors
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(MemberInvitationActionsTest|MemberInvitationUiTest|AhliPanelInstitutionEditingTest|FilamentPanelAccessTest|AdminResourcesCoverageTest)'` => 39 passed
  - `vendor/bin/pint --dirty --format agent` => pass

# Member Invitation UI

- [x] Add reusable admin invitation management UI on the existing subject member surfaces
- [x] Add an authenticated invitation acceptance page backed by the existing invitation actions
- [x] Add focused tests for admin invite/revoke flows and invitee acceptance flows
- [x] Run focused Rector, PHPStan, Pest, and Pint verification for the invitation UI batch

## Member Invitation UI Review

- Added reusable `MemberInvitationsRelationManager` support and registered invitation management on institution, speaker, event, and reference resources so admins can create, inspect, copy, and revoke member invitations from the same subject-management surfaces as memberships.
- Added subject-scoped `memberInvitations()` relations on institution, speaker, event, and reference models so invitation management now rides real owner-model relations instead of ad-hoc queries.
- Added `ResolveMemberInvitationByTokenAction` plus a new authenticated `ShowInvitation` Livewire page at `member-invitations.show`, which makes invitation tokens usable from the frontend and routes acceptance back through the existing shared invitation and membership actions.
- Hardened invitation acceptance for users without an email address so the action now returns a validation error instead of failing on a null email comparison.
- Scope note: this batch ships end-to-end invitation management and acceptance UI, but delivery is still manual. Admins currently copy/share the invitation link from the relation manager; no outbound email notification has been added yet.
- Verification:
  - `vendor/bin/rector process app/Actions/Membership/AcceptSubjectMemberInvitation.php app/Actions/Membership/ResolveMemberInvitationByTokenAction.php app/Filament/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Resources/Institutions/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Resources/Speakers/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Resources/Events/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Resources/References/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Resources/Institutions/InstitutionResource.php app/Filament/Resources/Speakers/SpeakerResource.php app/Filament/Resources/Events/EventResource.php app/Filament/Resources/References/ReferenceResource.php app/Filament/Ahli/Resources/Events/EventResource.php app/Models/Institution.php app/Models/Speaker.php app/Models/Event.php app/Models/Reference.php app/Livewire/Pages/Membership/ShowInvitation.php tests/Feature/MemberInvitationActionsTest.php tests/Feature/MemberInvitationUiTest.php tests/Feature/AdminResourcesCoverageTest.php` => pass
  - `vendor/bin/phpstan analyse --ansi app/Actions/Membership/AcceptSubjectMemberInvitation.php app/Actions/Membership/ResolveMemberInvitationByTokenAction.php app/Filament/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Resources/Institutions/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Resources/Speakers/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Resources/Events/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Resources/References/RelationManagers/MemberInvitationsRelationManager.php app/Filament/Resources/Institutions/InstitutionResource.php app/Filament/Resources/Speakers/SpeakerResource.php app/Filament/Resources/Events/EventResource.php app/Filament/Resources/References/ReferenceResource.php app/Filament/Ahli/Resources/Events/EventResource.php app/Models/Institution.php app/Models/Speaker.php app/Models/Event.php app/Models/Reference.php app/Livewire/Pages/Membership/ShowInvitation.php tests/Feature/MemberInvitationActionsTest.php tests/Feature/MemberInvitationUiTest.php tests/Feature/AdminResourcesCoverageTest.php` => no errors
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(MemberInvitationActionsTest|MemberInvitationUiTest|AdminResourcesCoverageTest|MemberRoleModalHydrationTest)'` => 16 passed
  - `vendor/bin/pint --dirty --format agent` => fixed imports/formatting

# Contribution Pending Lookup Extraction

- [x] Reuse the shared contribution entity metadata action in create-request creation
- [x] Extract latest pending contribution request lookup into a reusable action
- [x] Rewire the suggest-update page around the shared pending request resolver
- [x] Add focused regression coverage for staged create metadata and pending-request lookup
- [x] Run focused PHPStan, Pest, and Pint verification for the touched paths

## Contribution Pending Lookup Extraction Review

- Reused `app/Actions/Contributions/ResolveContributionEntityMetadataAction.php` inside `app/Actions/Contributions/SubmitContributionCreateRequestAction.php`, so staged create requests now pull `subject_type`, `entity_type`, and `entity_id` through the same metadata resolver already used by update-request creation.
- Added `app/Actions/Contributions/ResolveLatestPendingContributionRequestAction.php` so the “latest pending request for this proposer and entity” lookup now lives in the contribution action layer instead of inline inside the suggest-update Livewire page.
- Rewired `app/Livewire/Pages/Contributions/SuggestUpdate.php` to use the shared pending-request resolver while leaving the page responsible only for presentation and submission flow.
- Added focused regressions proving staged create requests still persist shared entity metadata, the latest pending request resolver returns the newest matching pending request, and the suggest-update page still shows the pending-request notice for contributors with an open request.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/ResolveLatestPendingContributionRequestAction.php app/Actions/Contributions/SubmitContributionCreateRequestAction.php app/Livewire/Pages/Contributions/SuggestUpdate.php tests/Feature/ContributionWorkflowActionsTest.php tests/Feature/ContributionPagesTest.php` => no errors
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(ContributionWorkflowActionsTest|ContributionPagesTest)'` => 33 passed

- Added `app/Actions/Contributions/ResolveContributionEntityMetadataAction.php` so contribution update-request creation no longer owns its own model-to-subject match; the shared action now resolves `subject_type`, `entity_type`, and `entity_id` in one place.
- Rewired `app/Actions/Contributions/SubmitContributionUpdateRequestAction.php` around that shared metadata action, leaving the update-request writer focused on state diff capture and persistence.
- Added `app/Actions/Contributions/ResolveOwnContributionRequestAction.php` and rewired `app/Livewire/Pages/Contributions/Index.php` so inbox cancel now follows the same resolve-first, act-second shape already used by approve and reject.
- Added focused regressions proving the shared contribution metadata action resolves event metadata correctly, update-request creation still records the expected reference subject type, the own-request resolver rejects non-proposers, and the inbox page still lets proposers cancel pending requests.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/ResolveContributionEntityMetadataAction.php app/Actions/Contributions/ResolveOwnContributionRequestAction.php app/Actions/Contributions/SubmitContributionUpdateRequestAction.php app/Livewire/Pages/Contributions/Index.php tests/Feature/ContributionWorkflowActionsTest.php tests/Feature/ContributionPagesTest.php` => no errors
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(ContributionWorkflowActionsTest|ContributionPagesTest)'` => 30 passed

# Workflow Action Extraction Batch

- [x] Extract shared notification read and read-all mutations into reusable Laravel Actions
- [x] Extract event self-check-in recording into a reusable Laravel Action
- [x] Extract public event registration orchestration into a reusable Laravel Action
- [x] Rewire the existing API controllers and Livewire pages around those actions
- [x] Add focused regression coverage for authenticated registration without contact details
- [x] Run focused Rector, PHPStan, Pest, and Pint verification for the workflow batch

## Workflow Action Extraction Batch Review

- Added `MarkNotificationMessageReadAction` and `MarkAllNotificationMessagesReadAction` so notification inbox mutations and product-signal side effects now live in shared actions used by both the API notification controller and the dashboard Livewire inbox page.
- Added `RecordEventCheckInAction` so the event page no longer creates check-ins, records share outcomes, and dispatches confirmation notifications inline; the page now keeps only check-in eligibility and UI messaging.
- Added `RegisterForEventAction` so public event registration now centralizes availability checks, guest contact requirements, capacity enforcement, duplicate detection, transactional registration creation, counter sync, share tracking, and confirmation notification dispatch.
- Rewired `app/Http/Controllers/Api/NotificationMessageController.php`, `app/Livewire/Pages/Dashboard/NotificationsIndex.php`, `app/Livewire/Pages/Events/Show.php`, and `app/Http/Controllers/Public/EventsController.php` around the new actions, leaving those entrypoints focused on validation, authorization, redirects, and UI responses.
- Added a regression in `tests/Feature/EventRegistrationSafetyTest.php` proving authenticated users can still register without email or phone after the registration workflow moved into an action.
- I deliberately stopped short of actionizing the event page save/interest/going toggles in this batch. Save and interest already have API-side actions, but the page still carries slightly different product rules and local state updates, so forcing that unification now would be more ceremony than gain.
- Verification:
  - `vendor/bin/rector process app/Actions/Notifications app/Actions/Events/RecordEventCheckInAction.php app/Actions/Events/RegisterForEventAction.php app/Http/Controllers/Api/NotificationMessageController.php app/Livewire/Pages/Dashboard/NotificationsIndex.php app/Livewire/Pages/Events/Show.php app/Http/Controllers/Public/EventsController.php tests/Feature/EventRegistrationSafetyTest.php` => pass, 1 file changed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Notifications app/Actions/Events/RecordEventCheckInAction.php app/Actions/Events/RegisterForEventAction.php app/Http/Controllers/Api/NotificationMessageController.php app/Livewire/Pages/Dashboard/NotificationsIndex.php app/Livewire/Pages/Events/Show.php app/Http/Controllers/Public/EventsController.php tests/Feature/NotificationPreferencesTest.php tests/Feature/ProductSignalsTelemetryTest.php tests/Feature/EventCheckInTest.php tests/Feature/EventRegistrationSafetyTest.php tests/Feature/DawahShareImpactTest.php` => no errors
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(NotificationPreferencesTest|ProductSignalsTelemetryTest|EventCheckInTest|EventRegistrationSafetyTest|DawahShareImpactTest)'` => 51 passed
  - `vendor/bin/pint --dirty --format agent` => pass

# Saved Search Action Extraction

- [x] Audit the saved-search page and API for duplicated workflow that is worth turning into Laravel Actions
- [x] Extract the saved-search create, update, and execute workflows into dedicated action classes
- [x] Rewire the saved-search Livewire page and API controller around the new actions
- [x] Add focused regression coverage for the shared max-10 rule on the Livewire page
- [x] Run focused Rector, PHPStan, Pest, and Pint verification for the saved-search batch

## Saved Search Action Extraction Review

- Added `CreateSavedSearchAction`, `UpdateSavedSearchAction`, and `ExecuteSavedSearchAction` so saved-search mutation and execution workflows now live in reusable Laravel Actions instead of inline controller and Livewire orchestration.
- Added `SavedSearchLimitReachedException` so the shared max-10 rule is enforced in one place while the Livewire page and API still keep their own user-facing error presentation.
- Rewired `app/Livewire/Pages/SavedSearches/Index.php` to delegate create and update workflows to the new actions while keeping page-specific auth, validation, form state, and toast behavior local to the component.
- Rewired `app/Http/Controllers/Api/SavedSearchController.php` so store, update, and execute now delegate to actions, leaving the controller focused on validation, authorization, and HTTP responses.
- Added a Livewire regression in `tests/Feature/SavedSearchPageTest.php` to prove the shared max-10 rule still applies on the saved-search page after moving creation into an action.
- Verification:
  - `vendor/bin/rector process app/Actions/SavedSearches app/Exceptions/SavedSearchLimitReachedException.php app/Http/Controllers/Api/SavedSearchController.php app/Livewire/Pages/SavedSearches/Index.php tests/Feature/SavedSearchApiTest.php tests/Feature/SavedSearchPageTest.php tests/Feature/ProductSignalsTelemetryTest.php tests/Feature/UiFeedbackExperienceTest.php` => pass, 2 files changed
  - `vendor/bin/phpstan analyse --ansi app/Actions/SavedSearches app/Exceptions/SavedSearchLimitReachedException.php app/Http/Controllers/Api/SavedSearchController.php app/Livewire/Pages/SavedSearches/Index.php tests/Feature/SavedSearchApiTest.php tests/Feature/SavedSearchPageTest.php tests/Feature/ProductSignalsTelemetryTest.php tests/Feature/UiFeedbackExperienceTest.php` => no errors
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(SavedSearchApiTest|SavedSearchPageTest|ProductSignalsTelemetryTest|UiFeedbackExperienceTest)'` => 57 passed
  - `vendor/bin/pint --dirty --format agent` => pass

# Full Project Verification Sweep

- [x] Run `php artisan migrate:fresh --seed`
- [x] Run full-project `rector`
- [x] Run full-project `phpstan`
- [x] Run full-project `pest --parallel`
- [x] Run full-project `pint`
- [x] Fix any issues surfaced by the full-project sweep
- [x] Re-run the failing checks until the project is clean

## Full Project Verification Sweep Review

- Removed an orphaned `User::query()->get();` call from `InstitutionSeeder`, which was breaking `migrate:fresh --seed` with a missing class error.
- Accepted the full-project Rector pass, which mainly tightened readonly classes, class constants, first-class callables, and a few smaller modernizations across 17 files.
- Fixed share analytics typing by giving the Filament page a real default report payload and making the analytics service return explicit typed list payloads instead of collection templates that PHPStan could not prove.
- Hardened the reverse-permission feedback block check so missing `feedback.blocked` seed data no longer causes report submission to 500; the app now treats an absent permission row as “not blocked”.
- Final verification:
  - `php artisan migrate:fresh --seed` => pass
  - `vendor/bin/rector process` => pass, 17 files changed
  - `vendor/bin/phpstan analyse --ansi` => pass
  - `vendor/bin/pest --parallel` => 856 passed
  - `vendor/bin/pint --format agent` => pass
  - Post-Pint narrowed `phpstan` on Pint-touched files => pass

# Authz User Index Global Roles Fix

- [x] Restore authz user index search by global role name
- [x] Remove the global role badge N+1 by switching back to a relationship-backed column
- [x] Add focused regression coverage and rerun verification for the authz user resource

## Authz User Index Global Roles Fix Review

- Added a dedicated `globalRoles()` relation on `User` that only resolves global role assignments, even with scoped/team authz enabled.
- Switched the authz user index back to a relationship-backed role column using `globalRoles.name`, which restores built-in role-name search and avoids per-row role queries for the badge state.
- Added a focused authz user index regression test that proves searching by a global role name returns the expected user records.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `vendor/bin/pest --parallel --compact tests/Feature/AuthzUserResourceTest.php` => 7 passed
  - `vendor/bin/phpstan analyse --ansi app/Models/User.php app/Filament/Resources/Authz/UserResource.php tests/Feature/AuthzUserResourceTest.php` => no errors

# Protected Scoped Role Admin Surface

- [x] Allow the central authz user edit page to override protected scoped owner roles explicitly
- [x] Add a protected-role management section to the authz user edit page while keeping local membership UIs locked down
- [x] Add focused regression coverage and verify the new central override path

## Protected Scoped Role Admin Surface Review

- Extended `ChangeSubjectMemberRole` so the authz user edit page can explicitly override protected shared member roles by subject type, while local membership flows remain blocked from changing them.
- Added a dedicated `Protected Scoped Roles` section to the authz user edit page with per-type ownership cards, membership context, and a central replacement-role selector.
- Reloaded the authz user edit record state after protected-role changes so the page and read-only membership summary stay in sync after a central override.
- Added focused regression coverage for both the explicit action-level bypass and the authz user page workflow.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(MembershipActionsTest|AuthzUserResourceTest|MemberRoleModalHydrationTest|DashboardPagesTest)'` => 42 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Membership/ChangeSubjectMemberRole.php app/Filament/Resources/Authz/UserResource.php app/Filament/Resources/Authz/UserResource/Pages/EditUser.php tests/Feature/MembershipActionsTest.php tests/Feature/AuthzUserResourceTest.php` => no errors

# Membership Audit Fixes

- [x] Make shared member role resolution safe for UUID-backed PostgreSQL roles
- [x] Fix add/remove membership actions so they do not clear shared scoped roles incorrectly
- [x] Enforce that protected owner roles can only be changed from the global authz surface
- [x] Align the legacy report alias with the authenticated report flow
- [x] Add focused regression coverage and run verification for the touched paths

## Membership Audit Fixes Review

- Updated shared member role lookup to avoid UUID-casting failures on PostgreSQL when role names like `owner` or `admin` are passed through the Laravel Actions workflow.
- Changed membership attach/remove semantics so adding a membership without an explicit role preserves the existing shared scoped role, non-protected removals clear roles only when no memberships of that type remain, and protected ownership memberships are blocked from local removal entirely.
- Marked the event `organizer` role as protected alongside institution/speaker/reference `owner` roles, then blocked local role-edit/remove actions for those protected roles in the relation managers and institution dashboard while keeping global authz editing as the only removal path.
- Moved the legacy `/report/{subjectType}/{subjectId}` alias into the authenticated route group so it now matches the canonical report page behavior and redirects guests to login instead of returning a component-level `403`.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(MembershipActionsTest|MemberInvitationActionsTest|ContributionWorkflowServiceTest|ContributionPagesTest|DashboardPagesTest|MemberRoleModalHydrationTest)'` => 60 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Membership app/Enums/MemberSubjectType.php app/Support/Authz/MemberRoleCatalog.php app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php app/Filament/Resources/References/RelationManagers/MembersRelationManager.php app/Filament/Resources/Events/RelationManagers/EventUsersRelationManager.php app/Livewire/Pages/Dashboard/InstitutionDashboard.php routes/web.php tests/Feature/MembershipActionsTest.php tests/Feature/MemberRoleModalHydrationTest.php tests/Feature/DashboardPagesTest.php tests/Feature/ContributionPagesTest.php` => no errors
  - `vendor/bin/pint --dirty --format agent` => pass

# Hard-Cut Authz And Membership Refactor

- [x] Verify local `filament-authz` package changes with focused package tests
- [x] Replace hard-coded scoped member role definitions with a central app role catalog
- [x] Add Laravel Actions membership workflow layer and route active mutation paths through it
- [x] Remove legacy model-specific authz scope usage from active models, seeders, and runtime paths
- [x] Rebuild member-management UI to single-role workflows and add reference member management
- [x] Rebuild admin authz UX around global role editing plus read-only membership summaries
- [x] Add destructive cleanup migration for legacy model-backed authz scopes and assignments
- [x] Add domain member invitation actions and persistence on top of the new workflow layer
- [x] Update focused application tests for the new hard-cut model and run verification

## Refactor Review

- Switched Majlis Ilmu to the local path repository for `aiarmada/filament-authz` and verified Composer resolves `/Users/Saiffil/Herd/commerce/packages/filament-authz` during development.
- Added generic `filament-authz` improvements in the Commerce package: configurable user role scope mode (`all`, `global_only`, `scoped_only`) and configurable role scope options for the role resource form/filter, with README and configuration docs updated.
- Replaced hard-coded shared member role definitions with `App\Support\Authz\MemberRoleCatalog`, and updated `ScopedMemberRoleSeeder` plus `ScopedMemberRolesSeeder` to seed institution, speaker, event, and reference shared scopes from that catalog.
- Added Laravel Actions membership workflows under `app/Actions/Membership` for add/change/remove/owner assignment, then routed contribution ownership and active admin/dashboard member mutations through those actions.
- Hard-removed legacy model-specific authz scope usage from active app code by deleting `HasAuthzScope` usage and legacy scope labels from `Institution` and `Speaker`, removing dormant per-model scoped role seeding from seeders, and adding a destructive cleanup migration for old model-backed scope rows and assignments.
- Rebuilt institution, speaker, event, and reference member management to use single-role assignment instead of multi-role selection, and added missing reference member management in the admin resource.
- Reworked the authz user editor to keep editable global roles/direct permissions while showing memberships as a read-only summary with links back to the owning institution, speaker, event, and reference resources.
- Added backend invitation support with `member_invitations`, `MemberInvitation`, and Laravel Actions for invite, accept, and revoke flows. Acceptance routes through the shared membership actions instead of duplicating attach/role logic.
- Added focused regression coverage for membership actions, invitation actions, reference member management, single-role modal hydration, admin authz UX, dashboard flows, contribution ownership assignment, and the “no model-specific authz scopes” guarantee.
- Verification:
  - `composer show aiarmada/filament-authz -P` => `/Users/Saiffil/Herd/commerce/packages/filament-authz`
  - `/Users/Saiffil/Herd/commerce/vendor/bin/pest --parallel tests/src/FilamentAuthzScoped/UserAuthzFormTest.php` => 3 passed
  - `/Users/Saiffil/Herd/commerce/vendor/bin/phpstan analyse --ansi packages/filament-authz/src/FilamentAuthzPlugin.php packages/filament-authz/src/Resources/RoleResource.php packages/filament-authz/src/Support/UserAuthzForm.php tests/src/FilamentAuthz/FilamentAuthzTestCase.php tests/src/FilamentAuthzScoped/UserAuthzFormTest.php` => no errors
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(MemberInvitationActionsTest|MembershipActionsTest|MemberRoleModalHydrationTest|PublicSubmissionLockActionsTest|AuthzUserResourceTest|AdminResourcesCoverageTest|DashboardPagesTest|AhliPanelInstitutionEditingTest|ContributionWorkflowServiceTest|MemberPermissionGateTest|ReferenceAuthorizationTest|FilamentPanelAccessTest|ContributionPagesTest|InstitutionSeederTest|ScopedMemberRoleSeederTest)'` => 104 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Membership app/Enums/MemberSubjectType.php app/Filament/Resources/Authz/UserResource.php app/Filament/Resources/Authz/UserResource/Pages/EditUser.php app/Filament/Resources/Authz/UserResource/Pages/ViewUser.php app/Filament/Resources/Events/RelationManagers/EventUsersRelationManager.php app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php app/Filament/Resources/References/ReferenceResource.php app/Filament/Resources/References/RelationManagers/MembersRelationManager.php app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php app/Forms/InstitutionFormSchema.php app/Forms/SpeakerFormSchema.php app/Livewire/Pages/Dashboard/InstitutionDashboard.php app/Models/Event.php app/Models/Institution.php app/Models/MemberInvitation.php app/Models/Speaker.php app/Providers/Filament/AdminPanelProvider.php app/Services/ContributionEntityMutationService.php app/Services/ContributionWorkflowService.php app/Support/Authz/MemberRoleCatalog.php app/Support/Authz/MemberRoleScopes.php app/Support/Authz/ScopedMemberRoleSeeder.php database/migrations/2026_03_14_120000_prune_legacy_model_authz_scopes.php database/migrations/2026_03_14_130000_create_member_invitations_table.php tests/Feature/MembershipActionsTest.php tests/Feature/MemberInvitationActionsTest.php tests/Feature/AuthzUserResourceTest.php tests/Feature/PublicSubmissionLockActionsTest.php tests/Feature/MemberRoleModalHydrationTest.php tests/Feature/AdminResourcesCoverageTest.php` => no errors
# Contribution Submission State Extraction

- [x] Extract shared contribution submission-state normalization into a reusable Laravel Action
- [x] Rewire staged create and update suggestion flows around the shared submission-state action
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the submission-state batch

## Contribution Submission State Extraction Review

- Added `app/Actions/Contributions/ResolveContributionSubmissionStateAction.php` so proposer-note trimming and contribution form-state cleanup now live in one reusable Laravel Action instead of being duplicated between staged create and update suggestion flows.
- Rewired `app/Actions/Contributions/SubmitStagedContributionCreateAction.php` and `app/Livewire/Pages/Contributions/SuggestUpdate.php` to consume that shared action, leaving each caller focused on entity mutation, diffing, authorization, and redirect flow.
- Added direct action coverage in `tests/Feature/ContributionWorkflowActionsTest.php` proving the shared submission-state action strips `proposer_note` out of request state while preserving a trimmed note payload for downstream contribution request actions.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ContributionWorkflowActionsTest.php`, `tests/Feature/ContributionPagesTest.php` => 26 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/ResolveContributionSubmissionStateAction.php app/Actions/Contributions/SubmitStagedContributionCreateAction.php app/Livewire/Pages/Contributions/SuggestUpdate.php tests/Feature/ContributionWorkflowActionsTest.php tests/Feature/ContributionPagesTest.php` => no errors

# Suggest Update Diff Extraction

- [x] Extract shared contribution change-detection logic into a reusable Laravel Action
- [x] Rewire `SuggestUpdate` around the shared changed-payload action
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the diff extraction batch

## Suggest Update Diff Extraction Review

- Added `app/Actions/Contributions/ResolveContributionChangedPayloadAction.php` so contribution change detection, comparable normalization, and nested payload diffing no longer live inline inside `SuggestUpdate`.
- Rewired `app/Livewire/Pages/Contributions/SuggestUpdate.php` to use that shared action during submission, leaving the component focused on auth, UI validation, redirect flow, and dispatching direct-edit or pending-request actions.
- Added direct action coverage in `tests/Feature/ContributionWorkflowActionsTest.php` proving the shared diff action correctly ignores unchanged scalar/date/collection values while preserving real nested payload changes.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ContributionWorkflowActionsTest.php`, `tests/Feature/ContributionPagesTest.php` => 25 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/ResolveContributionChangedPayloadAction.php app/Livewire/Pages/Contributions/SuggestUpdate.php tests/Feature/ContributionWorkflowActionsTest.php tests/Feature/ContributionPagesTest.php` => no errors

# Contribution Review Request Resolver Extraction

- [x] Extract shared contribution review-request lookup and authorization into a reusable Laravel Action
- [x] Rewire contribution inbox approve/reject flows around the shared review-request resolver
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the inbox resolver batch

## Contribution Review Request Resolver Extraction Review

- Added `app/Actions/Contributions/ResolveReviewableContributionRequestAction.php` so contribution review-request lookup and reviewer authorization now live in one reusable Laravel Action instead of being repeated inside both inbox approve and reject handlers.
- Rewired `app/Livewire/Pages/Contributions/Index.php` to use that resolver for approve/reject flows, leaving the component focused on UI state and dispatching the final action once a reviewable request has been resolved.
- Added direct action coverage in `tests/Feature/ContributionWorkflowActionsTest.php` for the new review-request resolver while keeping the page-level inbox approval and rejection regressions green.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ContributionWorkflowActionsTest.php`, `tests/Feature/ContributionPagesTest.php` => 24 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/ResolveReviewableContributionRequestAction.php app/Actions/Contributions/CanReviewContributionRequestAction.php app/Livewire/Pages/Contributions/Index.php tests/Feature/ContributionWorkflowActionsTest.php tests/Feature/ContributionPagesTest.php` => no errors

# Report Entity Metadata Extraction

- [x] Extract shared report entity metadata into a reusable Laravel Action
- [x] Rewire the API controller and Filament report resource around the shared entity metadata action
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the entity metadata batch

## Report Entity Metadata Extraction Review

- Added `app/Actions/Reports/ResolveReportEntityMetadataAction.php` so reportable entity labels, model-class resolution, and valid entity-type keys now live in one reusable Laravel Action instead of being repeated between API validation, model resolution, and the Filament report resource.
- Rewired `app/Http/Controllers/Api/ReportController.php` to use that shared metadata for entity-type validation and model lookup, which removed another inline `match` block from the controller.
- Rewired `app/Filament/Resources/Reports/Schemas/ReportForm.php` and `app/Filament/Resources/Reports/Tables/ReportsTable.php` to use the same shared entity-type options, keeping the admin form/filter surface aligned with the API contract.
- Added direct action coverage in `tests/Feature/ReportActionsTest.php` for the shared entity metadata action while keeping the report page and API regressions green.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ReportActionsTest.php`, `tests/Feature/ContributionPagesTest.php`, `tests/Feature/Api/ReportApiModerationTest.php` => 22 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Reports/ResolveReportCategoryOptionsAction.php app/Actions/Reports/ResolveReportEntityMetadataAction.php app/Actions/Reports/ResolveReportFormContextAction.php app/Livewire/Pages/Reports/Create.php app/Http/Controllers/Api/ReportController.php app/Filament/Resources/Reports/Schemas/ReportForm.php app/Filament/Resources/Reports/Tables/ReportsTable.php tests/Feature/ReportActionsTest.php tests/Feature/ContributionPagesTest.php tests/Feature/Api/ReportApiModerationTest.php` => no errors

# Report Category Catalog Extraction

- [x] Extract a shared report category catalog into a reusable Laravel Action
- [x] Rewire the public report flow, API controller, and Filament report resource around the shared category action
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the report category batch

## Report Category Catalog Extraction Review

- Added `app/Actions/Reports/ResolveReportCategoryOptionsAction.php` so subject-specific report category labels and admin-wide category lists now live in one reusable Laravel Action instead of being duplicated across the public report page, API validation, and Filament resource definitions.
- Rewired `app/Actions/Reports/ResolveReportFormContextAction.php` and `app/Http/Controllers/Api/ReportController.php` to consume that shared catalog, keeping the public report flow and API validation aligned on the same canonical category keys.
- Rewired `app/Filament/Resources/Reports/Schemas/ReportForm.php` and `app/Filament/Resources/Reports/Tables/ReportsTable.php` to use the same shared admin category list, which also closes the previous admin drift where some valid categories were missing from the resource UI.
- Added direct action coverage in `tests/Feature/ReportActionsTest.php` plus an API regression in `tests/Feature/Api/ReportApiModerationTest.php` proving shared reference-specific categories are accepted by the controller.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ReportActionsTest.php`, `tests/Feature/ContributionPagesTest.php`, `tests/Feature/Api/ReportApiModerationTest.php` => 21 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Reports/ResolveReportCategoryOptionsAction.php app/Actions/Reports/ResolveReportFormContextAction.php app/Livewire/Pages/Reports/Create.php app/Http/Controllers/Api/ReportController.php app/Filament/Resources/Reports/Schemas/ReportForm.php app/Filament/Resources/Reports/Tables/ReportsTable.php tests/Feature/ReportActionsTest.php tests/Feature/ContributionPagesTest.php tests/Feature/Api/ReportApiModerationTest.php` => no errors

# Shared Subject Presentation Extraction

- [x] Extract shared contribution/report subject presentation metadata into a reusable Laravel Action
- [x] Rewire `SuggestUpdate` and the report context action around the shared subject presentation action
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the subject presentation batch

## Shared Subject Presentation Extraction Review

- Added `app/Actions/Contributions/ResolveContributionSubjectPresentationAction.php` so subject label and subject redirect URL mapping for events, institutions, speakers, and references now live in one reusable action.
- Rewired `app/Livewire/Pages/Contributions/SuggestUpdate.php` to consume that presentation context instead of carrying its own `subjectLabel()` and `entityUrl()` helpers for direct-edit redirect and heading text.
- Rewired `app/Actions/Reports/ResolveReportFormContextAction.php` to compose the shared subject presentation action instead of duplicating report-side label and redirect mapping.
- Added direct regression coverage in `tests/Feature/ContributionWorkflowActionsTest.php` for the shared presentation action while keeping `SuggestUpdate` and report flow regressions green.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ContributionWorkflowActionsTest.php`, `tests/Feature/ContributionPagesTest.php`, `tests/Feature/ReportActionsTest.php` => 25 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/ResolveContributionSubjectPresentationAction.php app/Actions/Reports/ResolveReportFormContextAction.php app/Livewire/Pages/Contributions/SuggestUpdate.php tests/Feature/ContributionWorkflowActionsTest.php tests/Feature/ContributionPagesTest.php tests/Feature/ReportActionsTest.php` => no errors

# Staged Contribution Create Extraction

- [x] Extract the shared staged institution/speaker create workflow into a reusable Laravel Action
- [x] Rewire the public institution and speaker contribution pages around the staged create action
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the staged create batch

## Staged Contribution Create Extraction Review

- Added `app/Actions/Contributions/SubmitStagedContributionCreateAction.php` so note extraction, staged entity creation, optional relationship persistence, and contribution-request submission now live in one reusable action for institution and speaker create flows.
- Rewired `app/Livewire/Pages/Contributions/SubmitInstitution.php` and `app/Livewire/Pages/Contributions/SubmitSpeaker.php` to use that action, leaving each page focused on defaults, form rendering, success toast, and redirect flow.
- Added direct regression coverage in `tests/Feature/ContributionWorkflowActionsTest.php` proving both staged institution and staged speaker submissions still create pending records and linked contribution requests.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ContributionWorkflowActionsTest.php`, `tests/Feature/ContributionPagesTest.php` => 22 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/SubmitStagedContributionCreateAction.php app/Livewire/Pages/Contributions/SubmitInstitution.php app/Livewire/Pages/Contributions/SubmitSpeaker.php tests/Feature/ContributionWorkflowActionsTest.php tests/Feature/ContributionPagesTest.php` => no errors

# Report Context And Fingerprint Extraction

- [x] Extract report form context into a reusable Laravel Action
- [x] Extract reporter fingerprint resolution into a reusable Laravel Action shared by web and API report flows
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the report extraction batch

## Report Context And Fingerprint Extraction Review

- Added `app/Actions/Reports/ResolveReportFormContextAction.php` so report subject labels, category options, default category selection, and post-submit redirect URLs no longer live inline inside the public report page.
- Added `app/Actions/Reports/ResolveReporterFingerprintAction.php` so the public report page and `app/Http/Controllers/Api/ReportController.php` now share one reporter fingerprint implementation instead of maintaining duplicate user/IP/user-agent hashing logic.
- Rewired `app/Livewire/Pages/Reports/Create.php` and `app/Http/Controllers/Api/ReportController.php` around those actions, leaving the page/controller focused on auth, validation, duplicate-report handling, and response/redirect flow.
- Added direct action coverage in `tests/Feature/ReportActionsTest.php` for report form context and reporter fingerprint resolution while keeping the public page and API report regressions green.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ReportActionsTest.php`, `tests/Feature/ContributionPagesTest.php`, `tests/Feature/Api/ReportApiModerationTest.php` => 19 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Reports/ResolveReportFormContextAction.php app/Actions/Reports/ResolveReporterFingerprintAction.php app/Livewire/Pages/Reports/Create.php app/Http/Controllers/Api/ReportController.php tests/Feature/ReportActionsTest.php tests/Feature/ContributionPagesTest.php tests/Feature/Api/ReportApiModerationTest.php` => no errors

# Shared Contribution Subject Resolver Extraction

- [x] Extract a generic contribution/report subject resolver into a reusable Laravel Action
- [x] Rewire the contribution update context action and public report page to use the shared subject resolver
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the shared subject resolver batch

## Shared Contribution Subject Resolver Extraction Review

- Added `app/Actions/Contributions/ResolveContributionSubjectAction.php` so slug/UUID subject lookup for events, institutions, speakers, and references now lives in one reusable action.
- Rewired `app/Actions/Contributions/ResolveContributionUpdateContextAction.php` to compose the shared subject resolver instead of carrying its own duplicate lookup methods.
- Rewired `app/Livewire/Pages/Reports/Create.php` to use the same shared resolver during `mount()`, removing another copy of the slug/UUID entity resolution logic from the public reporting flow.
- Added direct action coverage in `tests/Feature/ContributionWorkflowActionsTest.php` for the shared subject resolver while keeping the page-level report and suggestion regressions in `tests/Feature/ContributionPagesTest.php` green.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ContributionWorkflowActionsTest.php`, `tests/Feature/ContributionPagesTest.php` => 20 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/ResolveContributionSubjectAction.php app/Actions/Contributions/ResolveContributionUpdateContextAction.php app/Livewire/Pages/Reports/Create.php tests/Feature/ContributionWorkflowActionsTest.php tests/Feature/ContributionPagesTest.php` => no errors

# Contribution Inbox Review Extraction

- [x] Extract contribution inbox review authorization into a reusable Laravel Action
- [x] Extract pending contribution approval queue resolution into a reusable Laravel Action
- [x] Rewire the contributions inbox Livewire page around the extracted review actions
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the inbox review batch

## Contribution Inbox Review Extraction Review

- Added `app/Actions/Contributions/CanReviewContributionRequestAction.php` so the contribution inbox no longer owns the reviewer eligibility rules for privileged roles, create requests, and maintainer-owned update requests.
- Added `app/Actions/Contributions/ResolvePendingContributionApprovalsAction.php` so pending approval queue filtering now lives in one reusable action instead of an inline `pendingApprovals()` query-and-filter loop in the Livewire page.
- Rewired `app/Livewire/Pages/Contributions/Index.php` to use those actions for review authorization and pending queue resolution, leaving the component focused on UI state, notes/reason capture, and dispatching approve/reject/cancel flows.
- Added direct action coverage in `tests/Feature/ContributionWorkflowActionsTest.php` for reviewer eligibility and pending approval resolution, while keeping the page-level approve/reject regressions in `tests/Feature/ContributionPagesTest.php` green.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ContributionWorkflowActionsTest.php`, `tests/Feature/ContributionPagesTest.php` => 19 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/CanReviewContributionRequestAction.php app/Actions/Contributions/ResolvePendingContributionApprovalsAction.php app/Livewire/Pages/Contributions/Index.php tests/Feature/ContributionWorkflowActionsTest.php tests/Feature/ContributionPagesTest.php` => no errors

# Suggest Update Context And Builder Membership Extraction

- [x] Extract `SuggestUpdate` entity resolution and initial-state loading into a reusable Laravel Action
- [x] Consolidate advanced builder institution/speaker membership lookups behind a shared Laravel Action
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the context and membership batch

## Suggest Update Context And Builder Membership Extraction Review

- Added `app/Actions/Contributions/ResolveContributionUpdateContextAction.php` so `SuggestUpdate` no longer owns slug/UUID subject lookup or initial-state loading for event, institution, speaker, and reference updates.
- Added `app/Actions/Events/ResolveAdvancedBuilderMembershipOptionsAction.php` so the advanced builder context and submission-preparation actions reuse one membership query path for organizer/location options and ownership checks.
- Rewired `app/Livewire/Pages/Contributions/SuggestUpdate.php`, `app/Actions/Events/ResolveAdvancedBuilderContextAction.php`, and `app/Actions/Events/PrepareAdvancedParentProgramSubmissionAction.php` around those actions, leaving the component/actions focused on diffing, validation, and redirect flow.
- Added direct regression coverage for contribution update context resolution and shared advanced builder membership option filtering in `tests/Feature/ContributionWorkflowActionsTest.php` and `tests/Feature/EventActionsTest.php`.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ContributionWorkflowActionsTest.php`, `tests/Feature/EventActionsTest.php`, `tests/Feature/ContributionPagesTest.php`, `tests/Feature/DashboardPagesTest.php` => 42 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/ResolveContributionUpdateContextAction.php app/Actions/Events/ResolveAdvancedBuilderMembershipOptionsAction.php app/Actions/Events/ResolveAdvancedBuilderContextAction.php app/Actions/Events/PrepareAdvancedParentProgramSubmissionAction.php app/Livewire/Pages/Contributions/SuggestUpdate.php tests/Feature/ContributionWorkflowActionsTest.php tests/Feature/EventActionsTest.php tests/Feature/ContributionPagesTest.php tests/Feature/DashboardPagesTest.php` => no errors

# Contribution Workflow Correction

# Direct Edit And Builder Context Extraction

- [x] Extract the direct-edit contribution workflow into a reusable Laravel Action
- [x] Extract advanced builder bootstrap/default-state resolution into a reusable Laravel Action
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the direct-edit and builder-context batch

## Direct Edit And Builder Context Extraction Review

- Added `app/Actions/Contributions/ApplyDirectContributionUpdateAction.php` so `SuggestUpdate` no longer owns the direct-edit mutation and event re-moderation orchestration for maintainer edits.
- Added `app/Actions/Events/ResolveAdvancedBuilderContextAction.php` so `CreateAdvanced` no longer builds its organizer/location option state and default form values inline during `mount()`.
- Rewired `app/Livewire/Pages/Contributions/SuggestUpdate.php` and `app/Livewire/Pages/Dashboard/Events/CreateAdvanced.php` to call those actions directly, leaving the components focused on authorization, validation, and redirect/UI flow.
- Extended focused coverage with direct action tests and page regressions proving sensitive direct event edits still re-moderate approved events and institution-prefilled advanced builder defaults still resolve correctly.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ContributionPagesTest.php`, `tests/Feature/AdvancedEventCreationTest.php`, `tests/Feature/EventActionsTest.php` => 21 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions/ApplyDirectContributionUpdateAction.php app/Actions/Events/ResolveAdvancedBuilderContextAction.php app/Livewire/Pages/Contributions/SuggestUpdate.php app/Livewire/Pages/Dashboard/Events/CreateAdvanced.php tests/Feature/ContributionPagesTest.php tests/Feature/AdvancedEventCreationTest.php tests/Feature/EventActionsTest.php` => no errors

# Advanced Builder Preparation And Event Sync Extraction

- [x] Extract advanced builder submission preparation into a reusable Laravel Action
- [x] Extract shared event relation-sync persistence for Filament event pages into a reusable Laravel Action
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the builder preparation and event sync batch

## Advanced Builder Preparation And Event Sync Extraction Review

- Added `app/Actions/Events/PrepareAdvancedParentProgramSubmissionAction.php` so the advanced builder no longer owns timezone parsing, organizer ownership checks, location resolution, or parent-program time validation inside the Livewire submit handler.
- Added `app/Actions/Events/SyncEventResourceRelationsAction.php` and rewired the admin create/edit pages plus the Ahli edit page to use one shared action for registration-mode persistence, tag syncing, language syncing, and key-person syncing.
- This also closes the Ahli edit drift where the page prefilled `speakers` and `other_key_people` but did not persist speaker/key-person changes on save.
- Added focused regressions in `tests/Feature/AdvancedEventCreationTest.php`, `tests/Feature/AhliEventFeaturedGuardTest.php`, and the new `tests/Feature/EventActionsTest.php` to cover the extracted builder preparation path, the Ahli speaker-sync behavior, and the shared event sync action directly.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/AdvancedEventCreationTest.php`, `tests/Feature/AhliEventFeaturedGuardTest.php`, `tests/Feature/EventActionsTest.php` => 13 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Events/PrepareAdvancedParentProgramSubmissionAction.php app/Actions/Events/SyncEventResourceRelationsAction.php app/Livewire/Pages/Dashboard/Events/CreateAdvanced.php app/Filament/Resources/Events/Pages/CreateEvent.php app/Filament/Resources/Events/Pages/EditEvent.php app/Filament/Ahli/Resources/Events/Pages/EditEvent.php tests/Feature/AdvancedEventCreationTest.php tests/Feature/AhliEventFeaturedGuardTest.php tests/Feature/EventActionsTest.php` => no errors

# Advanced Builder Action Extraction

- [x] Move advanced parent-program creation into a reusable Laravel Action
- [x] Remove the now-unused `ContributionWorkflowService` wrapper and keep contribution workflow coverage action-based
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the builder extraction and wrapper removal

## Advanced Builder Action Extraction Review

- Extracted the parent-program creation transaction from the Livewire builder into `app/Actions/Events/CreateAdvancedParentProgramAction.php`, leaving `CreateAdvanced` responsible for validation, membership checks, and redirect flow only.
- Removed the now-unused `app/Services/ContributionWorkflowService.php` after confirming no application callers remained, and updated `tests/Feature/ContributionWorkflowServiceTest.php` to exercise the contribution actions directly so coverage stays intact without the compatibility layer.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/AdvancedEventCreationTest.php`, `tests/Feature/ContributionWorkflowServiceTest.php`, `tests/Feature/ContributionWorkflowActionsTest.php` => 17 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Events/CreateAdvancedParentProgramAction.php app/Actions/Contributions app/Livewire/Pages/Dashboard/Events/CreateAdvanced.php tests/Feature/AdvancedEventCreationTest.php tests/Feature/ContributionWorkflowServiceTest.php tests/Feature/ContributionWorkflowActionsTest.php` => no errors

# Laravel Actions Entry Point Migration

- [x] Rewire contribution Livewire pages to call contribution action classes directly instead of routing through the compatibility service
- [x] Extract report submission into a reusable Laravel Action and remove the obsolete `ReportService`
- [x] Extract event save/unsave and interest/uninterest flows into reusable Laravel Actions behind the API controllers
- [x] Re-run focused Pest coverage, Pint, and narrowed PHPStan for the direct-caller migration batch

## Laravel Actions Entry Point Migration Review

- Rewired the contribution entrypoints in `SubmitSpeaker`, `SubmitInstitution`, `SuggestUpdate`, and the contributions inbox to call the new `app/Actions/Contributions/*` classes directly. The temporary `ContributionWorkflowService` compatibility wrapper was removed in a later cleanup batch once no application callers remained.
- Moved report submission orchestration into `app/Actions/Reports/SubmitReportAction.php`, updated both the API controller and the public Livewire report page to use that action directly, and removed the now-obsolete `app/Services/ReportService.php`.
- Added reusable event engagement actions under `app/Actions/Events` for save, unsave, mark interest, and remove interest, then slimmed `EventSaveController` and `EventInterestController` down to validation and HTTP response mapping.
- Verification:
  - `vendor/bin/pint --dirty --format agent` => pass
  - `runTests`: `tests/Feature/ContributionPagesTest.php`, `tests/Feature/Api/EventSaveTest.php`, `tests/Feature/EventPledgeTest.php`, `tests/Feature/Api/ReportApiModerationTest.php` => 39 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Events app/Actions/Reports/SubmitReportAction.php app/Livewire/Pages/Contributions/SubmitSpeaker.php app/Livewire/Pages/Contributions/SubmitInstitution.php app/Livewire/Pages/Contributions/SuggestUpdate.php app/Livewire/Pages/Contributions/Index.php app/Livewire/Pages/Reports/Create.php app/Http/Controllers/Api/EventSaveController.php app/Http/Controllers/Api/EventInterestController.php app/Http/Controllers/Api/ReportController.php tests/Feature/ContributionPagesTest.php tests/Feature/Api/EventSaveTest.php tests/Feature/EventPledgeTest.php tests/Feature/Api/ReportApiModerationTest.php` => no errors

# Laravel Actions Contribution Migration

- [x] Add first-wave `lorisleiva/laravel-actions` classes for contribution request workflows
- [x] Delegate `ContributionWorkflowService` methods to the new action classes without breaking existing call sites
- [x] Add focused Pest coverage proving the new actions work directly and the compatibility layer still passes
- [x] Run focused tests and Pint for the migration batch

## Laravel Actions Contribution Migration Review

- Added first-wave contribution workflow actions under `app/Actions/Contributions` for create-request submission, update-request submission, approval, rejection, and cancellation using `lorisleiva/laravel-actions` via `AsAction`.
- Converted `ContributionWorkflowService` into a temporary compatibility wrapper during the first migration batch, then removed it once the app no longer had direct callers and the tests were updated to exercise the action layer itself.
- Added direct action coverage in `tests/Feature/ContributionWorkflowActionsTest.php` while keeping the existing service-level regression file in place.
- Removed the temporary optional guard around `FilamentSignalsPlugin` and fixed the original environment issue the proper way with `composer install`, which restored `aiarmada/signals` and `aiarmada/filament-signals` into `vendor`.
- Verification:
  - `composer install` => installed `aiarmada/signals` and `aiarmada/filament-signals`, package discovery passed
  - `vendor/bin/pint --dirty --format agent` => pass
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionWorkflowServiceTest.php` => 9 passed
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionWorkflowActionsTest.php` => 4 passed
  - `vendor/bin/phpstan analyse --ansi app/Actions/Contributions app/Services/ContributionWorkflowService.php app/Providers/Filament/AdminPanelProvider.php tests/Feature/ContributionWorkflowActionsTest.php tests/Feature/ContributionWorkflowServiceTest.php` => no errors

- [x] Restore dedicated `/sumbangan/institusi/baru` and `/sumbangan/penceramah/baru` pages as the canonical create flow
- [x] Remove the inaccurate inline institution and speaker submission forms from the public index pages
- [x] Expand the institution and speaker contribution pages to use richer, source-aligned form sections
- [x] Upgrade update suggestions so institution, speaker, reference, and event edits submit directly-applicable payloads
- [x] Extend contribution approval to apply structured relationship data instead of flat fillable attributes only
- [x] Re-run focused tests, Pint, and narrowed PHPStan for the corrected flow

## Contribution Workflow Correction Review

- Restored dedicated authenticated create pages at `/sumbangan/institusi/baru` and `/sumbangan/penceramah/baru`, and removed the incorrect inline submission forms from the public institution and speaker directory pages.
- Added richer shared contribution schemas for institution, speaker, reference, and event edits so update requests now carry structured data aligned with the real source forms instead of a small note-style subset.
- Added `ContributionEntityMutationService` so direct edits and approval actions apply nested address, contacts, social links, speakers, references, tags, and other structured payloads instead of only `fillable` attributes.
- Changed institution and speaker creation to stage pending entities first, then attach contribution requests to those staged records so reviewers can approve the real record in one action.
- Verification:
  - `runTests`: `tests/Feature/ContributionPagesTest.php`, `tests/Feature/ContributionWorkflowServiceTest.php` => 17 passed
  - `vendor/bin/pint --dirty --format agent` => pass
  - Editor diagnostics on all touched files => clean
  - Narrowed `phpstan analyse` attempts were interrupted in this shell before completion, so static verification fell back to clean editor diagnostics for the touched files

# Signals Surface Split And Identity Stitching

- [x] Replace implicit default property lookup with explicit public/admin Signals surface resolution
- [x] Stop reusing the public tracker config on admin pages unless a dedicated admin property is enabled
- [x] Stitch browser and server telemetry with shared anonymous/session identifiers plus identify calls for authenticated users
- [x] Refine search telemetry taxonomy to separate query-driven searches from filter-only discovery
- [x] Expand auth and moderation telemetry for signup, password reset, email verification, and moderation state transitions
- [x] Run focused tests, formatting, and static analysis for the completed Signals rollout

## Review

- Added app-level Signals surface config in `config/product-signals.php` so Majlis Ilmu now resolves public and admin properties explicitly instead of choosing the oldest active property implicitly.
- Updated `App\Services\Signals\SignalsTracker` and the tracker Blade include so public pages always use the public property, while admin tracking is off by default and only activates when a dedicated admin property slug is configured and enabled.
- Extended the Signals tracker script in the Commerce package to persist a stable browser anonymous identifier, mirror the browser session identifier into a first-party cookie, and call the identify endpoint for authenticated users. `ProductSignalsService` now reuses those cookies so server-side login/report/notification/search/moderation events stitch onto the same identity/session records.
- Refined product telemetry taxonomy so filter-only API discovery traffic records `listing.filtered` under the `discovery` category, while query-driven interactions remain `search.executed`.
- Expanded auth telemetry coverage with `auth.signup.completed`, `auth.password_reset.completed`, and `auth.email_verified`, and added moderation telemetry events for submit, approve, reject, request changes, cancel, reconsider, revert-to-draft, and re-moderation flows through `ModerationService`.
- Admin telemetry now defaults on with a dedicated `majlis-ilmu-admin` tracked property bootstrapped by migration, so admin traffic no longer depends on manual property creation unless the deployment overrides the slug/domain explicitly.
- Panel Signals surfaces are now generic instead of admin-only: `ahli` gets its own tracked property automatically, and future panel IDs resolve to their own deterministic Signals properties without new hardcoded tracker logic.
- Panel-specific Signals settings now live under a single `product-signals.panels` map, so future panel overrides stay in one place instead of being split between separate `properties` and `surfaces` sections.
- Verification:
  - `php artisan test --compact tests/Feature/SignalsIntegrationTest.php tests/Feature/ProductSignalsTelemetryTest.php tests/Feature/AdminShareAnalyticsPageTest.php tests/Feature/ModerationServiceTest.php` => 38 passed (132 assertions)
  - `vendor/bin/phpstan analyse --ansi app/Services/Signals/ProductSignalsService.php app/Services/Signals/SignalsTracker.php app/Services/Signals/SignalEventRecorder.php app/Services/Signals/AffiliateSignalsBridge.php app/Services/ShareTracking/AffiliatesShareTrackingService.php app/Services/ModerationService.php app/Actions/Fortify/CreateNewUser.php app/Actions/Fortify/ResetUserPassword.php app/Listeners/Auth/RecordVerifiedEmail.php app/Providers/AppServiceProvider.php tests/Feature/SignalsIntegrationTest.php tests/Feature/ProductSignalsTelemetryTest.php tests/Feature/AdminShareAnalyticsPageTest.php tests/Feature/ModerationServiceTest.php` => no errors
  - `/Users/Saiffil/Herd/commerce/vendor/bin/phpstan analyse --ansi packages/signals/src/Actions/ServeSignalsTracker.php` => no errors
  - `vendor/bin/pint --dirty --format agent` in Majlis Ilmu => pass
  - `/Users/Saiffil/Herd/commerce/vendor/bin/pint --dirty --format agent packages/signals/src/Actions/ServeSignalsTracker.php` => pass

# Signals Fail-Open Hardening

- [x] Wrap app-side product Signals ingestion so telemetry failures never break user flows
- [x] Wrap affiliate-to-Signals bridge so attribution/conversion telemetry failures never break share outcomes
- [x] Add focused regressions proving login, report, notification, search, and affiliate outcomes stay successful when Signals throws
- [x] Run focused tests and formatting for the hardening batch

## Review

- Added app-owned wrapper services for Signals package integration at `app/Services/Signals/SignalEventRecorder.php` and `app/Services/Signals/AffiliateSignalsBridge.php` so Majlis Ilmu owns the resilience boundary instead of binding directly to final package classes.
- Hardened `App\Services\Signals\ProductSignalsService` to fail open: Signals ingestion exceptions are now reported/logged and skipped instead of bubbling into auth, report, notification, or search flows.
- Hardened the affiliate-backed share tracking path in `App\Services\ShareTracking\AffiliatesShareTrackingService` so attribution/conversion telemetry failures are reported/logged and do not block share capture or outcome recording.
- Added focused regressions in `tests/Feature/ProductSignalsTelemetryTest.php` and `tests/Feature/SignalsIntegrationTest.php` proving password login, report submission, notification reads, search execution, and affiliate outcomes still succeed when the app-owned Signals boundary throws.
- Verification:
  - `php artisan test --compact tests/Feature/SignalsIntegrationTest.php tests/Feature/ProductSignalsTelemetryTest.php` => 13 passed (50 assertions)
  - `vendor/bin/phpstan analyse --ansi app/Services/Signals/ProductSignalsService.php app/Services/Signals/SignalEventRecorder.php app/Services/Signals/AffiliateSignalsBridge.php app/Services/ShareTracking/AffiliatesShareTrackingService.php tests/Feature/ProductSignalsTelemetryTest.php tests/Feature/SignalsIntegrationTest.php` => no errors
  - `vendor/bin/pint --dirty --format agent` => pass

# Affiliates And Signals Review

- [x] Audit installed AIArmada packages and service-provider wiring for affiliates, signals, and any Filament/admin counterparts
- [x] Trace Dawah Share runtime capture, attribution, and analytics surfaces across public flows and backend dashboards
- [x] Verify live backend visibility with database/admin inspection and summarize coverage gaps, risks, and missing analytics events
- [x] Require and register `aiarmada/signals` plus `aiarmada/filament-signals`
- [x] Bootstrap a default Signals tracked property and inject the tracker on public/auth/admin surfaces
- [x] Bridge affiliate attributions and conversions into Signals events from the local share-tracking adapter
- [x] Add a Filament admin share analytics page with cross-user/provider/link summaries
- [x] Run focused analytics tests, migrations, and formatting

## Review

- `aiarmada/affiliates` is installed and registered in the app, then wrapped by the local `ShareTrackingService` and `ShareTrackingAnalyticsService` adapters. Public Dawah Share routes, attribution middleware, signup attribution, event outcomes, follow outcomes, and saved-search outcomes are wired to that backend.
- `aiarmada/signals` and `aiarmada/filament-signals` are now required from the local Commerce path packages, discovered by Laravel, and registered into the admin panel. Their dashboard page is intentionally disabled so the existing Majlis Ilmu admin dashboard remains the panel home while Signals report pages/resources still register under the `Insights` navigation group.
- Runtime verification in Chrome MCP confirmed the superadmin can see a private user dashboard at `/dashboard/dawah-impact` plus per-link detail pages under `/dashboard/dawah-impact/links/{id}`. Those pages are not part of the Filament admin panel.
- Backend operators now have two admin-facing analytics surfaces: Signals report pages/resources in Filament plus a custom `Share Analytics` Filament page that summarizes provider performance, top sharers, top links, recent visits, and recent outcomes across all affiliates.
- Current live data is minimal: 1 affiliate profile, 1 tracked link, 2 outbound shares, 15 attributed visits, and 0 conversions in `affiliate_conversions`. The visible live report belongs to `superadmin@majlisilmu.my` and currently shows only speaker-share traffic.
- Signals page-view collection is now active through the built-in tracker script on the app/auth layouts and the admin panel head hook, and the local affiliate tracking service now records `affiliate.attributed` and `affiliate.conversion.recorded` events directly into Signals.
- Coverage is still not full product analytics. Current Signals + affiliate coverage now includes anonymous page views plus affiliate attributions/conversions tied to existing Dawah Share outcomes. Broader non-affiliate product telemetry called out in repo notes remains future work: login method, report submission, notification opens/clicks, search execution/result clicks, moderation funnel, AI extraction lifecycle, profile completion, donation intent, and other product telemetry.
- Important semantic caveat: per-link visit counts are attribution-path visits, not only exact target-page views. The live speaker link report included downstream navigations to `/`, `/institusi`, and `/penceramah` under the same shared link because touchpoints stay attached to the landing attribution's `link_id`.
- Verification for this implementation:
  - `php artisan migrate --no-interaction`
  - `php artisan test --compact tests/Feature/SignalsIntegrationTest.php tests/Feature/AdminShareAnalyticsPageTest.php` => 5 passed (18 assertions)
  - `vendor/bin/pint --dirty --format agent`

# Affiliate Dead-Code Cleanup

- [x] Audit remaining direct usages of the legacy `App\Services\DawahShare` layer and old `dawah_share_*` schema/models
- [x] Rewire the remaining runtime callers to `App\Services\ShareTrackingService`
- [x] Remove obsolete local Dawah Share services, models, factories, and legacy user relation if no longer referenced
- [x] Add a safe cleanup migration for stale `dawah_share_*` tables and delete the obsolete create migrations
- [x] Run focused tests, formatting, and targeted static/error checks

## Review

- Rewired the last direct callers in event, saved-search, institution, speaker, and submit-event flows from `App\Services\DawahShare\DawahShareService` to the active affiliate-backed `App\Services\ShareTrackingService`.
- Removed the obsolete local Dawah Share service layer, unused Eloquent models, factories, `DawahShareVisitKind` enum, and the legacy `User::dawahShareLinks()` relation.
- Deleted the old local `dawah_share_*` create migrations and added `database/migrations/2026_03_12_104922_drop_legacy_dawah_share_tables.php` so existing databases safely drop the stale tables while fresh databases never create them.
- Updated `analytic-plan.md` to reference `ShareTrackingAnalyticsService` instead of the removed local analytics service.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DawahShareImpactTest.php` => 24 passed (180 assertions)
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Show.php app/Livewire/Pages/SavedSearches/Index.php app/Http/Controllers/Api/SavedSearchController.php app/Models/User.php tests/Feature/DawahShareImpactTest.php database/migrations/2026_03_12_104922_drop_legacy_dawah_share_tables.php` => no errors
  - editor diagnostics for the changed files => no errors

# Speaker Show Page Relation Fix

# Community Contribution Workflow Completion

- [x] Add authenticated contribution pages for institution and speaker submissions plus generic update suggestions
- [x] Add contributor dashboard page for request history and maintainer approvals
- [x] Extend reporting flow to cover references and expose public report entrypoints
- [x] Wire contribution/report links into existing public pages and navigation
- [x] Run focused Pest coverage, Pint, and narrowed PHPStan for the new workflow

## Community Contribution Workflow Review

- Added authenticated contribution pages for new institution and speaker submissions, plus a generic record update page that either saves immediately for maintainers or creates a pending contribution request for everyone else.
- Added a contribution inbox page where contributors can track their own requests and maintainers can approve or reject pending updates for records they manage.
- Added a public report page, later migrated its shared reporting logic into a dedicated Laravel Action, extended reports to support references, and wired the report flow into the existing API path so duplicate checks and moderation escalation logic stay consistent.
- Linked the new workflow from the app layout, dashboard, and public event/institution/speaker/reference pages so the contribution/report actions are reachable from normal browsing flows.
- Verification:
  - `runTests`: `tests/Feature/ContributionPagesTest.php`, `tests/Feature/EventShowPageTest.php`, `tests/Feature/InstitutionShowPageTest.php`, `tests/Feature/SpeakerFollowTest.php`, `tests/Feature/ReferenceAuthorizationTest.php` => 53 passed
  - `vendor/bin/pint --dirty --format agent` => pass
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Contributions app/Livewire/Pages/Reports/Create.php app/Http/Controllers/Api/ReportController.php app/Models/Reference.php tests/Feature/ContributionPagesTest.php routes/web.php` => no errors

# Event Show Blaze Extraction

- [x] Identify a low-risk repeated presentation block on the public event show page
- [x] Extract the repeated hero details markup into an anonymous Blade component under `resources/views/components`
- [x] Re-run focused event show tests and formatting after the extraction

## Review

- Extracted the duplicated event-show hero badges, title, date, and location chips into `resources/views/components/events/show/hero-details.blade.php`.
- Replaced both inline hero-detail branches in `resources/views/livewire/pages/events/show.blade.php` with the new anonymous component so Blaze has more reusable component surface without changing page behavior.
- Verification:
  - `php artisan test --compact tests/Feature/EventShowPageTest.php` => 18 passed (51 assertions)
  - `vendor/bin/pint --dirty --format agent resources/views/livewire/pages/events/show.blade.php resources/views/components/events/show/hero-details.blade.php` => pass

- [x] Identify the stale speaker-page relation/model names causing the runtime exception
- [x] Update the speaker show page component to the current `EventKeyPerson` API
- [x] Run the focused speaker-page regression test and formatting checks

# Canonical Share Subject Read-Path Follow-up

# Show.php Merge Repair

- [x] Back up the current conflicted `Show.php` working copy before edits
- [x] Compare merge stages and cited commits to isolate unintended reversions
- [x] Restore the intended post-share-tracking behavior while keeping the `keyPeople` refactor
- [x] Run focused formatting and targeted validation on `Show.php`
- [x] Remove the temporary backup after verification

## Show.php Merge Repair Review

- Confirmed commit order: `0bcfc0d` is a direct child of `a9d4dfb`.
- Corrected history finding: `a9d4dfb` explicitly removed event-session support from the event domain. That commit deleted `app/Models/EventSession.php`, removed the `sessions()` relation and related helpers from `app/Models/Event.php`, removed `SessionStatus`, deleted the event-sessions Filament relation manager, removed session handling from the public event show page, and added the migration `2026_03_11_121755_remove_recurrence_and_sessions_from_events_domain.php`.
- Confirmed the `keyPeople` refactor belongs to `0bcfc0d`.
- Fixed the public no-session drift by removing the reintroduced session wiring from `app/Livewire/Pages/Events/Show.php`, `resources/views/livewire/pages/events/show.blade.php`, and `app/Http/Controllers/Public/EventsController.php` while preserving the `keyPeople` refactor and newer non-session share-tracking improvements.
- Temporary backup `app/Livewire/Pages/Events/Show.php.bak` was removed after focused verification passed.
- Focused validation outcome: `php artisan test --compact tests/Feature/EventShowPageTest.php tests/Feature/ParentProgramShowPageTest.php tests/Feature/EventRegistrationSafetyTest.php` passed with 23 tests and 71 assertions.
- Additional root-cause finding: this was not just a missing file accident in the current workspace. The repo history shows `a9d4dfb` deliberately removed `app/Models/EventSession.php`, `SessionStatus`, and the `Event::sessions()` relation, so keeping those references on the public event flow was the actual merge error.

- [x] Make analytics visit/outcome mappers prefer canonical top-level subject IDs over legacy metadata values
- [x] Add a focused regression covering stale metadata beside canonical touchpoint/conversion subject IDs
- [x] Re-run focused Dawah Share tests, Pint, and narrowed PHPStan for the touched files

## Review

- Updated the affiliate-backed analytics adapter so outcome subject IDs prefer canonical conversion `cart_identifier` values, while visit subject IDs read optional touchpoint `cart_identifier` values only when the current schema actually exposes that column.
- Extended the Dawah Share impact regression to prove analytics still resolves the canonical conversion subject ID when stale legacy metadata disagrees.
- Verification:
  - `./vendor/bin/pint --dirty --format agent app/Services/ShareTracking/AffiliatesShareTrackingAnalyticsService.php tests/Feature/DawahShareImpactTest.php` => pass
  - `php artisan test --compact tests/Feature/DawahShareImpactTest.php --filter='impact dashboard top subjects use canonical affiliate subject fields|resolved active attribution prefers canonical affiliate subject fields|new signups are attributed after a shared landing'` => 3 passed (41 assertions)
  - narrowed `phpstan analyse` on the share-tracking service files emitted only environment warnings in this shell session; editor diagnostics for the touched files remained clean

# Dawah Share Chrome E2E Audit

- [x] Reproduce the public share flow in Chrome MCP from speaker, institution, and event pages plus attributed landing links
- [x] Validate UI behavior, share modal/provider links, browser logs, cookies, and tracked-link generation against live database records
- [x] Verify Dawah Impact dashboard totals, per-link detail, provider breakdown, and conversion/outcome visibility for the exercised flows
- [x] Fix any defects found in the browser or tracking pipeline and add focused automated regression coverage
- [x] Re-run focused tests, formatter, and summarize verified end-to-end behavior in this review log

## Review

- Chrome MCP audit covered the authenticated sharer flow on speaker, institution, and event pages plus receiver landings on attributed links.
- Provider redirects generated tracked Majlis Ilmu URLs with `mi_share` and `mi_channel`, and the Dawah Impact dashboard reflected the exercised speaker, institution, and event links.
- The audit exposed a real analytics defect in channel reporting: provider cards could show visits while reporting `0` visitors when older or partially migrated records only carried provider metadata on visit touchpoints.
- Fixed provider-level unique visitor counting in the affiliate-backed analytics service by treating provider-tagged visit identities as the primary source and falling back to attribution cookies only when visit identities are absent.
- Tightened feature coverage with a regression test for missing attribution provider metadata and aligned the share-surface UI test with the actual search-results share implementation.
- Verification:
  - `vendor/bin/pint --dirty --format agent app/Services/ShareTracking/AffiliatesShareTrackingAnalyticsService.php tests/Feature/DawahShareImpactTest.php` => pass
  - `php artisan test --compact tests/Feature/DawahShareImpactTest.php` => 21 passed

# Advanced Events Hierarchy Foundation

- [x] Add event hierarchy primitives to `events` (`event_structure`, `parent_event_id`)
- [x] Add `EventStructure` enum and event parent/child helpers on the model
- [x] Add factory states and unit tests for standalone / parent / child semantics
- [x] Build the dedicated advanced-events builder on top of the new hierarchy foundation
- [x] Add Ahli entrypoint, hierarchy-aware moderation propagation, and dedicated public parent-program page

## Review

- Added a member-only advanced builder that creates one draft parent program and draft child events under it.
- Parent programs are hidden from normal discovery/search while child events remain the public attendance unit.
- Ahli event list now exposes a direct `Create Advanced Program` action.
- Ahli moderation actions on parent programs now propagate to child events.
- Added focused feature coverage for builder access/persistence, public parent page rendering, and hierarchy-aware Ahli approval.

# Dawah Share Outcome Expansion

- [x] Add missing outcome types for event check-ins and event submissions
- [x] Wire attribution recording into check-in, submit-event, and follow actions for all supported followable models
- [x] Extend Dawah Share impact tests to cover the new outcomes and public follow surfaces
- [x] Run focused Pest, PHPStan, and Pint verification for the expanded attribution feature

## Review

- Added `event_checkin` and `event_submission` as first-class Dawah Share outcome types.
- Recorded new outcomes in the event check-in Livewire action and the public submit-event flow.
- Verified follow attribution coverage across institution, speaker, series, and reference public pages.
- Expanded the Dawah Impact dashboard index and per-link detail pages so check-ins and submissions are shown as first-class summary metrics, not only inside total outcomes and breakdown lists.
- Confirmed the public form route `hantar-majlis` maps to `submit-event.create`, so submissions through that page are attributed through the same tracked flow.
- Verification:
  - `runTests`: `tests/Feature/DawahShareImpactTest.php`, `tests/Feature/EventCheckInTest.php` => 12 passed
- `runTests`: `tests/Feature/DawahShareImpactTest.php`, `tests/Feature/EventCheckInTest.php`, `tests/Feature/PublicPagesTest.php`, `tests/Feature/SubmitEventNotesTest.php`, `tests/Feature/SpeakerFollowTest.php`, `tests/Feature/InstitutionShowPageTest.php` => 47 passed
  - `vendor/bin/phpstan analyse --ansi app/Enums/DawahShareOutcomeType.php app/Services/DawahShare/DawahShareAnalyticsService.php app/Livewire/Pages/Events/Show.php tests/Feature/DawahShareImpactTest.php` => no errors
- `vendor/bin/phpstan analyse --ansi app/Services/DawahShare/DawahShareAnalyticsService.php app/Livewire/Pages/Dashboard/DawahImpactIndex.php app/Livewire/Pages/Dashboard/DawahImpactLinkShow.php tests/Feature/DawahShareImpactTest.php` => no errors
  - `vendor/bin/pint --dirty --format agent` => pass

# Affiliates Package Review

- [x] Inspect `/Users/Saiffil/Herd/commerce/packages/affiliates` models, migrations, docs, and services tied to links, touchpoints, attributions, conversions, and daily stats
- [x] Determine whether link destinations and conversion records are generic enough for MajlisIlmu share targets (`event`, `institution`, `speaker`, `saved search`) or still commerce-specific
- [x] Write a concise evidence-based summary with file references and package-extension recommendations

## Review

- Conclusion:
  - The package can capture affiliate/referral visits on arbitrary web URLs through `?aff=` style query parameters and store landing/referrer/UTM context in `affiliate_attributions`.
  - It does not currently provide first-class resource-aware tracking for arbitrary shared content. `affiliate_links` are URL-only, are not wired into runtime click tracking, and there is no `link_id` / `resource_type` / `resource_id` carried into attributions, touchpoints, conversions, or daily stats.
  - Conversion recording is still cart/order-centric. Automatic tracking paths are voucher-to-cart attachment and order-event conversion recording. There is no built-in referred-user signup/account-creation conversion flow.
  - For MajlisIlmu-style dawah metrics, the package would need extensions for resource identity, non-monetary outcome types, per-link tracking, and resource-level aggregation.

# Public Attribution Audit

- [x] Inventory public/shareable surfaces for events, institutions, speakers, saved searches, and other public pages
- [x] Trace signup, account creation, and event registration flows to determine attribution boundaries
- [x] Find existing share UI, analytics, or referral-like tracking and note implementation hook points
- [x] Write review notes confirming whether signup is separate from event registration

## Review

- Public/shareable surfaces currently include the event listing/detail/calendar flows, institution listing/detail, speaker listing/detail, series detail, reference detail, the public submit-event flow, public API event list/show endpoints, and sitemap/about/home pages.
- Explicit share UI exists on event, institution, and speaker detail pages. Series and reference pages are public and followable but do not currently expose a comparable share modal.
- Saved searches are authenticated-only. The event index forwards current query/filter state into the saved-searches page, which then persists those filters per user.
- Signup/account creation is separate from event registration. Account creation is handled through Fortify and Google Socialite. Event registration is a separate public POST on the event page and can create guest registrations without creating a user account.
- I did not find existing UTM/referral/campaign capture or first-class analytics beacons in app code. The closest existing tracking is operational `request_id` metadata on some APIs plus audit logging for registration exports.

# Comprehensive Notification System Todo

## Notification Review Follow-up

- [x] Refactor notification dispatch so only the primary external channel is queued up front and fallback happens only when needed
- [x] Move queued notification title/body rendering onto the notification object so send-time locale and timezone come from the actual notifiable
- [x] Add focused regression coverage for primary-channel sequencing and send-time rendering
- [x] Re-run focused notification tests and PHPStan after the refactor

## Review

- Root cause:
  - the notification engine still treated every enabled channel as an immediate send target, so preferred-channel order behaved like fan-out and fallback could duplicate downstream deliveries
  - notification-center rows still stored fully rendered title/body strings as the delivery source, so delayed notifications were locked to the locale/timezone that existed when the row was created instead of the recipient context at actual send time
- Fix:
  - added `app/Services/Notifications/NotificationMessageRenderer.php`
    - centralizes notification translation rendering and event-timing token formatting
    - renders against the actual notifiable so locale/timezone resolution happens at send time
  - updated `app/Support/Notifications/NotificationDispatchData.php`
    - added an optional `render` blueprint so notifications can persist raw message definitions alongside preview text
  - updated `app/Services/Notifications/EventNotificationService.php`
    - replaced direct translated title/body construction with a `buildDispatchData()` helper that stores render definitions and keeps localized preview strings for pending rows
    - replaced preformatted timing strings with raw event-timing tokens
  - updated `app/Jobs/DispatchNotificationDigests.php`
    - digest notifications now persist render definitions too, while keeping compatibility with direct test invocation of `handle()`
  - updated `app/Notifications/NotificationCenterMessage.php`
    - `toMail()`, `toDatabase()`, `toPush()`, and `toWhatsapp()` now render title/body from the persisted blueprint using the current notifiable instead of the stored preview strings
  - updated `app/Services/Notifications/NotificationEngine.php`
    - split `in_app` from external channels
    - queue `in_app` independently when enabled
    - queue only the primary external channel up front and keep the remaining external channels as a fallback chain instead of immediate sends
  - updated `tests/Feature/NotificationDeliveryFlowTest.php`
    - added regression coverage proving queued notifications re-render from the recipient’s current locale/timezone at send time
    - asserted that the configured fallback sequence queues only the primary external channel initially
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/NotificationDeliveryFlowTest.php` => **13 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(SubmitEventNotificationTest|ModerationServiceTest|EventModerationActionsTest|AhliEventApprovalTest|EventEscalationTest|AccountSettingsPageTest|NotificationInboxPageTest|NotificationCenterApiTest|NotificationPreferencesTest|NotificationDeliveryFlowTest|NotificationCenterTriggersTest)'` => **80 passed**
  - `vendor/bin/phpstan analyse --ansi app/Services/Notifications/NotificationMessageRenderer.php app/Services/Notifications/EventNotificationService.php app/Services/Notifications/NotificationEngine.php app/Notifications/NotificationCenterMessage.php app/Jobs/DispatchNotificationDigests.php app/Support/Notifications/NotificationDispatchData.php tests/Feature/NotificationDeliveryFlowTest.php` => **No errors**

## Institution Dashboard Member Access

- [x] Restrict dashboard member-management actions to scoped institution owner/admin roles
- [x] Prevent owner members from being removed from the institution dashboard
- [x] Update dashboard copy/tests and verify the institution dashboard suite

## Review

- Root cause:
  - the institution dashboard reused the broader `manageMembers` permission path, which was good enough for ordinary role checks but did not express the product rule that this dashboard should be managed only by scoped institution `owner` / `admin`
  - the member table also allowed owner rows to be detached with the same remove action as everyone else
- Fix:
  - updated `app/Livewire/Pages/Dashboard/InstitutionDashboard.php`
    - added a scoped owner/admin membership gate for this dashboard instead of relying on the broader policy shortcut
    - blocked owner-member removal and surfaced a dashboard error flash when attempted
  - updated `resources/views/livewire/pages/dashboard/institution-dashboard.blade.php`
    - changed the access/help copy from `admin` to `owner or admin`
    - added a visible error banner for blocked owner-removal attempts
    - replaced the owner row’s remove action with an `Owner cannot be removed` note
  - updated locale JSON files:
    - `resources/lang/en.json`
    - `resources/lang/ms.json`
    - `resources/lang/ms_MY.json`
    - `resources/lang/zh.json`
    - `resources/lang/ta.json`
    - `resources/lang/jv.json`
  - updated `tests/Feature/DashboardPagesTest.php`
    - existing member-management coverage still proves admins can add/edit/remove ordinary members
    - added regression coverage showing viewers do not get member-management controls and owners remain attached after a removal attempt
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **19 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/InstitutionDashboard.php tests/Feature/DashboardPagesTest.php` => **No errors**
  - `php -r 'foreach ([\"resources/lang/en.json\",\"resources/lang/ms.json\",\"resources/lang/ms_MY.json\",\"resources/lang/zh.json\",\"resources/lang/ta.json\",\"resources/lang/jv.json\"] as $file) { json_decode(file_get_contents($file)); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file.\": \".json_last_error_msg().PHP_EOL); exit(1); } } echo \"locale JSON validation passed\\n\";'` => **locale JSON validation passed**

## Laravel Notification Best-Practice Follow-up

- [x] Render notification title/body/timing per recipient locale and timezone before persistence
- [x] Make push and WhatsApp channels fail loudly when provider config or usable destinations are missing
- [x] Route push and WhatsApp delivery accounting through Laravel `NotificationSent` / `NotificationFailed` listeners instead of in-channel logging
- [x] Add focused regression coverage for recipient localization and custom-channel listener accounting
- [x] Re-run focused notification tests and PHPStan after the refactor

## Laravel Notification Best-Practice Follow-up Review

- Root cause:
  - the notification center had moved closer to Laravel Notifications, but event and digest message text was still being composed in dispatcher context before Laravel could apply each notifiable's locale
  - custom push and WhatsApp channels still treated provider misconfiguration as an early return instead of a real failure, which bypassed Laravel's `NotificationFailed` lifecycle and the app's fallback flow
  - post-send accounting for custom channels still happened inside the channel classes, while mail and database relied on Laravel's notification events, leaving two different delivery-accounting paths
- Fix:
  - updated `app/Services/Notifications/EventNotificationService.php`
    - added a per-user dispatch helper that switches to the recipient locale while building `NotificationDispatchData`
    - removed all shared-recipient formatting shortcuts like `$users->first()` and now format event timing for each actual recipient
  - updated `app/Jobs/DispatchNotificationDigests.php`
    - build digest title/body in the recipient locale instead of job locale
  - updated `app/Models/User.php`
    - added `preferredTimezone()` so notification rendering can consistently use the notification-setting timezone instead of request/auth fallback
  - updated `app/Notifications/NotificationCenterMessage.php`
    - mail `occurred_at` lines are now rendered from the notifiable's own locale and timezone instead of `UserDateTimeFormatter`, which is request-context based
  - added `app/Notifications/Channels/Exceptions/ChannelDeliveryException.php`
    - custom channels now throw structured failures with per-destination result data when provider config is missing or no usable destination can actually receive the message
  - updated `app/Notifications/Channels/PushChannel.php`
  - updated `app/Notifications/Channels/WhatsappChannel.php`
    - removed in-channel delivery logging
    - return structured result payloads on success for Laravel `NotificationSent`
    - throw `ChannelDeliveryException` on full-channel failure so Laravel emits `NotificationFailed`
  - updated `app/Listeners/Notifications/RecordNotificationSent.php`
    - custom push and WhatsApp delivery results now flow through the same `NotificationSent` listener path as mail/database
  - updated `app/Listeners/Notifications/HandleNotificationFailed.php`
    - consume structured custom-channel failures from `NotificationFailed` and queue fallbacks from that listener path
  - updated `app/Services/Notifications/NotificationDeliveryLogger.php`
    - added channel-result logging so listeners can record per-destination success/failure rows without channel-specific side effects
  - updated `app/Services/Notifications/NotificationEngine.php`
    - treat push/WhatsApp as unavailable up front when provider config is absent, so fallback can happen before queueing when the app already knows the provider is not ready
  - updated `tests/Feature/NotificationDeliveryFlowTest.php`
    - added regressions for mixed-recipient locale/timezone rendering, localized digest generation, timezone-correct mail rendering, push result accounting via `NotificationSent`, and WhatsApp config failure logging via `NotificationFailed`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/NotificationDeliveryFlowTest.php` => **12 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/NotificationCenterTriggersTest.php` => **4 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(SubmitEventNotificationTest|ModerationServiceTest|EventModerationActionsTest|AhliEventApprovalTest|EventEscalationTest|AccountSettingsPageTest|NotificationInboxPageTest|NotificationCenterApiTest|NotificationPreferencesTest|NotificationDeliveryFlowTest|NotificationCenterTriggersTest)'` => **79 passed**
  - `vendor/bin/phpstan analyse --ansi app/Services/Notifications/EventNotificationService.php app/Jobs/DispatchNotificationDigests.php app/Notifications/NotificationCenterMessage.php app/Notifications/Channels/PushChannel.php app/Notifications/Channels/WhatsappChannel.php app/Notifications/Channels/Exceptions/ChannelDeliveryException.php app/Listeners/Notifications/RecordNotificationSent.php app/Listeners/Notifications/HandleNotificationFailed.php app/Services/Notifications/NotificationDeliveryLogger.php app/Services/Notifications/NotificationEngine.php app/Models/User.php tests/Feature/NotificationDeliveryFlowTest.php tests/Feature/NotificationCenterTriggersTest.php` => **No errors**

## Laravel Notification Refactor

# Contribution Workflow Foundation

- [x] Add the shared contribution-request model, enums, factory, and migration
- [x] Add reference maintainers and scoped reference member roles/permissions
- [x] Implement the first contribution workflow service for create/update approval and rejection
- [x] Add focused Pest coverage for contribution requests and reference-member authorization
- [x] Run focused verification, Pint, and record review notes

## Review

- Added the initial contribution workflow backend foundation with `ContributionRequest` enums/model/factory, create/update request persistence, approval/rejection/cancellation service logic, and new migrations for `contribution_requests` plus `reference_user`.
- Extended references into the existing member-role architecture with `ReferencePolicy`, `reference_user` maintainers, new reference scoped roles/permissions, and reference-aware member permission checks.
- Verified the first slice with focused Pest coverage for contribution approval behavior and reference authorization, then ran Pint and narrow PHPStan on the touched files.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionWorkflowServiceTest.php tests/Feature/ReferenceAuthorizationTest.php tests/Feature/ScopedMemberRoleSeederTest.php` => 12 passed
  - `vendor/bin/pint --dirty --format agent` => pass
  - `vendor/bin/phpstan analyse --ansi app/Enums/ContributionRequestStatus.php app/Enums/ContributionRequestType.php app/Enums/ContributionSubjectType.php app/Models/ContributionRequest.php app/Policies/ReferencePolicy.php app/Services/ContributionWorkflowService.php app/Support/Authz/MemberRoleScopes.php app/Support/Authz/ScopedMemberRoleSeeder.php app/Support/Authz/MemberPermissionGate.php tests/Feature/ContributionWorkflowServiceTest.php tests/Feature/ReferenceAuthorizationTest.php tests/Feature/ScopedMemberRoleSeederTest.php` => no errors

- [x] Refactor all notification trigger entry points to dispatch native notifications after commit
- [x] Update focused notification tests and verify the refactor with Pest and PHPStan

- [x] Replace the custom notification runtime with Laravel Notification classes, native database notifications, and custom push / WhatsApp channels
- [x] Keep notification settings and destinations, but route delivery selection, locale, and queueing through Laravel notification best practices
- [x] Refactor the inbox page, unread badges, and notification APIs to read from the native `notifications` table instead of `notification_messages`
- [x] Remove or retire custom runtime pieces that bypass Laravel notifications (`NotificationEngine`, direct sender classes, custom inbox storage assumptions)
- [x] Update focused notification tests and verify the refactor end to end

## Laravel Notification Refactor Review

- Root cause:
  - the notification center had drifted into two overlapping systems: a newer Laravel-backed inbox path and a leftover legacy preference/endpoint layer with older moderation notifications still using hard-coded copy and URLs
  - that left dead schema/runtime code in `User`, inconsistent notification authoring patterns, and queued moderation alerts that were not explicitly aligned with Laravel's `afterCommit` guidance
- Fix:
  - removed the retired legacy notification-preference/endpoint runtime:
    - deleted `NotificationEndpoint`, `NotificationPreference`, their factories, and `NotificationPreferenceKey`
    - removed the old relations/helpers from `app/Models/User.php`
    - added `database/migrations/2026_03_09_210000_drop_legacy_notification_tables.php` to drop `notification_endpoints` and `notification_preferences`
  - finished the Laravel-native notification alignment:
    - kept `NotificationCenterMessage` as the single queued notification payload for the notification center
    - kept custom `PushChannel` and `WhatsappChannel` as Laravel notification channels
    - kept the outbox model as `PendingNotification`, but routed dispatch through `Notification::send(...)` and native `database` notifications for the inbox
  - modernized the remaining moderator/admin notifications:
    - updated `app/Notifications/EventSubmittedNotification.php`
    - updated `app/Notifications/EventEscalationNotification.php`
    - both now queue `afterCommit()`, use translated copy from `resources/lang/*/notifications.php`, and use panel route helpers instead of hard-coded `/admin/...` URLs
  - tightened the notification type layer for PHPStan and runtime safety:
    - added missing relation generics and locale-routing cleanup in `app/Models/User.php`
    - normalized pending payload typing in `app/Notifications/NotificationCenterMessage.php`
    - normalized digest-source delivery logging in `app/Services/Notifications/NotificationDeliveryLogger.php`
    - removed a redundant user filter in `app/Services/Notifications/NotificationEngine.php`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(SubmitEventNotificationTest|ModerationServiceTest|EventModerationActionsTest|AhliEventApprovalTest|EventEscalationTest|AccountSettingsPageTest|NotificationInboxPageTest|NotificationCenterApiTest|NotificationPreferencesTest|NotificationDeliveryFlowTest|NotificationCenterTriggersTest)'` => **74 passed**
  - `vendor/bin/phpstan analyse --ansi app/Models/User.php app/Notifications/EventSubmittedNotification.php app/Notifications/EventEscalationNotification.php app/Services/Notifications/NotificationEngine.php app/Services/Notifications/NotificationSettingsManager.php app/Services/Notifications/NotificationDeliveryLogger.php app/Notifications/NotificationCenterMessage.php app/Notifications/Channels/PushChannel.php app/Notifications/Channels/WhatsappChannel.php app/Jobs/DispatchNotificationDigests.php app/Jobs/EscalatePendingEvents.php tests/Feature/SubmitEventNotificationTest.php tests/Feature/ModerationServiceTest.php tests/Feature/EventModerationActionsTest.php tests/Feature/AhliEventApprovalTest.php tests/Feature/EventEscalationTest.php tests/Feature/AccountSettingsPageTest.php tests/Feature/NotificationInboxPageTest.php tests/Feature/NotificationCenterApiTest.php tests/Feature/NotificationPreferencesTest.php tests/Feature/NotificationDeliveryFlowTest.php tests/Feature/NotificationCenterTriggersTest.php` => **No errors**
  - `php artisan migrate --force` => applied `2026_03_09_210000_drop_legacy_notification_tables`

- [x] Replace the digest-only notification persistence with the new settings, rules, destinations, messages, and deliveries schema
- [x] Build the notification catalog, policy resolver, message builder, and delivery senders for in-app, email, push, and WhatsApp
- [x] Integrate notification triggers for follows, saved-search matches, event updates/reminders, registrations, check-ins, and submission workflow
- [x] Add the account-settings notification center, inbox page, navigation links, and authenticated notification APIs
- [x] Update focused tests and verify the new notification flows end to end

## Follow-up Review Fixes

- [x] Remove stale email and WhatsApp destinations when a user changes account contact details
- [x] Make `urgent_override` actually control quiet-hours bypass for urgent notifications
- [x] Mark digest source notifications delivered only after the digest delivery itself succeeds
- [x] Honor configured `fallback_channels` at runtime instead of ignoring them
- [x] Add an atomic delivery-claim step so duplicate jobs do not send the same notification twice

## Final Review Pass

- [x] Restrict inbox visibility to notifications with a real delivered `in_app` delivery and hide digest-source bookkeeping rows
- [x] Preserve explicit fallback-channel settings from the account settings web UI instead of rewriting them from preferred channel order
- [x] Keep unverified replacement email destinations inactive until verification completes
- [x] Fix digest query lookback to honor DST-aware daily and weekly windows instead of assuming a fixed UTC day/week length
- [x] Re-run focused and broader notification verification after the final fixes

## Final Review

- Root cause:
  - the earlier follow-up fixes corrected the main runtime mismatches, but one digest-path edge case still assumed a fixed UTC lookback and could miss the earliest portion of a local digest window across DST changes
  - the inbox surface also needed to distinguish real in-app notifications from delivery-accounting rows, and the account settings web payload needed to preserve explicit fallback-channel choices end to end
- Fix:
  - updated `app/Models/NotificationMessage.php`
    - added `visibleInInbox()` so inbox surfaces only include notifications with a delivered `in_app` delivery and exclude digest-source bookkeeping rows
  - updated `app/Http/Controllers/Api/NotificationMessageController.php`
  - updated `app/Livewire/Pages/Dashboard/NotificationsIndex.php`
  - updated `resources/views/layouts/app.blade.php`
    - switched inbox list, mark-read actions, and unread badge counts onto `visibleInInbox()`
  - updated `app/Livewire/Pages/Dashboard/AccountSettings.php`
  - updated `resources/views/livewire/pages/dashboard/account-settings.blade.php`
    - preserved explicit fallback-channel selections in separate web-form state and exposed trigger-level inheritance/override controls directly in the settings UI
  - updated `app/Services/Notifications/NotificationSettingsManager.php`
    - kept replacement email destinations inactive until re-verification and continued deleting stale email/WhatsApp destinations on contact changes
  - updated `app/Jobs/DispatchNotificationDigests.php`
    - replaced the fixed `subDay()/subWeek()` query lookback with an earliest due-window scan across notification settings so DST fall-back windows are queried completely before per-user policy filtering
  - updated focused tests:
    - `tests/Feature/AccountSettingsPageTest.php`
    - `tests/Feature/NotificationInboxPageTest.php`
    - `tests/Feature/NotificationCenterApiTest.php`
    - `tests/Feature/NotificationPreferencesTest.php`
    - `tests/Feature/NotificationDeliveryFlowTest.php`
    - added coverage for inbox filtering, fallback-channel persistence, inactive unverified email destinations, sibling-destination fallback suppression, and DST-aware digest windows
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(AccountSettingsPageTest|NotificationInboxPageTest|NotificationCenterApiTest|NotificationPreferencesTest|NotificationDeliveryFlowTest)'` => **26 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(AccountSettingsPageTest|DashboardPagesTest|NotificationPreferencesTest|NotificationInboxPageTest|NotificationDeliveryFlowTest|NotificationCenterApiTest|NotificationCenterTriggersTest|SubmitEventNotificationTest)'` => **47 passed**
  - `vendor/bin/phpstan analyse --ansi app/Models/NotificationMessage.php app/Services/Notifications/NotificationSettingsManager.php app/Services/Notifications/NotificationEngine.php app/Jobs/DispatchNotificationDigests.php app/Livewire/Pages/Dashboard/AccountSettings.php app/Livewire/Pages/Dashboard/NotificationsIndex.php app/Http/Controllers/Api/NotificationMessageController.php tests/Feature/AccountSettingsPageTest.php tests/Feature/NotificationInboxPageTest.php tests/Feature/NotificationCenterApiTest.php tests/Feature/NotificationPreferencesTest.php tests/Feature/NotificationDeliveryFlowTest.php` => **No errors**

## Migration Follow-up

- [x] Confirm the notification-center tables were simply missing from the local database
- [x] Run the pending migration instead of adding runtime workarounds
- [x] Verify the new tables exist and the dashboard-related tests still pass

## Review

- Root cause:
  - the `notification_messages` and related notification-center tables had not been migrated into the local Postgres database yet
  - the dashboard crash came from the new unread-count query in `resources/views/layouts/app.blade.php`, but the actual defect was schema state, not application logic
- Fix:
  - ran `php artisan migrate --force`
  - applied migration `2026_03_08_120000_create_notification_center_tables`
  - reverted the temporary missing-table guard code from this turn so the fix stays at the correct layer
- Verification:
  - `php artisan tinker --execute="echo (int) \\Illuminate\\Support\\Facades\\Schema::hasTable('notification_messages'); echo PHP_EOL; echo (int) \\Illuminate\\Support\\Facades\\Schema::hasTable('notification_deliveries'); echo PHP_EOL;"` => `1` / `1`
  - `vendor/bin/pest --parallel --compact --filter='(DashboardPagesTest|NotificationPreferencesTest)'` => **17 passed**

## Account Settings URL

- [x] Make `/tetapan-akaun` the canonical account settings URL
- [x] Remove the legacy `/papan-pemuka/tetapan-akaun`, `/dashboard/account-settings`, and digest-preferences aliases entirely
- [x] Update focused route assertions and verify the dashboard/account settings suite

## Review

- Root cause:
  - the account settings page was still canonically mounted under `/papan-pemuka/tetapan-akaun` even after the dashboard root itself had already been normalized to `/dashboard`
  - the first pass kept the old account-settings and digest-preferences URLs around as redirects, but the user explicitly wanted those old paths removed, not preserved
- Fix:
  - updated `routes/web.php`
    - made `route('dashboard.account-settings')` resolve to `/tetapan-akaun`
    - removed `/papan-pemuka/tetapan-akaun`
    - removed `/dashboard/account-settings`
    - removed `/papan-pemuka/pilihan-digest`
    - removed `/dashboard/digest-preferences`
  - updated tests:
    - `tests/Feature/AccountSettingsPageTest.php`
    - `tests/Feature/DashboardPagesTest.php`
    - added assertions for the canonical route helper path and for the removed legacy URLs returning `404`
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='(AccountSettingsPageTest|DashboardPagesTest)'` => **19 passed**
  - `php -l routes/web.php` => **No syntax errors**

## Review

- Root cause:
  - the app still treated notifications as a narrow digest preference instead of a full cross-channel notification product
  - there was no clean schema for user delivery behavior, no in-app inbox surface, and no single orchestration pipeline for follows, saved searches, tracked events, reminders, registrations, check-ins, and submission workflow updates
- Fix:
  - added a new notification-center domain from scratch:
    - schema/models for `notification_settings`, `notification_rules`, `notification_destinations`, `notification_messages`, and `notification_deliveries`
    - enums for families, triggers, cadence, channels, priority, rule scope, destination status, and delivery status
    - `NotificationCatalog`, `NotificationSettingsManager`, `NotificationEngine`, and delivery senders for `email`, `in_app`, `push`, and `whatsapp`
  - integrated trigger fanout through `EventNotificationService` and the existing event lifecycle surfaces:
    - followed content publication
    - saved-search matches
    - tracked event approval/cancellation/material changes
    - reminder windows (`24h`, `2h`, `check-in open`)
    - registration and check-in confirmations
    - submission workflow transitions
  - added user-facing product surfaces:
    - expanded `AccountSettings` into a real notification center with family controls, trigger overrides, delivery preferences, quiet hours, channel order, fallback strategy, digest timing, and destination status
    - added `dashboard.notifications` inbox page with unread count, filters, mark-read actions, and deep links
    - added authenticated API endpoints for inbox/messages, settings catalog/state, and push-device registration lifecycle
    - added navigation entry and unread badge wiring
  - fixed verification-discovered follow-ups:
    - normalized casted enum/date values at API and job boundaries so the notification slice passes PHPStan
    - changed `NotificationMessageMail` to Laravel markdown mail rendering so the notification email template resolves correctly
    - added a custom `EventSeries` pivot model so UUID-backed `event_series` attach/sync flows work reliably
    - froze time in notification trigger tests where the assertions depend on “future event” semantics
  - updated focused notification coverage:
    - `tests/Feature/AccountSettingsPageTest.php`
    - `tests/Feature/DashboardPagesTest.php`
    - `tests/Feature/NotificationPreferencesTest.php`
    - `tests/Feature/NotificationInboxPageTest.php`
    - `tests/Feature/NotificationDeliveryFlowTest.php`
    - `tests/Feature/NotificationCenterApiTest.php`
    - `tests/Feature/NotificationCenterTriggersTest.php`
  - Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(AccountSettingsPageTest|DashboardPagesTest|NotificationPreferencesTest|NotificationInboxPageTest|NotificationDeliveryFlowTest|NotificationCenterApiTest|NotificationCenterTriggersTest|SubmitEventNotificationTest)'` => **35 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/AccountSettings.php app/Livewire/Pages/Dashboard/NotificationsIndex.php app/Http/Controllers/Api/NotificationDestinationController.php app/Http/Controllers/Api/NotificationMessageController.php app/Http/Controllers/Api/NotificationSettingsController.php app/Services/Notifications/NotificationSettingsManager.php app/Services/Notifications/NotificationEngine.php app/Services/Notifications/EventNotificationService.php app/Jobs/DispatchNotificationDigests.php app/Jobs/DispatchEventReminderNotifications.php app/Jobs/ProcessDeferredNotificationDeliveries.php app/Jobs/ProcessNotificationDelivery.php app/Models/NotificationSetting.php app/Models/NotificationRule.php app/Models/NotificationDestination.php app/Models/NotificationMessage.php app/Models/NotificationDelivery.php app/Support/Notifications/NotificationCatalog.php tests/Feature/AccountSettingsPageTest.php tests/Feature/NotificationInboxPageTest.php tests/Feature/NotificationPreferencesTest.php tests/Feature/NotificationDeliveryFlowTest.php tests/Feature/DashboardPagesTest.php` => **No errors**
  - `vendor/bin/phpstan analyse --ansi app/Models/EventSeries.php app/Models/Event.php app/Models/Series.php app/Mail/NotificationMessageMail.php app/Http/Controllers/Api/NotificationDestinationController.php app/Http/Controllers/Api/NotificationMessageController.php app/Services/Notifications/NotificationSettingsManager.php app/Services/Notifications/NotificationEngine.php app/Services/Notifications/EventNotificationService.php app/Jobs/DispatchNotificationDigests.php app/Support/Notifications/NotificationCatalog.php tests/Feature/NotificationCenterTriggersTest.php tests/Feature/NotificationCenterApiTest.php tests/Feature/NotificationDeliveryFlowTest.php tests/Feature/NotificationInboxPageTest.php tests/Feature/NotificationPreferencesTest.php tests/Feature/AccountSettingsPageTest.php tests/Feature/DashboardPagesTest.php` => **No errors**

## Follow-up Review

- Root cause:
  - the initial notification implementation persisted several delivery controls, but parts of the runtime engine still bypassed those settings or accounted for delivery too early
  - contact-destination sync also assumed append-only behavior, which left outdated email and WhatsApp endpoints active after profile edits
  - queue delivery processing did not claim a row atomically before sending, so concurrent retries could duplicate provider sends
- Fix:
  - updated `app/Services/Notifications/NotificationSettingsManager.php`
    - remove stale email and WhatsApp destinations whenever the account contact value changes, so only the current verified address/number remains active per user/channel
  - updated `app/Services/Notifications/NotificationEngine.php`
    - gate quiet-hours bypass behind the saved `urgent_override` setting instead of only the dispatch payload flag
    - use the configured `fallback_channels` list when building fallback attempts, with ordered-channel fallback only when no explicit list is configured
    - add an atomic claim step before delivery processing so already-claimed rows are skipped safely
    - create source-message delivered rows for digests only after the digest delivery succeeds
    - trigger fallback after failed or skipped sends using the resolved policy instead of hard-coded channel slicing
  - updated `app/Jobs/DispatchNotificationDigests.php`
    - stop pre-marking source messages as delivered before the digest channel send completes
    - only treat existing source deliveries with `Delivered` status as already accounted for during later digest runs
  - updated focused tests:
    - `tests/Feature/AccountSettingsPageTest.php`
    - `tests/Feature/NotificationDeliveryFlowTest.php`
    - added coverage for stale-destination cleanup, urgent quiet-hours deferral, configured fallback-channel usage, duplicate-send prevention, and post-success digest source accounting
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(AccountSettingsPageTest|DashboardPagesTest|NotificationPreferencesTest|NotificationInboxPageTest|NotificationDeliveryFlowTest|NotificationCenterApiTest|NotificationCenterTriggersTest|SubmitEventNotificationTest)'` => **39 passed**
  - `vendor/bin/phpstan analyse --ansi app/Services/Notifications/NotificationSettingsManager.php app/Services/Notifications/NotificationEngine.php app/Jobs/DispatchNotificationDigests.php tests/Feature/AccountSettingsPageTest.php tests/Feature/NotificationDeliveryFlowTest.php` => **No errors**

## Second Follow-up Review

- Root cause:
  - `AccountSettings` reused the same Livewire component state for both profile edits and notification preferences, then wrote the full notification payload during `saveAccountSettings()`, which meant a profile save could silently persist unrelated unsaved notification changes
  - inherited trigger cards in the notification tab were disabled visually, but their displayed cadence/channel values did not stay aligned with live family-level edits until a save/rehydrate cycle
- Fix:
  - updated `app/Livewire/Pages/Dashboard/AccountSettings.php`
    - stopped profile saves from writing the full notification payload
    - added inherited-trigger state syncing so trigger cards that use family defaults reflect live family cadence/channel changes immediately
  - updated `app/Services/Notifications/NotificationSettingsManager.php`
    - added `syncProfileSettings()` so profile edits only update notification timezone mirroring and system destinations, without persisting unrelated notification-preference state
  - updated `tests/Feature/AccountSettingsPageTest.php`
    - added coverage proving profile saves do not commit pending notification edits
    - added coverage proving inherited trigger controls stay aligned with live family changes
    - extended the existing profile-save test to assert notification timezone mirroring still updates correctly
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AccountSettingsPageTest.php` => **6 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(AccountSettingsPageTest|DashboardPagesTest|NotificationPreferencesTest|NotificationInboxPageTest|NotificationDeliveryFlowTest|NotificationCenterApiTest|NotificationCenterTriggersTest|SubmitEventNotificationTest)'` => **47 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/AccountSettings.php app/Services/Notifications/NotificationSettingsManager.php tests/Feature/AccountSettingsPageTest.php` => **No errors**
  - `php -l app/Livewire/Pages/Dashboard/AccountSettings.php` => **No syntax errors**
  - `php -l app/Services/Notifications/NotificationSettingsManager.php` => **No syntax errors**
  - `php -l tests/Feature/AccountSettingsPageTest.php` => **No syntax errors**

# Event Reference Card Width Todo

- [x] Inspect the event show page reference-materials layout and confirm why a single card renders half-width
- [x] Make a single reference-material card span the full content width while preserving the two-column layout for multiple references
- [x] Add focused event-show coverage and run verification

## Review

- Root cause:
  - the reference-materials section on the public event page always used `sm:grid-cols-2`, so a single reference card stayed pinned to a half-width column and left the rest of the row empty
- Fix:
  - updated `resources/views/livewire/pages/events/show.blade.php`
    - switched the reference grid to a conditional class list
    - keep `sm:grid-cols-2` only when the event has more than one reference
    - let the section fall back to a single-column full-width card when there is only one reference item
  - updated `tests/Feature/EventShowPageTest.php`
    - added a focused event-show assertion proving a single reference item renders without the two-column grid class
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventShowPageTest.php` => **13 passed**
  - `php -l tests/Feature/EventShowPageTest.php` => **No syntax errors**

# Digest Preferences Consolidation Todo

- [x] Audit the account settings page, digest preferences page, menu, and routes
- [x] Move the digest preferences form and save flow into the account settings page
- [x] Remove the standalone digest menu/page surface and keep the old digest URLs as redirects
- [x] Update focused tests and verification

## Review

- Root cause:
  - digest preferences had been split into a separate page and separate navigation entry even though it is just another piece of account-level user settings
  - that created two adjacent settings surfaces, duplicated the account-area structure, and forced users to bounce between pages for related profile/preferences work
- Fix:
  - updated `app/Livewire/Pages/Dashboard/AccountSettings.php`
    - merged the digest preference state, hydration, validation, and save action into the account settings Livewire component
    - preserved the existing preference key, frequency rules, channel rules, and storage behavior
  - updated `resources/views/livewire/pages/dashboard/account-settings.blade.php`
    - added a second settings section for digest preferences directly under the account form
    - kept the account header clean and avoided reintroducing the old button-based navigation clutter
  - updated `routes/web.php`
    - replaced the standalone digest page routes with authenticated redirects to account settings for both `/papan-pemuka/pilihan-digest` and `/dashboard/digest-preferences`
  - updated `resources/views/layouts/app.blade.php`
    - removed the separate `Digest Preferences` menu item from both desktop and mobile authenticated navigation
  - removed the old standalone digest page implementation:
    - deleted `app/Livewire/Pages/Dashboard/DigestPreferences.php`
    - deleted `resources/views/livewire/pages/dashboard/digest-preferences.blade.php`
  - updated tests:
    - `tests/Feature/NotificationPreferencesTest.php`
    - `tests/Feature/AccountSettingsPageTest.php`
    - `tests/Feature/DashboardPagesTest.php`
    - moved digest-preference interaction coverage onto `AccountSettings` and changed the old digest route expectation to a redirect
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AccountSettingsPageTest.php` => **3 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **13 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/NotificationPreferencesTest.php` => **7 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/AccountSettings.php tests/Feature/AccountSettingsPageTest.php tests/Feature/DashboardPagesTest.php tests/Feature/NotificationPreferencesTest.php` => **No errors**
  - `php -l app/Livewire/Pages/Dashboard/AccountSettings.php` => **No syntax errors**
  - `php -l routes/web.php` => **No syntax errors**
  - `php -l tests/Feature/AccountSettingsPageTest.php` => **No syntax errors**
  - `php -l tests/Feature/DashboardPagesTest.php` => **No syntax errors**
  - `php -l tests/Feature/NotificationPreferencesTest.php` => **No syntax errors**

# Saved Searches Translation Todo

- [x] Audit the saved-searches page for hard-coded or unnatural copy in the Livewire class and Blade view
- [x] Make saved-search filter labels, values, and notification states fully translatable and human-readable
- [x] Rewrite saved-searches page copy in a more natural product voice and add locale keys across supported languages
- [x] Add focused regression coverage and run verification

## Review

- Root cause:
  - the saved-searches page only translated the obvious headings, while the Livewire class still carried hard-coded flash/error strings, raw notification labels, and partially humanized filter chips
  - the Malay copy itself also read like direct translation in a few places, especially around the create form, empty state, and saved-search cards
  - some chip labels and values, such as event format, languages, dates, and times, still fell back to raw keys or storage values instead of user-facing wording
- Fix:
  - updated `app/Livewire/Pages/SavedSearches/Index.php`
    - centralized notification labels through `notifyOptions()` / `notifyLabel()`
    - localized suggested-name generation, saved-search flash messages, and validation copy through translatable keys
    - expanded captured-filter labels and values so speaker, venue, event format, languages, dates, times, booleans, and enum-backed fields all render as human-readable text
    - added `venue_id` to captured saved-search filters and formatted Malay language labels naturally as `Bahasa Melayu`, `Bahasa Inggeris`, and so on
  - updated `resources/views/livewire/pages/saved-searches/index.blade.php`
    - rewrote the page intro, create section, empty state, and card actions in a more natural product voice
    - switched the notify select and badge to the centralized localized labels instead of raw `title()` formatting
  - updated locale JSON files:
    - `resources/lang/en.json`
    - `resources/lang/ms.json`
    - `resources/lang/ms_MY.json`
    - `resources/lang/zh.json`
    - `resources/lang/ta.json`
    - `resources/lang/jv.json`
    - added the missing saved-search keys and the filter-label keys that the formatter now uses
  - updated `tests/Feature/SavedSearchPageTest.php`
    - aligned the create-form assertions with the new saved-search wording
    - added a focused Malay regression that locks the natural copy, localized notify label, and human-readable filter values
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/SavedSearchPageTest.php` => **19 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/SavedSearches/Index.php tests/Feature/SavedSearchPageTest.php` => **No errors**
  - `php -l app/Livewire/Pages/SavedSearches/Index.php` => **No syntax errors**
  - `php -l resources/views/livewire/pages/saved-searches/index.blade.php` => **No syntax errors**
  - `php -l tests/Feature/SavedSearchPageTest.php` => **No syntax errors**
  - locale JSON validation passed for `en`, `ms`, `ms_MY`, `zh`, `ta`, and `jv`

# Public Calendar Color Emphasis Todo

- [x] Strengthen the speaker and institution public-page calendar card colors to match the dashboard direction
- [x] Add focused public-page assertions so the old pale calendar card classes do not return
- [x] Run focused verification for both page types

## Review

- Root cause:
  - the speaker and institution public-page calendars used their own Alpine card classes instead of the dashboard's shared planner palette
  - those cards still relied on pale `50`-level fills, so the event colors did not stand out enough in the month grid after the dashboard calendar had already been strengthened
- Fix:
  - updated `resources/views/components/pages/speakers/⚡show.blade.php`
    - strengthened the month-calendar event card classes to use clearer `100`-level fills, firmer borders, and small matching shadows
  - updated `resources/views/components/pages/institutions/⚡show.blade.php`
    - applied the same stronger calendar-card treatment so both public page types stay visually aligned
  - updated tests:
    - `tests/Feature/SpeakerShowPageTimingTest.php`
    - `tests/Feature/InstitutionShowPageTest.php`
    - added focused assertions proving the new stronger class string renders and the old pale class string does not
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='uses stronger calendar event colors on speaker page'` => **1 passed**
  - `vendor/bin/pest --parallel --compact --filter='uses stronger calendar event colors on institution page'` => **1 passed**
  - `php -l resources/views/components/pages/speakers/⚡show.blade.php` => **No syntax errors**
  - `php -l resources/views/components/pages/institutions/⚡show.blade.php` => **No syntax errors**
  - note:
    - broader `SpeakerShowPageTimingTest.php` and `InstitutionShowPageTest.php` runs still hit unrelated existing failures outside this color-change scope

# Dashboard Calendar Color Emphasis Todo

- [x] Strengthen the month-calendar card colors so each role stands out more clearly
- [x] Add a focused regression assertion so the old washed-out calendar card class does not return
- [x] Run the focused dashboard verification

## Review

- Root cause:
  - after removing the role text from calendar cards, the remaining role distinction relied only on very soft background tints like `bg-emerald-50/80`
  - those pale fills were not strong enough to help the different planner states stand out at a glance in the month grid
- Fix:
  - updated `app/Livewire/Pages/Dashboard/UserDashboard.php`
    - strengthened the calendar panel classes for `going`, `registered`, `interested`, `saved`, `submitted`, and `checkin`
    - increased the tint from soft `50/80` fills to clearer `100` fills
    - tightened the border colors and added small matching shadows so the cards read more clearly in the grid
  - updated `tests/Feature/DashboardPagesTest.php`
    - added a regression assertion proving the old `bg-emerald-50/80` calendar style no longer renders
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **13 passed**
  - `php -l app/Livewire/Pages/Dashboard/UserDashboard.php` => **No syntax errors**

# Dashboard Calendar Card Simplification Todo

- [x] Remove role/status text from the month-calendar event cards
- [x] Add a focused regression assertion for the removed calendar-card label line
- [x] Run the focused dashboard verification

## Review

- Root cause:
  - the month-calendar cards still rendered a second line made from `entry.role_badges`, which added labels like `Disimpan`, `Daftar Masuk`, and `Berdaftar` inside very small calendar cells
  - those labels were useful elsewhere in the planner, but inside the calendar they made the grid noisier without helping the user scan dates
- Fix:
  - updated `resources/views/livewire/pages/dashboard/user-dashboard.blade.php`
    - removed the second-line role label text from month-calendar event cards
    - kept the event title, card color, and overflow indicator intact
  - updated `tests/Feature/DashboardPagesTest.php`
    - added a focused assertion proving the old `entry.role_badges.map(...)` calendar label line no longer renders
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **13 passed**
  - `php -l resources/views/livewire/pages/dashboard/user-dashboard.blade.php` => **No syntax errors**

# Dashboard Canonical URL Todo

- [x] Make `/dashboard` the canonical named dashboard route
- [x] Keep `/papan-pemuka` as a backward-compatible redirect to the canonical dashboard URL
- [x] Add focused routing coverage and rerun verification

## Review

- Root cause:
  - the route helper and product wording had been aligned toward `Dashboard`, but the canonical named route still pointed to `/papan-pemuka`
  - that left the application with two live dashboard URLs and kept `route('dashboard')` inconsistent with the URL the user explicitly wants
- Fix:
  - updated `routes/web.php`
    - made `/dashboard` the named canonical route for `dashboard`
    - turned `/papan-pemuka` into an authenticated legacy redirect to `/dashboard`
    - kept the rest of the dashboard-area child routes unchanged
  - updated `tests/Feature/DashboardPagesTest.php`
    - added coverage proving `route('dashboard')` resolves to `/dashboard`
    - added coverage proving authenticated requests to `/papan-pemuka` redirect to `/dashboard`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **13 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/Auth/AuthenticationTest.php` => **6 passed**
  - `php -l routes/web.php` => **No syntax errors**

# Dashboard Page Label Alignment Todo

- [x] Inspect the user dashboard page label and align it with the menu wording
- [x] Update focused dashboard assertions so the old `Attendee Planner` / `Perancang Kehadiran` label does not reappear
- [x] Run the focused dashboard verification

## Review

- Root cause:
  - the actual route alias was already `dashboard`, but the dashboard page itself still surfaced a different hero eyebrow label, `Attendee Planner` / `Perancang Kehadiran`
  - that made the main dashboard landing page inconsistent with the navigation wording the user had already standardized to `Dashboard`
- Fix:
  - updated `resources/views/livewire/pages/dashboard/user-dashboard.blade.php`
    - introduced a local dashboard page label that keeps Malay on `Dashboard` instead of `Papan Pemuka`
    - reused that label for both the page `<title>` and the hero eyebrow text
  - updated `tests/Feature/DashboardPagesTest.php`
    - added assertions proving the page now shows `Dashboard`
    - added assertions proving the old `Attendee Planner` / `Perancang Kehadiran` wording is gone
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **12 passed**
  - `php -l resources/views/livewire/pages/dashboard/user-dashboard.blade.php` => **No syntax errors**

# Dashboard Agenda Header Cleanup Todo

- [x] Inspect the dashboard agenda header and remove the redundant title/button
- [x] Update focused dashboard assertions so the removed copy and CTA do not reappear
- [x] Run the focused dashboard verification

## Review

- Root cause:
  - the `Upcoming Agenda` section still carried a second large heading and a `Find more` CTA even though the dashboard already has primary event-discovery navigation elsewhere
  - that made the agenda block feel heavier than the content it introduced, and in Malay it surfaced the extra `Yang perlu anda urus selepas ini` copy the user explicitly does not want
- Fix:
  - updated `resources/views/livewire/pages/dashboard/user-dashboard.blade.php`
    - removed the secondary `What needs your attention next` heading from the agenda section
    - removed the `Find more` / `Lihat lagi` button from that same header
    - kept the `Upcoming Agenda` section label and the agenda content itself intact
  - updated `tests/Feature/DashboardPagesTest.php`
    - added assertions proving both the English and Malay versions of the removed heading/button no longer render
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **12 passed**
  - `php -l resources/views/livewire/pages/dashboard/user-dashboard.blade.php` => **No syntax errors**

# Account Settings Phone Input Todo

- [x] Inspect the current account settings Livewire form and the existing `ysfkaya` phone-input pattern
- [x] Switch the account settings page to render the Filament schema so the phone field uses `ysfkaya/filament-phone-input`
- [x] Update focused coverage and rerun verification

## Review

- Root cause:
  - the account settings Livewire component had already been partially refactored to define a Filament schema with `Ysfkaya\FilamentPhoneInput\Forms\PhoneInput`, but the Blade view still rendered the old raw HTML inputs
  - because the page never rendered `{{ $this->form }}`, the dashboard account settings page could not actually use the same phone-input component already used elsewhere in the app
  - the PHPUnit package caches did not include `ysfkaya/filament-phone-input`, so rendering the package field on a public Livewire page failed in tests even though the package existed in the normal runtime
- Fix:
  - updated `app/Livewire/Pages/Dashboard/AccountSettings.php`
    - removed an unused import
    - added a typed `accountSettingsForm()` helper so the schema can be used without dynamic-property PHPStan errors
    - switched internal form usage from `$this->form` to the typed helper
    - resolved timezone options through the method directly
  - updated `resources/views/livewire/pages/dashboard/account-settings.blade.php`
    - replaced the hand-written `name` / `email` / `phone` / `timezone` inputs with `{{ $this->form }}`
    - kept the surrounding account-settings shell, status notice, verification notice, and save button intact
  - updated `bootstrap/providers.php`
    - explicitly registered `Ysfkaya\FilamentPhoneInput\FilamentPhoneInputServiceProvider::class` so the package view namespace is available on non-panel/public Livewire pages and under the PHPUnit testing bootstrap
  - updated `tests/Feature/AccountSettingsPageTest.php`
    - now asserts the rendered page contains the phone-input wrapper class
    - moved the Livewire state updates and validation assertions to `formData.*`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AccountSettingsPageTest.php` => **3 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/AccountSettings.php tests/Feature/AccountSettingsPageTest.php` => **No errors**

# Account Settings Header Simplification Todo

- [x] Inspect the remaining account settings hero copy and CTA button
- [x] Remove the profile-intro copy and digest-preferences button from the account settings header
- [x] Tighten focused coverage to the header fragment and rerun verification

## Review

- Root cause:
  - after the earlier cleanup, the account settings header still carried extra explanatory copy and a digest-preferences button that the user does not want on that page
  - those elements added noise without helping the core account-editing task
  - the first regression assertion was too broad because `Digest Preferences` still appears elsewhere in the global navigation
- Fix:
  - updated `resources/views/livewire/pages/dashboard/account-settings.blade.php`
    - removed the `Manage your profile details` heading
    - removed the supporting paragraph about dashboard/registration/time display details
    - removed the `Digest Preferences` button from the page header
  - updated `tests/Feature/AccountSettingsPageTest.php`
    - narrowed the assertion to the rendered account-settings header fragment instead of the whole response
    - now verifies the removed copy and button are absent from that header block
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AccountSettingsPageTest.php` => **3 passed**
  - `php -l tests/Feature/AccountSettingsPageTest.php` => **No syntax errors**

# Dashboard Calendar Day Count Cleanup Todo

- [x] Confirm the top-right calendar-day pill is the per-day event count
- [x] Remove the day-count badge from the attendee dashboard calendar
- [x] Update focused coverage and verify the dashboard suite

## Review

- Root cause:
  - each calendar day cell showed a small top-right count badge driven by `cell.entries.length`
  - that duplicated information already implied by the event cards in the same cell and made the calendar feel busier than needed
- Fix:
  - updated `resources/views/livewire/pages/dashboard/user-dashboard.blade.php`
    - removed the per-day event count badge from the calendar cell header
    - kept the day number and the existing event cards / `+lagi` overflow indicator intact
  - updated `tests/Feature/DashboardPagesTest.php`
    - added a focused assertion proving the old `x-show="cell.entries.length > 0"` badge markup is no longer rendered
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **12 passed**
  - `php -l tests/Feature/DashboardPagesTest.php` => **No syntax errors**

# Account Settings Header Cleanup Todo

- [x] Inspect the account settings hero actions
- [x] Remove the redundant `Back to Dashboard` button from the account settings page
- [x] Update focused coverage and verify the page still renders correctly

## Review

- Root cause:
  - the account settings page header included both a useful cross-link to digest preferences and a redundant `Back to Dashboard` CTA
  - that extra dashboard button added noise without providing a necessary account-management action
- Fix:
  - updated `resources/views/livewire/pages/dashboard/account-settings.blade.php`
    - removed the `Back to Dashboard` button from the header action group
  - updated `tests/Feature/AccountSettingsPageTest.php`
    - the authenticated page-render test now asserts the account settings page does not show `Back to Dashboard`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AccountSettingsPageTest.php` => **3 passed**
  - `php -l tests/Feature/AccountSettingsPageTest.php` => **No syntax errors**

# Ahli Draft Submit For Review Todo

- [x] Inspect why submitted draft events have no path to pending from the Ahli edit page
- [x] Add an Ahli `Submit for Review` action for submitted draft events using the existing moderation service
- [x] Add focused Ahli coverage and run verification

## Review

- Root cause:
  - the event state machine already allows `draft -> pending`, but the Ahli edit page only exposed actions for `pending`, `approved`, `rejected`, and `needs_changes`
  - the Ahli page reused the `approve` policy helper, and that policy intentionally returns `false` unless the event is already `pending`
  - as a result, submitted draft events could not be moved into review from the Ahli workspace even though the backend transition already existed
- Fix:
  - updated `app/Filament/Ahli/Resources/Events/Pages/EditEvent.php`
    - added a `Submit for Review` header action
    - wired the action to `ModerationService::submitForModeration()`
    - introduced a dedicated draft-review eligibility helper so submitted draft events use the same scoped `event.approve` permission path without depending on the pending-only `approve` policy
  - updated `tests/Feature/AhliEventApprovalTest.php`
    - added coverage proving institution admins can move a submitted draft event to `pending`
    - added coverage proving the action stays hidden for ordinary draft events that do not come from the submission flow
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AhliEventApprovalTest.php` => **8 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Ahli/Resources/Events/Pages/EditEvent.php tests/Feature/AhliEventApprovalTest.php` => **No errors**

# Ahli View Public New Tab Todo

- [x] Inspect the Ahli event edit-page `View Public Page` header action
- [x] Open the public event page in a new tab from the Ahli edit screen
- [x] Add focused coverage and verify the action markup

## Review

- Root cause:
  - the Ahli event edit page had a `View Public Page` header action with the correct public event URL, but it was missing Filament's new-tab flag
  - that caused the action to replace the Ahli workspace tab instead of preserving the current moderation/edit context
- Fix:
  - updated `app/Filament/Ahli/Resources/Events/Pages/EditEvent.php`
    - added `->openUrlInNewTab()` to the `view_public` header action
  - updated `tests/Feature/AhliPanelInstitutionEditingTest.php`
    - added a focused assertion proving the rendered Ahli edit page contains the public URL and `target="_blank"`
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='opens the ahli view public page action in a new tab'` => **1 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Ahli/Resources/Events/Pages/EditEvent.php tests/Feature/AhliPanelInstitutionEditingTest.php` => **No errors**

# Ahli Submitter WhatsApp Link Todo

- [x] Inspect Ahli submitter-contact renderers and reuse the existing WhatsApp URL resolver
- [x] Link submitter contact labels to WhatsApp when a phone number is available on Ahli review surfaces
- [x] Add focused Ahli coverage and run targeted verification

## Review

- Root cause:
  - Ahli review surfaces showed submitter phone numbers as inert text, which forced moderators to copy numbers manually before contacting the submitter
  - the same submitter-label logic was duplicated across the Ahli dashboard widget, the shared event form submission panel, and the event infolist, making it easy for contact behavior to drift
- Fix:
  - added `app/Support/Events/SubmitterContactPresenter.php`
    - centralizes submitter label assembly across registered-user and guest-submission flows
    - resolves a canonical WhatsApp link by reusing `App\Support\SocialMedia\SocialMediaLinkResolver`
    - supports both `phone` and `whatsapp` submission-contact categories when available
  - updated `app/Filament/Ahli/Widgets/PendingApprovalEventsWidget.php`
    - the `Submitter` column now opens WhatsApp in a new tab when the submitter has a phone number
  - updated `app/Filament/Resources/Events/Schemas/EventForm.php`
    - the read-only submission `Penghantar` placeholder on the event edit form now renders as a WhatsApp link when possible
  - updated `app/Filament/Resources/Events/Schemas/EventInfolist.php`
    - the shared submitter entry now uses the same presenter and WhatsApp-link behavior for consistency on record views
  - updated tests:
    - `tests/Feature/AhliDashboardApprovalWidgetTest.php`
    - `tests/Feature/AhliPanelInstitutionEditingTest.php`
    - added focused assertions proving Ahli dashboard/edit views render `https://wa.me/...` when the submitter has a phone number
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AhliDashboardApprovalWidgetTest.php` => **3 passed**
  - `vendor/bin/pest --parallel --compact --filter='(links submitter phone numbers to whatsapp in the ahli approval widget|renders submitter phone numbers as whatsapp links on the ahli event edit page)'` => **2 passed**
  - `vendor/bin/phpstan analyse --ansi app/Support/Events/SubmitterContactPresenter.php app/Filament/Resources/Events/Schemas/EventForm.php app/Filament/Resources/Events/Schemas/EventInfolist.php app/Filament/Ahli/Widgets/PendingApprovalEventsWidget.php tests/Feature/AhliDashboardApprovalWidgetTest.php tests/Feature/AhliPanelInstitutionEditingTest.php` => **No errors**
  - `php -l app/Support/Events/SubmitterContactPresenter.php && php -l app/Filament/Resources/Events/Schemas/EventForm.php && php -l app/Filament/Resources/Events/Schemas/EventInfolist.php && php -l app/Filament/Ahli/Widgets/PendingApprovalEventsWidget.php` => **No syntax errors**
  - note:
    - a broader filtered run that included the whole `AhliPanelInstitutionEditingTest` file still hit an existing unrelated failure at `tests/Feature/AhliPanelInstitutionEditingTest.php:344` expecting `Edit Institution`; that assertion is outside this WhatsApp change

# Local Conflict Cleanup Todo

- [x] Inspect local task-tracking files for unresolved merge markers
- [x] Merge the nested conflict blocks in `tasks/todo.md` and `tasks/lessons.md`
- [x] Verify conflict markers are gone and record the final state

## Review

- Root cause:
  - a previous local merge left nested conflict markers inside the task-tracking files even though the surrounding feature work had already been recorded
  - the broken state was limited to documentation files, but it still left the workspace inconsistent and made future edits risky
- Fix:
  - merged the duplicated marker blocks at the top of `tasks/todo.md`
  - preserved both timezone follow-up sections in the correct order
  - cleaned `tasks/lessons.md` so the dashboard lessons remain intact and the timezone-input lesson appears only once
- Verification:
  - `rg -n "^(<<<<<<<|=======|>>>>>>>)" -S tasks/todo.md tasks/lessons.md` => no matches
  - `git status --short tasks/todo.md tasks/lessons.md` => both files remain modified locally with the merged content

# Account Settings Menu Todo

- [x] Inspect existing dashboard navigation and user-profile update support
- [x] Add a dedicated authenticated account settings page for profile fields and wire it into the user menu
- [x] Add focused route/update coverage, verify translations, and rerun checks

## Review

- Root cause:
  - the authenticated dashboard menu had planner tools and digest settings, but no first-party place for users to update their own core profile details
  - Fortify in this app currently covers registration and password reset only; there was no existing profile-update action or page for name, email, phone, and timezone
  - that left basic account maintenance effectively hidden from normal users even though the `users` model already supports those fields
- Fix:
  - added `app/Livewire/Pages/Dashboard/AccountSettings.php`
    - dedicated authenticated Livewire page for profile editing
    - keeps the app's existing contact rules: at least one of email or phone, both unique, timezone optional
    - resets `email_verified_at` / `phone_verified_at` when those contact values change
    - updates the session timezone when the user sets a preferred timezone and clears it when the preference is removed
  - added `resources/views/livewire/pages/dashboard/account-settings.blade.php`
    - account settings UI for name, email, phone, and timezone
    - profile/help copy aligned with the dashboard cluster styling
  - updated `routes/web.php`
    - added `/papan-pemuka/tetapan-akaun` named route `dashboard.account-settings`
    - added legacy alias `/dashboard/account-settings`
  - updated `resources/views/layouts/app.blade.php`
    - inserted `Account Settings` into the authenticated desktop and mobile user menus
  - updated locale files:
    - `resources/lang/en.json`
    - `resources/lang/ms.json`
    - `resources/lang/ms_MY.json`
    - `resources/lang/zh.json`
    - `resources/lang/ta.json`
    - `resources/lang/jv.json`
    - added the full phrase set required by the new account settings page and menu
  - updated tests:
    - `tests/Feature/DashboardPagesTest.php`
      - auth coverage now includes `/dashboard/account-settings`
      - dashboard rendering now asserts the new menu item exists
    - added `tests/Feature/AccountSettingsPageTest.php`
      - page render coverage
      - Livewire save coverage for name/email/phone/timezone updates
      - validation coverage for the email-or-phone requirement
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **12 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/AccountSettingsPageTest.php` => **3 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/AccountSettings.php tests/Feature/DashboardPagesTest.php tests/Feature/AccountSettingsPageTest.php` => **No errors**
  - `php -r '$files=["resources/lang/en.json","resources/lang/ms.json","resources/lang/ms_MY.json","resources/lang/zh.json","resources/lang/ta.json","resources/lang/jv.json"]; foreach($files as $file){json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);} echo "locale-json-ok\n";'` => **locale-json-ok**

# Dashboard Menu Label Todo

- [x] Isolate the authenticated menu labels from the shared Malay dashboard translation
- [x] Keep Malay menu wording as `Dashboard` / `Dashboard Institusi` without changing page titles and other dashboard copy
- [x] Add focused regression coverage and rerun verification

## Review

- Root cause:
  - the authenticated menu reused the shared `Dashboard` translation key
  - that key is intentionally translated as `Papan Pemuka` in Malay for page titles and dashboard copy
  - because the menu used the same key directly, the navigation label inherited wording that the product does not want in the menu
- Fix:
  - updated `resources/views/layouts/app.blade.php`
    - added menu-specific labels for Malay locales only
    - kept the shared translation keys untouched for titles and non-menu dashboard UI
    - desktop and mobile menus now render `Dashboard` and `Dashboard Institusi` for `ms` / `ms_MY`
  - updated `tests/Feature/DashboardPagesTest.php`
    - extended the Malay dashboard test to verify the authenticated menu contains `Dashboard` / `Dashboard Institusi`
    - added assertions proving the menu block no longer contains `Papan Pemuka`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **12 passed**

# Dashboard Pagination Scroll Lock Todo

- [x] Reproduce the post-pagination scroll lock on the attendee dashboard
- [x] Replace the stock Livewire paginator scroll handler with a non-smooth offset scroll that respects the sticky header
- [x] Add focused regression coverage and rerun verification

## Review

- Root cause:
  - the earlier pagination fix switched Livewire from scrolling to `body` to scrolling each section selector, which did keep the viewport near the relevant block
  - however, Livewire's stock paginator template still performs that jump with raw `scrollIntoView()`
  - because the app layout uses global `scroll-smooth` on `<html>`, that section jump becomes a smooth animation and can keep asserting the target position after the page changes, which makes manual upward scrolling feel stuck near the top of the hero
- Fix:
  - added `resources/views/vendor/livewire/tailwind.blade.php`
  - added `resources/views/vendor/livewire/simple-tailwind.blade.php`
  - replaced the stock `scrollIntoView()` handler with a custom inline scroll routine that:
    - resolves the intended scroll target exactly as before
    - temporarily disables smooth scrolling on `html` and `body`
    - scrolls immediately with `window.scrollTo(...)`
    - subtracts the sticky-header height for section targets so the section header is not pinned under the nav bar
  - updated `tests/Feature/DashboardPagesTest.php`
    - added a regression assertion proving the dashboard paginator markup now emits the new `window.scrollTo(...)` behavior and no longer renders `scrollIntoView()`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **12 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/UserDashboard.php app/Livewire/Pages/Dashboard/InstitutionDashboard.php tests/Feature/DashboardPagesTest.php` => **No errors**
  - manual browser verification on `/papan-pemuka`
    - pagination still updates the correct page
    - the target section now lands below the sticky header
    - moving back to the top after pagination remains at `scrollY = 0` instead of being pulled back down

# Dashboard Translation And Status Badge Cleanup Todo

- [x] Audit dashboard cluster pages for untranslated labels and redundant approval badges
- [x] Remove attendee-facing approved badges while keeping submission workflow status visible
- [x] Add locale coverage for dashboard, digest preferences, and institution dashboard copy
- [x] Add focused regression coverage and run verification

## Review

- Root cause:
  - the attendee dashboard still rendered event-approval badges in planner cards even when the status was the default `approved`, which added noise outside the submission workflow
  - many dashboard strings were wrapped in `__()` but had no matching locale entries, so the interface silently fell back to English in non-English locales
  - both digest-preferences and institution-dashboard pages had the same translation-gap pattern, and institution status chips still used raw `headline()` formatting
- Fix:
  - updated `resources/views/livewire/pages/dashboard/user-dashboard.blade.php`
    - added a shared translated-status helper for dashboard badges
    - attendee-facing cards now suppress event-status badges when the status is only `approved`
    - workflow status still appears for submitted events, including when they surface in the planner
  - updated `app/Livewire/Pages/Dashboard/UserDashboard.php`
    - calendar-entry status labels now resolve through translated status keys instead of raw `headline()` formatting
    - removed the redundant static Livewire title attribute so the translated Blade title section remains authoritative
  - updated `resources/views/livewire/pages/dashboard/institution-dashboard.blade.php`
    - institution type/status/registration labels now use translated status text instead of English `headline()` output
  - updated `app/Livewire/Pages/Dashboard/DigestPreferences.php` and `app/Livewire/Pages/Dashboard/InstitutionDashboard.php`
    - removed static English title attributes so the translated Blade titles drive the page title
  - updated locale files:
    - `resources/lang/en.json`
    - `resources/lang/ms.json`
    - `resources/lang/ms_MY.json`
    - `resources/lang/zh.json`
    - `resources/lang/ta.json`
    - `resources/lang/jv.json`
    - added the full dashboard-cluster phrase set plus status keys used by planner and institution surfaces
- Regression coverage:
  - updated `tests/Feature/DashboardPagesTest.php`
  - now verifies:
    - the existing English dashboard assertions explicitly run under `locale=en`
    - Malay dashboard copy renders correctly
    - translated approved workflow status appears in the submitted section without leaking into the regular `Going` section
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **10 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/NotificationPreferencesTest.php` => **7 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/UserDashboard.php app/Livewire/Pages/Dashboard/DigestPreferences.php app/Livewire/Pages/Dashboard/InstitutionDashboard.php tests/Feature/DashboardPagesTest.php` => **No errors**
  - locale JSON validation completed for `en`, `ms`, `ms_MY`, `zh`, `ta`, and `jv`

# Dashboard Status Parity And Pagination Todo

- [x] Compare dashboard event badges against `/majlis` card semantics and remove non-submission `approved` noise
- [x] Replace hard preview truncation with named pagination where planner sections can grow
- [x] Add focused regression coverage and run verification

## Review

- Root cause:
  - the featured planner card still had one inconsistent status-badge branch, so the dashboard was not fully aligned with `/majlis` event-card behavior
  - the badge helper on that card was receiving the raw roles array instead of a boolean submission flag, which made the suppression rule brittle
  - planner sections had been capped with preview-style limits before the pagination pass, so growing buckets risked hiding records instead of letting users page through them
- Fix:
  - updated `resources/views/livewire/pages/dashboard/user-dashboard.blade.php`
    - aligned pending workflow copy with the public `/majlis` badge language via `Menunggu Kelulusan`
    - fixed the featured-event badge branch so `approved` only appears for the user’s own submitted events
    - kept the named paginators wired into agenda, planner buckets, submitted events, and check-in history
  - updated `app/Livewire/Pages/Dashboard/UserDashboard.php`
    - made calendar-entry workflow labels use the same pending-status translation path as the dashboard cards
  - updated locale files:
    - `resources/lang/en.json`
    - `resources/lang/zh.json`
    - `resources/lang/ta.json`
    - `resources/lang/jv.json`
    - added `Menunggu Kelulusan` where it was still missing so the shared pending label remains translatable outside Malay locales
  - updated `tests/Feature/DashboardPagesTest.php`
    - added coverage proving named paginator pages show later agenda, going, submitted, and check-in records instead of silently truncating them
- Verification:
  - `php -r '$files=["resources/lang/en.json","resources/lang/ms.json","resources/lang/ms_MY.json","resources/lang/zh.json","resources/lang/ta.json","resources/lang/jv.json"]; foreach($files as $file){json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);} echo "locale-json-ok\n";'` => **locale-json-ok**
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **11 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/UserDashboard.php tests/Feature/DashboardPagesTest.php` => **No errors**

# Dashboard Natural Malay Copy Todo

- [x] Audit dashboard-cluster Malay copy for literal translation wording
- [x] Rewrite the affected `ms` / `ms_MY` strings to sound natural in product UI
- [x] Update focused assertions and rerun verification

## Review

- Root cause:
  - the dashboard structure was already correct, but several Malay strings still read like direct English translations
  - the awkwardness was most obvious in the planner intro, agenda headings, quick-link helper text, and digest-preferences copy
  - this made the dashboard feel translated rather than written for Malay users
- Fix:
  - updated `resources/lang/ms.json`
  - updated `resources/lang/ms_MY.json`
  - rewrote the dashboard-cluster strings into more natural Bahasa Melayu, including:
    - planner intro and hero-support copy
    - agenda and calendar helper text
    - attendance bucket descriptions
    - submitted/check-in guidance copy
    - digest-preferences labels and explanatory text
    - institution-dashboard supporting copy that was still overly literal
  - updated `tests/Feature/DashboardPagesTest.php`
    - changed the Malay assertion tied to the quick-link heading from the old literal phrasing to the new natural wording
- Verification:
  - `php -r '$files=["resources/lang/en.json","resources/lang/ms.json","resources/lang/ms_MY.json","resources/lang/zh.json","resources/lang/ta.json","resources/lang/jv.json"]; foreach($files as $file){json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);} echo "locale-json-ok\n";'` => **locale-json-ok**
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **11 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/UserDashboard.php tests/Feature/DashboardPagesTest.php` => **No errors**

# Dashboard Copy, Membership Access, And Pagination Scroll Todo

- [x] Remove unnecessary institution-focused attendee copy from the main dashboard hero
- [x] Hide the institution dashboard from non-members and forbid direct access
- [x] Fix dashboard pagination so section paging stays at the relevant block instead of jumping to the top
- [x] Run focused verification and document the outcome

## Review

- Root cause:
  - the attendee hero copy still mentioned institution operations even though most users are not institution members
  - the top-level authenticated navigation still exposed `Institution Dashboard` to every signed-in user, and the institution dashboard page returned an empty state instead of rejecting non-members
  - the paginator data itself worked, but Livewire's default pagination view was scrolling to `body`, so clicking a page number looked broken because the user was thrown back to the top of the dashboard
- Fix:
  - updated `resources/views/livewire/pages/dashboard/user-dashboard.blade.php`
    - kept the hero focused on attendee planning and removed the unnecessary institution mention from the translated intro copy
    - changed every dashboard paginator to use section-specific `scrollTo` selectors so pagination returns to the relevant block
  - updated `resources/views/livewire/pages/dashboard/institution-dashboard.blade.php`
    - added section ids for the institution tables and gave their paginators section-specific scroll targets as well
  - updated `resources/views/layouts/app.blade.php`
    - compute institution-dashboard access once from the authenticated user
    - only render the desktop/mobile `Institution Dashboard` menu item for members
  - updated `app/Livewire/Pages/Dashboard/InstitutionDashboard.php`
    - forbid access up front when the signed-in user has no institution memberships
  - updated locale files:
    - `resources/lang/en.json`
    - `resources/lang/ms.json`
    - `resources/lang/ms_MY.json`
    - `resources/lang/zh.json`
    - `resources/lang/ta.json`
    - `resources/lang/jv.json`
    - shortened the main dashboard intro so it no longer references institution operations
  - updated `tests/Feature/DashboardPagesTest.php`
    - added coverage proving non-members do not see `Institution Dashboard` on `/dashboard`
    - added coverage proving direct access to `/dashboard/institutions` is forbidden for non-members
- Verification:
  - `php -r '$files=["resources/lang/en.json","resources/lang/ms.json","resources/lang/ms_MY.json","resources/lang/zh.json","resources/lang/ta.json","resources/lang/jv.json"]; foreach($files as $file){json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);} echo "locale-json-ok\n";'` => **locale-json-ok**
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **12 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/UserDashboard.php app/Livewire/Pages/Dashboard/InstitutionDashboard.php tests/Feature/DashboardPagesTest.php` => **No errors**
  - manual browser verification on `/dashboard`
    - section pagination now updates data and keeps the viewport anchored to the relevant section instead of jumping to the top of the page

# Dashboard Hierarchy Cleanup Todo

- [x] Inspect the dashboard layout to identify repeated summary layers and duplicated agenda content
- [x] Refactor the dashboard Blade so analytics and planner sections each have a distinct job
- [x] Add focused regression coverage for the new hierarchy and run verification

## Review

- Root cause:
  - the dashboard tried to support both analytics and planning, but multiple layers answered the same question
  - the hero already summarized planner state, then a second stat row repeated category counts, and the detailed sections repeated them again
  - `Next up` also reused the first item from `Upcoming Agenda`, so the same event appeared twice in the top planner area
- Fix:
  - updated `resources/views/livewire/pages/dashboard/user-dashboard.blade.php`
    - removed the secondary stat-card strip entirely
    - kept a single analytics layer in the hero and split the old combined `Going + Registered` tile into distinct metrics
    - added a compact quick-jump row so the removed summary space now helps navigation instead of repeating counts
    - changed the agenda panel to render items after the featured `Next up` event, with a dedicated empty state when only the featured event exists
    - added section anchors so the quick-jump pills move users directly to calendar, agenda, and each activity bucket
- Regression coverage:
  - updated `tests/Feature/DashboardPagesTest.php`
  - now verifies:
    - the dashboard renders the new quick-jump row
    - the old `Going + Registered` card is gone
    - when there is only one upcoming planner event, the agenda shows the featured-event empty state instead of repeating the same item
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **9 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/UserDashboard.php /Users/Saiffil/Herd/majlisilmu/tests/Feature/DashboardPagesTest.php` => **No errors**

# Timezone Input Key Safety Follow-up Todo

- [x] Re-review prior timezone hardening changes for domain-field collision risk
- [x] Switch viewer-timezone request-input detection to dedicated `user_timezone` key
- [x] Align middleware regression tests with dedicated request key behavior
- [x] Run available verification checks and document limitations

## Review

- Root issue addressed:
  - Using generic request input key `timezone` for viewer timezone detection can collide with domain payload fields (for example event/session timezone fields), creating unintended viewer-timezone overrides.
- Fix applied:
  - `UserTimezoneResolver` now reads request input/query from `user_timezone` (dedicated viewer context signal) rather than generic `timezone`.
  - Middleware behavior stays the same for persistence (`request_input` remains persistable), but now only for explicit viewer-timezone payloads.
  - Middleware tests updated to exercise `user_timezone` request input precedence and persistence.
- Verification:
  - `php -l app/Support/Timezone/UserTimezoneResolver.php` => no syntax errors
  - `php -l app/Http/Middleware/SetFilamentTimezone.php` => no syntax errors
  - `php -l tests/Feature/SetFilamentTimezoneMiddlewareTest.php` => no syntax errors
  - `node --check resources/js/filament/user-timezone.js` => valid syntax
  - `vendor/bin/pest --parallel --compact tests/Feature/SetFilamentTimezoneMiddlewareTest.php` not runnable: dependencies cannot be fully installed in this container because private Filament package downloads return 403.

# Timezone Detection Hardening Todo

- [x] Review current timezone resolver/middleware/frontend detection flow and identify gaps
- [x] Study filament-timezone-detector package code/docs for transferable patterns
- [x] Implement safe, minimal timezone detection hardening in this codebase
- [x] Add focused timezone middleware regression tests
- [x] Run focused verification and document review notes

## Review

- What was reviewed from `filament-timezone-detector`:
  - middleware pattern that accepts timezone from request input/query in addition to header and cookie
  - frontend pattern that ensures Livewire/fetch requests include `X-Timezone` consistently
- Applied improvements (without adding the package dependency):
  - `UserTimezoneResolver` now considers request input/query `timezone` as a valid detection source (after header, before cookie/session)
  - `SetFilamentTimezone` now treats `request_input` as a persistable user timezone source when authenticated user timezone is null/different
  - Filament timezone script now stores timezone in `sessionStorage`, keeps cookie sync, and injects `X-Timezone` for Livewire v4 intercepted requests and global `fetch()` requests
  - Added middleware regression tests for request-input priority and user-profile persistence from request input
- Verification:
  - `php -l app/Support/Timezone/UserTimezoneResolver.php` => no syntax errors
  - `php -l app/Http/Middleware/SetFilamentTimezone.php` => no syntax errors
  - `php -l tests/Feature/SetFilamentTimezoneMiddlewareTest.php` => no syntax errors
  - `node --check resources/js/filament/user-timezone.js` => valid syntax
  - `vendor/bin/pest --parallel --compact tests/Feature/SetFilamentTimezoneMiddlewareTest.php` could not run because dependencies are not installed (`vendor/bin/pest` missing)

# Authz User Timezone Field Todo

- [x] Inspect the Authz user resource form and confirm the underlying user model supports `timezone`
- [x] Expose `timezone` on the Authz user edit/create form
- [x] Add focused regression coverage and run verification

## Review

- Root cause:
  - the `users` table and `App\Models\User` already support a nullable `timezone` field
  - the custom Authz user resource followed a configurable field list, and `timezone` was not included in that form configuration
  - as a result, admin users could not view or edit timezone from `/admin/authz/users/{id}/edit`
- Fix:
  - updated `config/filament-authz.php`
    - added `timezone` to the configured Authz user form field list
  - updated `app/Filament/Resources/Authz/UserResource.php`
    - added a dedicated `timezone` field renderer
    - implemented it as a searchable `Select` over PHP timezone identifiers instead of a free-text input
    - kept the field optional so users can still fall back to application defaults
- Regression coverage:
  - updated `tests/Feature/AuthzUserResourceTest.php`
  - now verifies:
    - the edit page renders `Timezone`
    - timezone persists through the Authz user edit flow alongside verification timestamps and role syncing
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AuthzUserResourceTest.php` => **4 passed**
  - `vendor/bin/phpstan analyse --ansi config/filament-authz.php app/Filament/Resources/Authz/UserResource.php tests/Feature/AuthzUserResourceTest.php` => **No errors**

# Ahli Escalation Field Visibility Todo

- [x] Hide `Tarikh Eskalasi` from ahli event edit surfaces
- [x] Strip crafted ahli `escalated_at` payloads on save and add focused regression coverage
- [x] Run focused verification and document the outcome

## Review

- Root cause:
  - `Tarikh Eskalasi` lived in the shared event moderation form with no panel-specific visibility rule
  - so it still rendered on ahli event edit pages even though it is moderation metadata, not something ahli users should manage
  - the ahli save path also did not explicitly unset `escalated_at`, so a crafted Livewire payload could still attempt to persist it
- Fix:
  - updated `app/Filament/Resources/Events/Schemas/EventForm.php`
    - `DateTimePicker::make('escalated_at')` is now only visible on the `admin` panel
  - updated `app/Filament/Ahli/Resources/Events/Pages/EditEvent.php`
    - ahli saves now explicitly unset `escalated_at` before persistence
  - this matches the same pattern already used for other admin-only moderation metadata on shared forms
- Regression coverage:
  - extended `tests/Feature/AhliEventFeaturedGuardTest.php`
  - now verifies:
    - ahli edit page hides `is_priority`
    - ahli edit page hides `escalated_at`
    - ahli edit page hides `is_featured`
    - admin edit page still shows all three fields
    - crafted ahli payloads cannot set either `is_featured` or `escalated_at`
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='AhliEventFeaturedGuardTest|FilamentPanelAccessTest'` => **11 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/Events/Schemas/EventForm.php app/Filament/Ahli/Resources/Events/Pages/EditEvent.php tests/Feature/AhliEventFeaturedGuardTest.php` => **No errors**

# Priority Review Visibility And Queue Order Todo

- [x] Inspect where `Priority Review` is rendered and how moderation queue ordering currently works
- [x] Hide `Priority Review` on ahli event surfaces while preserving intended admin moderation controls
- [x] Make the moderation queue surface priority events first and cover the behavior with focused tests
- [x] Run focused verification and document the outcome

## Review

- Root cause:
  - `is_priority` was exposed directly in the shared event moderation form with no panel-aware visibility guard, so it still rendered on ahli event edit pages
  - the admin moderation queue still defaulted to a generic submission-time sort, so urgent `is_priority` events were not explicitly surfaced first
- Fix:
  - updated `app/Filament/Resources/Events/Schemas/EventForm.php`
    - `Priority Review` is now only visible when the current Filament panel is `admin`
    - this hides it from ahli while keeping it available on admin moderation surfaces
  - updated `app/Filament/Pages/ModerationQueue.php`
    - added an explicit `Priority` column so reviewers can see why a row is elevated
    - changed the default queue ordering to:
      - priority events first
      - then earlier `starts_at`
      - then newer `created_at`
    - removed the old plain `created_at desc` default sort from the table definition so urgency ordering now leads the queue
- Regression coverage:
  - updated `tests/Feature/AhliEventFeaturedGuardTest.php`
    - ahli edit page hides `is_priority`
    - admin edit page still shows `is_priority`
  - updated `tests/Feature/ModerationQueueTest.php`
    - queue exposes the new `Priority` column
    - priority records appear before non-priority records in table order
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='AhliEventFeaturedGuardTest|ModerationQueueTest|FilamentPanelAccessTest'` => **20 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/Events/Schemas/EventForm.php app/Filament/Pages/ModerationQueue.php tests/Feature/AhliEventFeaturedGuardTest.php tests/Feature/ModerationQueueTest.php` => **No errors**

# Ahli Featured Toggle Visibility Todo

- [x] Inspect why the `Featured Event` toggle still renders on ahli event edit pages
- [x] Hide the field at the schema/render level so only application admins can see it
- [x] Add focused regression coverage for ahli vs admin visibility
- [x] Run focused verification and document the outcome

## Review

- Root cause:
  - the shared event form/table helpers only checked whether the current user had application-admin access
  - they did not check which Filament panel was rendering the shared schema
  - that meant a super admin using the ahli panel could still see `Featured Event`, even though the product rule is that this control belongs only on global-admin surfaces, not on ahli
- Fix:
  - tightened the shared featured-flag visibility guard in:
    - `app/Filament/Resources/Events/Schemas/EventForm.php`
    - `app/Filament/Resources/Events/Tables/EventsTable.php`
  - the helper now requires both:
    - `hasApplicationAdminAccess()`
    - current panel id is `admin`
  - result:
    - admin panel: global admins still see `Featured Event`
    - ahli panel: `Featured Event` stays hidden for everyone, including super admins
  - the ahli save-path guard in `app/Filament/Ahli/Resources/Events/Pages/EditEvent.php` remains in place as backend protection against crafted payloads
- Regression coverage:
  - extended `tests/Feature/AhliEventFeaturedGuardTest.php`
  - now verifies:
    - ahli members do not see the featured field/column
    - even a super admin does not see the featured field/column on ahli surfaces
    - admin panel super admins still do see the field/column
    - crafted ahli payloads still cannot set `is_featured`
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='AhliEventFeaturedGuardTest|AhliPanelInstitutionEditingTest|FilamentPanelAccessTest'` => **19 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/Events/Schemas/EventForm.php app/Filament/Resources/Events/Tables/EventsTable.php tests/Feature/AhliEventFeaturedGuardTest.php` => **No errors**

# Ahli Event Edit 404 Todo

- [x] Inspect the target event record and ahli resource scoping to find why the edit route returns 404
- [x] Implement the minimal fix so eligible ahli members can open the event edit page
- [x] Add focused regression coverage for the failing ahli event access path
- [x] Run focused verification and document the outcome

## Review

- Root cause:
  - the event `019ca2c8-b868-73b9-8242-c5189901bdba` is a draft event with `organizer_type = App\Models\Speaker`, but it is also linked to an institution via `institution_id`
  - the ahli event resource query only admitted institution members for:
    - institution-organized events, or
    - legacy events where `organizer_type` was null
  - that meant institution-linked speaker events were filtered out at the resource-query layer before the edit page could authorize them, which surfaced as a `404`
  - the same “speaker check returns early, institution fallback never runs” pattern also existed in the event scoped-permission helpers, so even if the query were widened, institution members could still fail update/approve checks on speaker-organized institution events
- Fix:
  - widened ahli event scoping in `app/Filament/Ahli/Resources/Events/EventResource.php`
    - institution members now see any event linked to their institution through `events.institution_id`, including speaker-organized records
  - widened the ahli dashboard approval widget query in `app/Filament/Ahli/Widgets/PendingApprovalEventsWidget.php`
    - pending speaker-organized submissions linked to a member institution now appear for institution members as well
  - centralized scoped permission fallback in `app/Models/Event.php`
    - added `userHasScopedEventPermission(...)`
    - `userCanManage()`, `userCanDelete()`, `userCanView()`, and `userCanApprovePublicSubmission()` now evaluate:
      - event scope
      - organizer institution scope
      - organizer speaker scope
      - linked institution scope
    - this prevents organizer speaker checks from blocking legitimate institution fallback
  - updated `app/Policies/EventPolicy.php`
    - `exportRegistrations()` and `manageMembers()` now reuse the same shared scoped-permission helper so their behavior stays aligned with edit/approve access
- Regression coverage:
  - extended `tests/Feature/AhliPanelInstitutionEditingTest.php`
    - institution admins can open ahli edit pages for speaker-organized events linked to their institution
    - ahli events index now includes institution-linked speaker events for institution members
  - extended `tests/Feature/AhliEventApprovalTest.php`
    - institution admins can approve pending speaker-organized public submissions linked to their institution
  - extended `tests/Feature/AhliDashboardApprovalWidgetTest.php`
    - institution members see pending speaker-organized submissions linked to their institution on the ahli dashboard widget
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='AhliPanelInstitutionEditingTest|AhliEventApprovalTest|AhliDashboardApprovalWidgetTest'` => **16 passed**
  - `vendor/bin/phpstan analyse --ansi app/Models/Event.php app/Policies/EventPolicy.php app/Filament/Ahli/Resources/Events/EventResource.php app/Filament/Ahli/Widgets/PendingApprovalEventsWidget.php tests/Feature/AhliPanelInstitutionEditingTest.php tests/Feature/AhliEventApprovalTest.php tests/Feature/AhliDashboardApprovalWidgetTest.php` => **No errors**

# Public About Page Todo

- [x] Add a dedicated public About page route and Livewire page using the existing site layout
- [x] Write structured multilingual About content for every supported locale
- [x] Design a polished public-facing page with strong narrative, clear action paths, and responsive layout
- [x] Wire public navigation/footer links to the page
- [x] Add focused coverage and run verification

## Review

- Root cause:
  - the public site had no About page at all, even though the footer already implied one with a dead `About Us` placeholder link
  - the provided writeup was long, narrative, and multi-section, so forcing it into JSON translation keys or a one-off static blade would have made future edits messy
- Fix:
  - added a dedicated public About page Livewire component:
    - `app/Livewire/Pages/About/Show.php`
    - `resources/views/livewire/pages/about/show.blade.php`
  - added the primary public route and legacy alias in `routes/web.php`:
    - `/tentang-kami` named `about`
    - `/about` legacy alias
  - initially wired the new About route into the header, mobile menu, and footer
  - revised the navigation placement after product feedback so `About` now lives in the lower navigation/footer only, not the top menu
- Content architecture:
  - created structured locale files instead of bloating JSON dictionaries:
    - `resources/lang/en/about.php`
    - `resources/lang/ms/about.php`
    - `resources/lang/ms_MY/about.php`
    - `resources/lang/zh/about.php`
    - `resources/lang/ta/about.php`
    - `resources/lang/jv/about.php`
  - revised the page copy around the stronger Malaysia-specific product thesis:
    - Malaysia already has a vast masjid/surau network
    - the real gap is discovery, not venue supply
    - missed majlis are often a visibility problem, not a motivation problem
    - Majlis Ilmu acts as the invitation layer that makes knowledge easier to find, trust, and attend
  - after user review, rewrote each locale as native copy rather than direct translation
  - after a second review round, rebuilt the Bahasa Melayu copy again with a more native cadence, less translated sentence structure, and more grounded Malaysia-first phrasing
  - rewrote the other locale files in the same pass so each language reads like locally authored copy rather than a mirrored structure from English
  - localized the Blade-only motivation section and image alt text so the live page no longer falls back to English midway through the scroll
  - kept the page copy modular by section:
    - hero and simple definition
    - scale / proximity stats
    - silent-loss and paradox story blocks
    - root causes
    - life-outcome losses
    - before/after product shifts
    - product proof
    - magnet / distribution / trust impact
    - closing CTA
- Design direction implemented:
  - bold dark hero with gradient and patterned atmosphere
  - two-column introduction with a sharper definition panel
  - stat cards to anchor the Malaysia-scale argument
  - alternating story cards and impact sections instead of a generic mission wall
  - strong closing CTA with direct product actions (`register`, `browse events`, `submit event`)
  - verified clean stacking on mobile and strong hierarchy on desktop
- Regression coverage:
  - added `tests/Feature/AboutPageTest.php`
  - verifies:
    - primary and legacy About routes render
    - the page headline localizes correctly across supported locales
    - the public layout links to the About route
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='AboutPageTest|PublicPagesTest|HomepageRendersContentTest'` => **15 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/About/Show.php routes/web.php tests/Feature/AboutPageTest.php` => **No errors**
  - manual browser render check completed on `https://majlisilmu.test/tentang-kami` for both narrow and desktop layouts

# Ahli Dashboard Approval Queue Todo

- [x] Add a member-scoped approval queue to the ahli dashboard
- [x] Limit the dashboard queue to pending public-submitted events from member institutions and speakers
- [x] Add focused dashboard/widget coverage and run verification

## Review

- Root cause:
  - the ahli dashboard was still the bare Filament dashboard page with no panel widgets, so members had no at-a-glance approval queue even after the ahli approval flow was implemented
- Fix:
  - added an explicit ahli dashboard widget in `app/Filament/Ahli/Widgets/PendingApprovalEventsWidget.php`
  - updated `app/Filament/Pages/AhliDashboard.php` to render only that widget and use a single-column layout
  - updated `app/Providers/Filament/AhliPanelProvider.php` to discover ahli-specific widgets
- Dashboard queue scope:
  - only `pending` events
  - must have a real `EventSubmission` record (`whereHas('submissions')`)
  - only events organized by institutions or speakers the current user is a member of
  - includes legacy institution-linked events where `organizer_type` is null but `institution_id` belongs to a member institution
  - excludes unrelated pending events, in-scope drafts, and pending events without a submission record
- Widget behavior:
  - heading: `Events Needing Approval`
  - shows event title, approval scope, submitter, and submission timestamp
  - includes a `Review` action that links to the ahli event edit page only when the current user can actually approve that event
  - shows an empty state when there is no pending approval work
- Regression coverage:
  - added `tests/Feature/AhliDashboardApprovalWidgetTest.php`
  - verifies the widget table only shows pending public-submitted events from member institutions/speakers
  - verifies the ahli dashboard route renders the queue heading and scoped event title
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='AhliDashboardApprovalWidgetTest|AhliDashboardTest|FilamentPanelAccessTest'` => **11 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Pages/AhliDashboard.php app/Providers/Filament/AhliPanelProvider.php app/Filament/Ahli/Widgets/PendingApprovalEventsWidget.php tests/Feature/AhliDashboardApprovalWidgetTest.php tests/Feature/AhliDashboardTest.php tests/Feature/FilamentPanelAccessTest.php` => **No errors**

# Ahli Access And Featured Guard Todo

- [x] Restrict ahli panel access to actual institution, speaker, or event members
- [x] Hide event featured controls from non-application-admin users on ahli event surfaces
- [x] Enforce the featured guard in the ahli save path against crafted payloads
- [x] Add focused regression coverage and run verification

## Review

- Root cause:
  - `App\Models\User::canAccessPanel()` still returned `true` for every authenticated user on the ahli panel, so non-members could enter that workspace
  - the shared admin event schema/table exposed `is_featured` everywhere, including ahli event edit/list surfaces, and the ahli save path did not strip a crafted `is_featured` payload
  - event-level members were included in the requested ahli access rule, but the ahli event query did not include `event_user` memberships yet
- Fix:
  - added explicit member-based ahli access in `app/Models/User.php`
    - institution member
    - speaker member
    - event member
  - preserved admin panel access through a dedicated `hasApplicationAdminAccess()` helper
  - extended the ahli event workspace query in `app/Filament/Ahli/Resources/Events/EventResource.php` to include `memberEvents()`
  - restricted featured controls to application admins only:
    - `app/Filament/Resources/Events/Schemas/EventForm.php`
    - `app/Filament/Resources/Events/Tables/EventsTable.php`
  - hardened the ahli event save path in `app/Filament/Ahli/Resources/Events/Pages/EditEvent.php` to unset `is_featured` for non-application-admin users even if they submit a crafted Livewire payload
- Regression coverage:
  - updated `tests/Feature/FilamentPanelAccessTest.php`
    - non-members cannot access ahli
    - institution/speaker/event members can
  - updated `tests/Feature/AhliDashboardTest.php`
    - ahli members can open the dashboard
    - non-members are forbidden at HTTP level
  - updated `tests/Feature/AhliPanelInstitutionEditingTest.php`
    - non-member submitters are forbidden from ahli edit pages
    - event members can open scoped event edit pages
    - event-member events appear on the ahli events index
  - added `tests/Feature/AhliEventFeaturedGuardTest.php`
    - ahli edit page hides `is_featured`
    - ahli list hides the `is_featured` column
    - admin edit/list still expose it
    - crafted ahli payloads cannot flip `is_featured`
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='FilamentPanelAccessTest|AhliDashboardTest|AhliPanelInstitutionEditingTest|AhliEventFeaturedGuardTest'` => **19 passed**
  - `vendor/bin/phpstan analyse --ansi app/Models/User.php app/Filament/Ahli/Resources/Events/EventResource.php app/Filament/Ahli/Resources/Events/Pages/EditEvent.php app/Filament/Resources/Events/Schemas/EventForm.php app/Filament/Resources/Events/Tables/EventsTable.php tests/Feature/FilamentPanelAccessTest.php tests/Feature/AhliDashboardTest.php tests/Feature/AhliPanelInstitutionEditingTest.php tests/Feature/AhliEventFeaturedGuardTest.php` => **No errors**

# Ahli Navigation Parent Todo

- [x] Remove the redundant `Ahli Workspace` navigation wrapper from the ahli panel
- [x] Make `Events` the top-level ahli navigation item and align future institution navigation under it
- [x] Add focused coverage for the built ahli navigation structure
- [x] Run verification and document the outcome

## Review

- Root cause:
  - the ahli panel sidebar was still using a resource-level `navigationGroup = 'Ahli Workspace'`, which produced a redundant wrapper label around the actual working navigation item
  - the ahli event resource also inherits from the admin event resource, so removing the local wrapper alone would still leave it grouped under inherited `Content` unless that inheritance is explicitly overridden
  - the ahli institution resource has no index page, so it does not register a sidebar item today; `navigationParentItem` is therefore future-facing metadata, not a currently rendered child menu
- Fix:
  - removed the visible ahli navigation wrapper by setting the ahli event resource navigation group to `null`
  - made the ahli event resource the stable top-level nav anchor with an explicit `Events` label
  - aligned the ahli institution resource metadata so if an index page is introduced later, it will register under `Events` instead of creating a new top-level wrapper
- Updated files:
  - `app/Filament/Ahli/Resources/Events/EventResource.php`
  - `app/Filament/Ahli/Resources/Institutions/InstitutionResource.php`
  - `tests/Feature/AhliNavigationTest.php`
- Regression coverage:
  - added `tests/Feature/AhliNavigationTest.php`
  - verifies the ahli dashboard response no longer shows `Ahli Workspace`
  - verifies the built Filament navigation no longer contains that wrapper and still exposes `Events` as the top-level ahli nav item
  - locks the institution resource parent-item metadata to `Events`
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='AhliNavigationTest|AhliDashboardTest|FilamentPanelAccessTest'` => **6 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Ahli/Resources/Events/EventResource.php app/Filament/Ahli/Resources/Institutions/InstitutionResource.php tests/Feature/AhliNavigationTest.php` => **No errors**

# Admin Dashboard Priorities Todo

- [x] Refocus the admin dashboard so approval work is the primary call to action
- [x] Add informational event summary stats for upcoming, passed, and featured events
- [x] Add focused dashboard coverage and run verification

## Review

- Root cause:
  - the admin dashboard was still using a generic stats widget (`Total Events`, `Active Speakers`, `Institutions`) that did not direct moderators/admins toward the actual approval queue
  - the default lazy widget rendering also meant dashboard call-to-action content could be delayed behind placeholders on first load
- Fix:
  - repurposed `app/Filament/Widgets/StatsOverview.php` into a moderation-first widget with:
    - `Events Needing Approval` -> moderation queue pending tab
    - `Speakers Needing Approval` -> speakers index filtered to `status=pending`
    - `Institutions Needing Approval` -> institutions index filtered to `status=pending`
    - `References Needing Approval` -> references index filtered to `status=pending`
    - `Venues Needing Approval` -> venues index filtered to `status=pending`
  - added `app/Filament/Widgets/EventInventoryOverview.php` for informational counts:
    - `Upcoming Events`
    - `Past Events`
    - `Featured Events`
  - added an admin-only dashboard page override in `app/Filament/Pages/AdminDashboard.php`
    - forces the page title and navigation label to `Dashboard`
    - registered only in `app/Providers/Filament/AdminPanelProvider.php`
    - leaves the rest of the app’s Bahasa Melayu dashboard terminology unchanged
  - both widgets are now eager-rendered (`$isLazy = false`) and explicitly ordered with widget sort values so the approval widget always appears first on `/admin`
- Count model:
  - approval counts use raw pending records
  - information counts use `Event::active()` so they reflect active public-facing events rather than drafts/rejected inventory
- Regression coverage:
  - added `tests/Feature/AdminDashboardTest.php`
  - verifies dashboard ordering, deep-link destinations, and the exact datasets used for the approval/info widgets, including pending references and venues
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AdminDashboardTest.php` => **3 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/AdminResourcesCoverageTest.php` => **2 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Widgets/StatsOverview.php app/Filament/Widgets/EventInventoryOverview.php tests/Feature/AdminDashboardTest.php` => **No errors**
  - `vendor/bin/pest --parallel --compact --filter='AdminDashboardTest|AdminResourcesCoverageTest'` => **5 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Pages/AdminDashboard.php app/Providers/Filament/AdminPanelProvider.php tests/Feature/AdminDashboardTest.php` => **No errors**

# Moderation Queue Reference Status Todo

- [x] Add reference-status visibility to the admin moderation queue event table
- [x] Add focused coverage for pending/verified/missing reference states
- [x] Run verification and document the outcome

## Review

- Root cause:
  - `app/Filament/Pages/ModerationQueue.php` summarized related moderation state for institution, venue, and speakers, but omitted event references even though pending references are also auto-verified during event approval
  - that left moderators without an at-a-glance signal that an event still carried pending references
- Fix:
  - added a `References Status` badge column to the moderation queue event table
  - the column mirrors the existing speaker summary pattern:
    - `None` when the event has no references
    - `All verified` when every attached reference is already verified
    - `N unverified` when one or more attached references are still pending or otherwise not verified
  - eager-loaded `references` in the moderation queue query to avoid N+1 lookups
  - added a tooltip listing the non-verified reference titles so moderators can see which references still need review
- Regression coverage:
  - extended `tests/Feature/ModerationQueueTest.php`
  - covers pending reference visibility, all-verified references, and no-reference events
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/ModerationQueueTest.php` => **7 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Pages/ModerationQueue.php tests/Feature/ModerationQueueTest.php` => **No errors**

# Moderation Queue Venue Column Todo

- [x] Add a visible venue-name column to the admin moderation queue event table
- [x] Extend queue coverage so the venue column and value are asserted
- [x] Run focused verification and document the outcome

## Review

- Root cause:
  - the moderation queue exposed `Institution` plus venue verification state, but not the actual venue name
  - that made rows harder to review because some events are effectively anchored by their venue as much as their institution
- Fix:
  - added a dedicated `Venue` text column to `app/Filament/Pages/ModerationQueue.php`
  - kept it adjacent to `Institution` so the event’s organizing entity and location entity are both visible at a glance
  - added a tooltip with the full venue name and a `None` placeholder when an event has no linked venue
- Regression coverage:
  - extended `tests/Feature/ModerationQueueTest.php`
  - now asserts the queue renders the `Venue` column and shows the event’s venue name
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/ModerationQueueTest.php` => **7 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Pages/ModerationQueue.php tests/Feature/ModerationQueueTest.php` => **No errors**

# Moderation Queue Status Column Todo

- [x] Remove the redundant event status column from the admin moderation queue table
- [x] Add focused table-shape coverage so the status column stays absent
- [x] Run focused verification and document the outcome

## Review

- Root cause:
  - the moderation queue is a dedicated review surface, not a general event listing
  - keeping the event `status` column there was redundant because the active queue context already defines the review state, especially on the default pending tab
- Fix:
  - removed the `status` text column from `app/Filament/Pages/ModerationQueue.php`
  - retained the surrounding moderation-signal columns (`Institution Status`, `Venue Status`, `Speakers Status`, `References Status`) that still add distinct review value
- Regression coverage:
  - extended `tests/Feature/ModerationQueueTest.php`
  - added a Livewire table assertion proving the moderation queue no longer exposes the `status` column while still exposing `venue.name`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/ModerationQueueTest.php` => **8 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Pages/ModerationQueue.php tests/Feature/ModerationQueueTest.php` => **No errors**

# Authz User View Todo

- [x] Add the missing user relationships needed for follow/member/activity visibility on the admin user view
- [x] Create a read-only Authz user view page that surfaces activity, registrations, follows, submissions, memberships, and saved searches
- [x] Make the admin authz users index open the view page when a row is clicked
- [x] Add focused coverage and run verification

## Review

- Root cause:
  - the Authz users resource was still edit-first, so row clicks went straight into a mutable form and there was no admin-readable profile page for user activity and relationships
  - the underlying user model already contained most of the needed event/activity relations, but follow visibility was incomplete because institution/reference follow relations were missing on `User`
- Fix:
  - added `followingInstitutions()` and `followingReferences()` to `app/Models/User.php`
  - added a dedicated read-only admin user page in `app/Filament/Resources/Authz/UserResource/Pages/ViewUser.php`
  - implemented a custom page view at `resources/views/filament/resources/authz/user-resource/pages/view-user.blade.php` that shows:
    - interested, going, saved, and checked-in events
    - event registrations
    - followed institutions, speakers, and references
    - submitted events
    - institution, speaker, and event memberships
    - saved searches
  - updated `app/Filament/Resources/Authz/UserResource.php` so:
    - the resource exposes a real `view` page
    - authz `view` permission is honored
    - the users index includes a `view` action URL, which makes row clicks resolve to the view page instead of edit
- Implementation note:
  - an initial schema/infolist attempt hit a Filament renderer recursion edge case, so the final solution uses a custom Blade page for reliability while keeping the resource/page routing standard
- Regression coverage:
  - extended `tests/Feature/AuthzUserResourceTest.php`
  - covers:
    - index `view` action URL on authz users
    - end-to-end visibility of the requested user activity/follow/member/search datasets on the view page
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AuthzUserResourceTest.php` => **4 passed**
  - `vendor/bin/phpstan analyse --ansi app/Models/User.php app/Filament/Resources/Authz/UserResource.php app/Filament/Resources/Authz/UserResource/Pages/ViewUser.php tests/Feature/AuthzUserResourceTest.php` => **No errors**

# Ahli Dashboard Label Todo

- [x] Add an ahli-panel dashboard page override with explicit `Dashboard` title/label
- [x] Register that page in the ahli panel provider
- [x] Add focused coverage and run verification

## Review

- Root cause:
  - the admin panel already had an explicit dashboard override, but the ahli panel still registered Filament’s default dashboard page, so Malay locale users still saw `Papan Pemuka`
- Fix:
  - added `app/Filament/Pages/AhliDashboard.php`
  - registered it in `app/Providers/Filament/AhliPanelProvider.php`
  - the ahli panel home now explicitly uses `Dashboard` for both the page title and navigation label
- Regression coverage:
  - added `tests/Feature/AhliDashboardTest.php`
  - verifies the ahli dashboard renders `Dashboard` and not `Papan pemuka`
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='AhliDashboardTest|FilamentPanelAccessTest'` => **5 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Pages/AhliDashboard.php app/Providers/Filament/AhliPanelProvider.php tests/Feature/AhliDashboardTest.php` => **No errors**

# Event Submission Display Todo

- [x] Remove the editable submissions relation manager from the admin event resource
- [x] Replace editable submitter fields on the event moderation tab with read-only submission details
- [x] Add focused regression coverage and run verification

## Review

- Root cause:
  - the admin event page modeled `submissions` as a normal relation manager, which exposed add/edit/delete affordances for data that should only exist as a factual result of a real submission flow
  - the moderation form also exposed `submitter_id` as an editable select, which let admins rewrite submission provenance manually
- Fix:
  - removed the submissions relation manager from `app/Filament/Resources/Events/EventResource.php`
  - deleted the unused `app/Filament/Resources/Events/RelationManagers/EventSubmissionsRelationManager.php`
  - replaced the editable `submitter_id` field in `app/Filament/Resources/Events/Schemas/EventForm.php` with a read-only `Submission` section on the moderation tab
  - the new section shows:
    - source (`Pengguna berdaftar` vs `Penghantaran awam`)
    - submitted timestamp
    - submitter identity
    - submission notes
  - the section only renders when a real `EventSubmission` record exists
- Regression coverage:
  - added `tests/Feature/EventSubmissionDisplayTest.php`
  - asserts the relation manager list no longer contains the submissions relation
  - asserts `submitter_id` is no longer a form field
  - asserts the new submission components render the expected read-only state
  - asserts the submission section stays hidden when no real submission record exists
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSubmissionDisplayTest.php` => **3 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/AdminEventsResourceTest.php` => **7 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/Events/EventResource.php app/Filament/Resources/Events/Schemas/EventForm.php tests/Feature/EventSubmissionDisplayTest.php` => **No errors**

# Event Registrations Relation Visibility Todo

- [x] Inspect the event resource registrations relation manager and the canonical registration-required flag
- [x] Hide the registrations relation manager for events that do not require registration
- [x] Add focused regression coverage and run verification

## Review

- Root cause:
  - `RegistrationsRelationManager` was always registered and relied only on generic authorization, so the tab still appeared on admin event pages even when the event did not require registration
- Fix:
  - added `canViewForRecord(...)` to `app/Filament/Resources/Events/RelationManagers/RegistrationsRelationManager.php`
  - the relation now loads the event settings and only renders when `settings.registration_required` is true
  - this uses the same flag already used by the public event page and registration controller logic
- Regression coverage:
  - `tests/Feature/EventRegistrationsRelationVisibilityTest.php`
  - asserts the edit page relation-manager list includes registrations when required and omits it when not required
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventRegistrationsRelationVisibilityTest.php` => **2 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/Events/RelationManagers/RegistrationsRelationManager.php tests/Feature/EventRegistrationsRelationVisibilityTest.php` => **No errors**

# Scoped Event Approval Todo

- [x] Inspect the current ahli-panel event approval gate for public submissions tied to institutions/speakers
- [x] Allow responsible institution/speaker members to approve qualifying public-submitted events
- [x] Add focused regression coverage and run verification

## Review

- Root cause:
  - the ahli event edit page exposed no approval action at all; only the admin moderation pages could approve pending events
  - speaker members also lacked a path through `EventPolicy::update` for pending public submissions, so they could be blocked from the ahli edit page before any approval action could render
- Approval model implemented:
  - added a scoped `event.approve` permission to institution `owner`/`admin`/`editor` roles and speaker `owner`/`admin`/`editor` roles in `app/Support/Authz/ScopedMemberRoleSeeder.php`
  - added `Event::userCanApprovePublicSubmission(User $user)` to enforce:
    - event must be `pending`
    - event must come from the public submission flow (`submissions()->exists()`)
    - user must be a responsible institution/speaker member with the scoped `event.approve` permission
  - added `EventPolicy::approve(...)` and allowed `EventPolicy::update(...)` to pass for this exact approval path, so qualified speaker approvers can reach the ahli edit page
  - added an `Approve` header action to `app/Filament/Ahli/Resources/Events/Pages/EditEvent.php`
- Verification:
  - new focused tests in `tests/Feature/AhliEventApprovalTest.php`
  - `vendor/bin/pest --parallel --compact tests/Feature/AhliEventApprovalTest.php` => **5 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/AhliPanelInstitutionEditingTest.php` => **6 passed**
  - `vendor/bin/phpstan analyse --ansi app/Support/Authz/ScopedMemberRoleSeeder.php app/Models/Event.php app/Policies/EventPolicy.php app/Filament/Ahli/Resources/Events/Pages/EditEvent.php tests/Feature/AhliEventApprovalTest.php tests/Feature/AhliPanelInstitutionEditingTest.php` => **No errors**

# Public Submission Toggle Live Refresh Todo

- [x] Identify why the edit-page toggle stays disabled after eligible members are added in the relation manager
- [x] Trigger a parent-page toggle refresh when institution/speaker member relations change
- [x] Add focused regression coverage and run verification

## Review

- Root cause:
  - the edit pages computed toggle availability from the current record, but member relation changes happened in a child Livewire relation manager
  - after add/remove/role updates, the parent page never refreshed its record or toggle field, so the disabled state stayed stale until a full browser refresh
- Fix:
  - added a shared UI event constant in `app/Support/Submission/PublicSubmissionUiEvents.php`
  - `EditInstitution` and `EditSpeaker` now listen for the refresh event and call `refresh()` on the record plus `refreshFormData(['allow_public_event_submission'])`
  - institution/speaker member relation managers now dispatch that event to their parent page after add-member, manage-roles, and remove-member actions
- Updated files:
  - `app/Filament/Resources/Institutions/Pages/EditInstitution.php`
  - `app/Filament/Resources/Speakers/Pages/EditSpeaker.php`
  - `app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php`
  - `app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php`
  - `app/Support/Submission/PublicSubmissionUiEvents.php`
- Added regression coverage:
  - `tests/Feature/PublicSubmissionLockActionsTest.php`
  - new assertions prove the same mounted edit page flips from disabled to enabled after the refresh event, without remounting
  - also verifies the institution member relation manager dispatches the targeted refresh event after adding an eligible member
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicSubmissionLockActionsTest.php` => **9 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/MemberRoleModalHydrationTest.php` => **3 passed**
  - `vendor/bin/phpstan analyse --ansi app/Support/Submission/PublicSubmissionUiEvents.php app/Filament/Resources/Institutions/Pages/EditInstitution.php app/Filament/Resources/Speakers/Pages/EditSpeaker.php app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php tests/Feature/PublicSubmissionLockActionsTest.php tests/Feature/MemberRoleModalHydrationTest.php` => **No errors**

# Member Role Modal Hydration Todo

- [x] Reproduce the missing scoped-role preselection in institution and speaker member relation actions
- [x] Fix relation manager role hydration so existing roles are preselected in the modal
- [x] Add focused regression coverage and run verification

## Review

- Root cause:
  - `manageRoles` used `mountUsing(function (Action $action, ...) { $action->fillForm(...) })`.
  - In Filament actions, `fillForm()` configures the mount callback; it does not fill the already-mounted schema from inside `mountUsing()`.
  - That left the modal `role_ids` state empty even when the member already had scoped roles.
- Fixed relation managers:
  - `app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php`
  - `app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php`
  - `app/Filament/Resources/Events/RelationManagers/EventUsersRelationManager.php`
- Change made:
  - replaced the custom `mountUsing(...)` callback with `fillForm(fn (User $record): array => [...])`
  - existing scoped role IDs are now resolved before the action modal opens, so the multi-select preselects correctly
- Added regression test:
  - `tests/Feature/MemberRoleModalHydrationTest.php`
  - covers institution, speaker, and event member role modals
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/MemberRoleModalHydrationTest.php` => **3 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionMembersRelationLinkTest.php` => **1 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php app/Filament/Resources/Events/RelationManagers/EventUsersRelationManager.php tests/Feature/MemberRoleModalHydrationTest.php tests/Feature/InstitutionMembersRelationLinkTest.php` => **No errors**

# Filament Subdomain Panels Todo

- [x] Move admin panel from `/admin` path to `admin` subdomain routing (with fallback to `/admin` when no subdomain is configured)
- [x] Enforce admin panel login to users with at least one global-scope role assignment only
- [x] Add `ahli` panel on its own subdomain with authenticated-access for all users
- [x] Add focused panel access tests and run verification

## Review

- Added panel-domain config:
  - `config/filament-panels.php` with `FILAMENT_ADMIN_DOMAIN` and `FILAMENT_AHLI_DOMAIN`.
  - `.env.example` now includes both keys.
- Added shared domain resolver trait:
  - `app/Providers/Filament/Concerns/ResolvesPanelDomain.php`
  - Auto-derives subdomains from `APP_URL` host (e.g. `majlisilmu.test` -> `admin.majlisilmu.test`, `ahli.majlisilmu.test`) unless explicitly configured.
  - Keeps testing safe by disabling domain binding when running unit tests.
- Updated admin panel:
  - `app/Providers/Filament/AdminPanelProvider.php`
  - binds to `admin` subdomain and uses root path on that domain.
  - falls back to `/admin` path if no domain is configured.
- Added ahli panel:
  - `app/Providers/Filament/AhliPanelProvider.php`
  - registered in `bootstrap/providers.php`
  - binds to `ahli` subdomain (fallback `/ahli`) with login enabled for authenticated users.
- Updated panel access policy:
  - `app/Models/User.php`
  - `admin` panel now requires at least one global role assignment (`authz_scope_id = null`).
  - `ahli` panel allows all authenticated users.
- Added focused tests:
  - `tests/Feature/FilamentPanelAccessTest.php`
  - covers `ahli` open access, `admin` deny without global role, `admin` deny scoped-only role, and `admin` allow global role.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/FilamentPanelAccessTest.php` => **4 passed**
  - `vendor/bin/phpstan analyse --ansi app/Providers/Filament/AdminPanelProvider.php app/Providers/Filament/AhliPanelProvider.php app/Providers/Filament/Concerns/ResolvesPanelDomain.php app/Models/User.php tests/Feature/FilamentPanelAccessTest.php` => **No errors**
  - `php artisan route:list` confirms panel routes on:
    - `admin.majlisilmu.test`
    - `ahli.majlisilmu.test`

# Ahli Institution Editing Todo

- [x] Add member-scoped institution/event edit resources under `ahli` panel
- [x] Restrict ahli resource queries to institutions the current user is a member of
- [x] Add frontend institution-dashboard links to ahli edit routes when policy permits
- [x] Add focused feature coverage for ahli edit-route access and dashboard link visibility
- [x] Run focused verification and document review

## Review

- Added member editing resources in ahli panel:
  - `app/Filament/Ahli/Resources/Institutions/InstitutionResource.php`
  - `app/Filament/Ahli/Resources/Institutions/Pages/EditInstitution.php`
  - `app/Filament/Ahli/Resources/Events/EventResource.php`
  - `app/Filament/Ahli/Resources/Events/Pages/EditEvent.php`
- Wired ahli panel to discover these resources:
  - `app/Providers/Filament/AhliPanelProvider.php`
- Access model for ahli resources:
  - institution/event records are constrained to current user’s institution memberships in resource `getEloquentQuery()`.
  - edit permissions still enforced by existing policies (`institution.update`, `event.update`).
- Added frontend edit links from institution dashboard:
  - `resources/views/livewire/pages/dashboard/institution-dashboard.blade.php`
  - `Edit Institution` and `Edit in Ahli Panel` links render only when current user can update the respective record.
- Added tests:
  - `tests/Feature/AhliPanelInstitutionEditingTest.php`
  - Covers allowed member edit access, denied non-member access, and conditional dashboard link visibility.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AhliPanelInstitutionEditingTest.php` => **3 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **6 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Ahli/Resources/Institutions/InstitutionResource.php app/Filament/Ahli/Resources/Events/EventResource.php app/Filament/Ahli/Resources/Events/Pages/EditEvent.php tests/Feature/AhliPanelInstitutionEditingTest.php` => **No errors**
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `php artisan route:list` confirms ahli edit endpoints:
    - `ahli.majlisilmu.test/institutions/{record}/edit`
    - `ahli.majlisilmu.test/events/{record}/edit`

# Event Check-In Todo

- [x] Add first-class event check-in data model (`event_checkins`) for both open and registration-required events
- [x] Implement event detail check-in action with eligibility rules (status, time window, auth, registration requirement when needed)
- [x] Add check-in UI affordance on event detail (desktop + mobile action bars)
- [x] Show personal check-in history on user dashboard
- [x] Add focused feature tests for open-event check-in and registration-required check-in
- [x] Run focused Pest verification and document review

## Review

- Added check-in persistence:
  - `database/migrations/2026_03_04_000100_create_event_checkins_table.php`
  - `app/Models/EventCheckin.php`
  - `database/factories/EventCheckinFactory.php`
- Added relations + cleanup hooks:
  - `Event::checkins()`, `Registration::checkins()`, `EventSession::checkins()`, `User::eventCheckins()`
  - deletion cleanup in `Event`, `Registration`, and `User`; `EventSession` now nulls `event_session_id` in check-ins on delete
- Implemented event detail check-in flow in `app/Livewire/Pages/Events/Show.php`:
  - one-button check-in for both open and registration-required events
  - open events use `method=self_reported`
  - registration-required events enforce existing non-cancelled registration and use `method=registered_self_checkin`
  - eligibility window: `2h before start` until `8h after start`
  - duplicate prevention per `(event_id, user_id)`
  - integrated with existing auth + notification UX
- Added check-in actions on desktop/mobile bars in `resources/views/livewire/pages/events/show.blade.php`.
- Added user-facing history in dashboard:
  - `app/Livewire/Pages/Dashboard/UserDashboard.php`
  - `resources/views/livewire/pages/dashboard/user-dashboard.blade.php`
- Added focused tests in `tests/Feature/EventCheckInTest.php`.
- Fixed Carbon type handling in event page checks (`CarbonImmutable` compatibility) by switching strict checks to `CarbonInterface` in `Show.php`.
- Verification:
  - `vendor/bin/pest --parallel --compact --filter=EventCheckIn` => **5 passed**
  - `vendor/bin/pest --parallel --compact --filter="EventShowPageTest|EventEngagementLivewireTest|NotificationPreferencesTest"` => **21 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Show.php` => **No errors**
  - `vendor/bin/phpstan analyse --ansi` => reports **pre-existing unrelated project errors** outside this change set.

# Institution Dashboard Scope Clarity Todo

- [x] Align institution dashboard stats with all-record visibility while preserving explicit public-active counts
- [x] Add clear UI scope labels for events and registrations (`Visible on public page` vs `Internal only`)
- [x] Add regression coverage for mixed public/internal institution events on dashboard
- [x] Run focused verification and document review

## Review

- Updated dashboard stats logic in `app/Livewire/Pages/Dashboard/InstitutionDashboard.php`:
  - `events_count` now represents all institution events for members/admins.
  - added `public_events_count` and `internal_events_count`.
  - registrations now include `public_registrations_count` and `internal_registrations_count`.
- Updated dashboard UI in `resources/views/livewire/pages/dashboard/institution-dashboard.blade.php`:
  - stats cards now show `All`, plus explicit split (`Public active` vs `Internal / hidden`).
  - added a scope notice clarifying dashboard vs public-page visibility rules.
  - event table now includes `Visibility` and `Public Page` columns with badges (`Visible on public page` / `Internal only`).
  - registration cards now show event scope label with same visibility language.
- Added regression in `tests/Feature/DashboardPagesTest.php`:
  - `it('clearly distinguishes public and internal institution data for members', ...)`
  - covers mixed public/internal events + registrations and scope labels.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **6 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/InstitutionDashboard.php tests/Feature/DashboardPagesTest.php` => **No errors**
  - `php artisan view:cache` => **Blade templates cached successfully**

# Event Cancelled Status Todo

- [x] Add `cancelled` as a first-class `EventStatus` with moderation transition support
- [x] Keep cancelled events publicly visible in listing/search/API (without flipping `is_active` false)
- [x] Surface explicit cancelled badges in public event cards/details and admin status badges
- [x] Add a moderation action for admins to cancel approved/pending events
- [x] Notify affected users (`going`, `interested`, `saved`) when an event is cancelled
- [x] Add focused feature coverage for visibility and cancellation notifications
- [x] Run focused Pest + PHPStan verification and document review

## Review

- Added `cancelled` status implementation:
  - `app/States/EventStatus/Cancelled.php`
  - `app/States/EventStatus/Transitions/CancelEvent.php`
  - `app/Notifications/EventCancelledNotification.php`
  - transition wiring in `app/States/EventStatus/EventStatus.php` (pending/approved -> cancelled, cancelled -> pending/draft)
- Added moderation support:
  - `ModerationService::cancel(...)`
  - new `cancel` action in `app/Filament/Resources/Events/Pages/ViewEvent.php`
  - new `cancel` row action + cancelled badge mapping in `app/Filament/Pages/ModerationQueue.php`
- Updated visibility/indexing/listing behavior:
  - `Event::PUBLIC_STATUSES` now includes `cancelled` and is used by `active()` and `shouldBeSearchable()`
  - updated status filters in `EventSearchService`, API event listing/show, saved/interested list endpoints, and public calendar access
  - updated event-show access logic to allow cancelled events while keeping engagement actions disabled for cancelled status
- Updated UI status presentation:
  - `/events` cards now show `Dibatalkan` badge
  - event detail hero and status banners now show clear cancellation state
  - admin table/infolist/form/reviews mappings include cancelled status color/label
- Added/updated tests:
  - `tests/Feature/ModerationServiceTest.php`
  - `tests/Feature/EventModerationActionsTest.php`
  - `tests/Feature/EventVisibilityAccessTest.php`
  - `tests/Feature/Api/EventApiContractTest.php`
  - `tests/Feature/EventSearchTest.php`
  - `tests/Feature/Api/EventSaveTest.php`
  - `tests/Feature/EventPledgeTest.php`
  - `tests/Unit/EventTest.php`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/ModerationServiceTest.php` => **16 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventModerationActionsTest.php` => **15 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventVisibilityAccessTest.php` => **13 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/Api/EventApiContractTest.php` => **5 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/Api/EventSaveTest.php` => **8 passed**
  - `vendor/bin/pest --parallel --compact tests/Unit/EventTest.php` => **2 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="(shows approved, pending, and cancelled public events|shows pending events on detail page with warning banner|shows cancelled events on detail page with cancellation banner)"` => **3 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventPledgeTest.php --filter="(can list all interested events for authenticated user|interested events index still includes cancelled events)"` => **2 passed**
  - `vendor/bin/phpstan analyse --ansi ...[changed files]` => **No errors**
  - Note: broader suites still contain pre-existing unrelated failures (for example assertions expecting legacy UI strings in `EventPledgeTest`, `EventSearchTest`, and location text in `InstitutionShowPageTest`).

# Speaker Missing Submission Todo

- [x] Add a direct speaker-submission flow on `/penceramah` using shared `SpeakerFormSchema`
- [x] Add clear CTA(s) on speaker index and empty-state so users can submit missing speakers
- [x] Keep new submissions moderated (`pending`) with explicit UI notice
- [x] Add feature test coverage for submission from speaker index
- [x] Run focused verification

## Review

- Updated `resources/views/components/pages/speakers/⚡index.blade.php`:
  - integrated Filament form handling (`HasForms`, `InteractsWithForms`, `WithFileUploads`) into the speaker index Volt page
  - reused `SpeakerFormSchema::createOptionForm()` + `SpeakerFormSchema::createOptionUsing(...)` for submission parity with submit-event
  - added “Tak jumpa penceramah?” CTA in hero and empty-state
  - added inline submission panel with moderation message and submit/cancel actions
  - invalidates `submit_speakers` cache key after successful submission
  - sends success notification clarifying admin approval flow (`pending` status)
- Updated `tests/Feature/SpeakerIndexTest.php`:
  - added CTA visibility assertion and regression test that submits a missing speaker from `pages.speakers.index` and asserts persisted `pending` + `is_active=true`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/SpeakerIndexTest.php` => **7 passed**
  - `vendor/bin/phpstan analyse --ansi app/Forms/SpeakerFormSchema.php` => **No errors**
  - `php artisan view:cache` => **Blade templates cached successfully**

# Event Advanced Filter Taxonomy Parity Todo

- [x] Rename `Topic/Tajuk` advanced filter label to `Bidang Ilmu`
- [x] Add `Sumber Rujukan Utama`, `Tema / Isu`, and `Rujukan Kitab/Buku` controls to `/majlis` advanced filters
- [x] Wire new filters through URL state normalization and search filter payload
- [x] Implement DB + Typesense filter handling for new fields
- [x] Extend searchable index payload/schema for new tag/reference filter fields
- [x] Extend saved-search capture/labels/API validation for new filters
- [x] Add focused tests and run verification
- [x] Verify field visibility in browser via Chrome MCP

## Review

- Updated `app/Livewire/Pages/Events/Index.php`:
  - relabeled `topic_ids` field to `Bidang Ilmu`
  - added new advanced filter state and controls for:
    - `source_tag_ids` (`Sumber Rujukan Utama`)
    - `issue_tag_ids` (`Tema / Isu`)
    - `reference_ids` (`Rujukan Kitab/Buku`)
  - added computed option sources for disciplines/sources/issues/references
  - propagated new keys through default/normalized/hydrated filter state and `searchFilters`
- Updated `resources/views/livewire/pages/events/index.blade.php`:
  - wired selected labels/chips for the new filters
  - included new keys in saved-search query payload and active-filter counter
- Updated `app/Services/EventSearchService.php`:
  - Typesense filter parts now include `source_tag_ids`, `issue_tag_ids`, `reference_ids`
  - DB filters now include tag-type constrained `whereHas` for source/issue and `references` relation filter
- Updated search index payload:
  - `app/Models/Event.php` now includes `source_tag_ids`, `issue_tag_ids`, `reference_ids` in `toSearchableArray()`
  - `config/scout.php` schema now includes these fields as facet-enabled `string[]`
- Updated saved-search pipeline:
  - `app/Livewire/Pages/SavedSearches/Index.php` captures and renders human-readable labels for the new filter keys
  - `app/Http/Controllers/Api/SavedSearchController.php` validates the new filter arrays
- Added/updated tests:
  - `tests/Feature/EventSearchTest.php`
  - `tests/Feature/EventSearchTypesenseFilterTest.php`
  - `tests/Feature/SavedSearchPageTest.php`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="(filters events by selected bidang ilmu|filters events by selected kategori \(domain tags\)|filters events by selected sumber rujukan utama tags|filters events by selected tema isu tags|filters events by selected rujukan kitab buku)"` => **5 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTypesenseFilterTest.php` => **5 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/SavedSearchPageTest.php --filter="(renders source issue and reference chips using human-readable values|renders domain kategori chip using human-readable tag name|prefills domain kategori filters from query string when saving searches)"` => **3 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php app/Services/EventSearchService.php app/Models/Event.php resources/views/livewire/pages/events/index.blade.php app/Livewire/Pages/SavedSearches/Index.php app/Http/Controllers/Api/SavedSearchController.php tests/Feature/EventSearchTest.php tests/Feature/EventSearchTypesenseFilterTest.php tests/Feature/SavedSearchPageTest.php` => **No errors**
  - Chrome MCP verification on `https://majlisilmu.test/majlis` confirms advanced filter now shows:
    - `Bidang Ilmu`
    - `Sumber Rujukan Utama`
    - `Tema / Isu`
    - `Rujukan Kitab/Buku`

# Event Filter Kategori Domain Todo

- [x] Add URL-backed `domain_tag_ids` state to `/majlis` advanced filters with category options sourced from Domain tags
- [x] Include `domain_tag_ids` in event filtering pipeline (Livewire filter normalization + EventSearchService DB + Typesense filter parts)
- [x] Extend searchable payload/schema for Typesense (`domain_tag_ids`) so non-DB path supports the new filter
- [x] Include `domain_tag_ids` in saved-search capture payload from `/majlis`
- [x] Add/adjust feature tests for category filtering and Typesense filter-part generation
- [x] Run focused Pest verification and document review notes

## Review

- Added `domain_tag_ids` as a URL-backed advanced filter in `app/Livewire/Pages/Events/Index.php` with options sourced from Domain tags (`TagType::Domain`) and wired through filter normalization/state hydration.
- Added filter UI chip/saved-search payload support in `resources/views/livewire/pages/events/index.blade.php`, including readable selected category chips in active filters.
- Extended search filtering:
  - `app/Services/EventSearchService.php` now applies `domain_tag_ids` in both DB query (`whereHas(tags.type = domain)`) and Typesense filter parts (`domain_tag_ids:[...]`).
  - `app/Models/Event.php` searchable array now includes `domain_tag_ids` from attached Domain tags.
  - `config/scout.php` Typesense schema now defines `domain_tag_ids` as `string[]` facet field.
- Extended saved-search handling:
  - `app/Livewire/Pages/SavedSearches/Index.php` now captures `domain_tag_ids` from request and renders chip label/value as `Kategori: <Tag Name>`.
  - `app/Http/Controllers/Api/SavedSearchController.php` now validates `filters.domain_tag_ids`.
- Added regressions:
  - `tests/Feature/EventSearchTest.php`: domain category filtering behavior.
  - `tests/Feature/EventSearchTypesenseFilterTest.php`: Typesense filter part generation for `domain_tag_ids`.
  - `tests/Feature/SavedSearchPageTest.php`: query prefill + readable captured chip for kategori domain tag.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="(filters events by selected topics|filters events by selected kategori \(domain tags\)|shows title-only search placeholder on events index)"` => **3 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTypesenseFilterTest.php` => **4 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/SavedSearchPageTest.php` => **10 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php app/Services/EventSearchService.php app/Models/Event.php app/Livewire/Pages/SavedSearches/Index.php app/Http/Controllers/Api/SavedSearchController.php tests/Feature/EventSearchTest.php tests/Feature/EventSearchTypesenseFilterTest.php tests/Feature/SavedSearchPageTest.php` => **No errors**

# Institution Location Scope Todo

- [x] Add URL-backed location scope state (`state_id`, `district_id`, `subdistrict_id`) to `/institusi` page component
- [x] Add cascading option providers and dependent resets for Negeri -> Daerah -> Daerah Kecil / Bandar / Mukim
- [x] Apply institution query filtering through `address` relation based on selected location scope
- [x] Render location scope filter controls in institution index UI
- [x] Add feature tests for state/district/subdistrict scoped filtering on institution index
- [x] Run focused Pest verification and record review notes

## Review

- Updated `resources/views/components/pages/institutions/⚡index.blade.php` to support URL-backed location scopes:
  - added `state_id`, `district_id`, `subdistrict_id` properties
  - added cascading options for `Negeri -> Daerah -> Daerah Kecil / Bandar / Mukim`
  - added dependent resets (`updatedStateId`, `updatedDistrictId`) and `clearFilters()`
  - applied address-based query scoping in both direct and fuzzy search paths
- Added UI controls under search input for the three location scopes with disabled child selects until parent is chosen.
- Added regressions in `tests/Feature/InstitutionIndexTest.php`:
  - scope control visibility assertion
  - end-to-end filtering assertions for state, district, and subdistrict URL query scopes
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php` => **8 passed**
  - `php artisan view:cache` => **Blade templates cached successfully**

---

# Institution Multi-Word Search Precision Todo

- [x] Add regression coverage for strict multi-word institution search (`masjid besi`) to avoid broad token-only matches
- [x] Tighten public institution direct-search logic so multi-word queries require all tokens (while preserving fuzzy typo fallback when no direct result)
- [x] Run focused verification and document review notes

## Review

- Updated `resources/views/components/pages/institutions/⚡index.blade.php`:
  - direct search now uses normalized phrase/wildcard matching and, for multi-word input, requires all meaningful tokens to match (name/description) instead of broad token `OR` expansion.
  - this prevents queries like `masjid besi` from returning institutions that only match `masjid` or only `besi`.
- Added regression in `tests/Feature/InstitutionIndexTest.php`:
  - `it('keeps multi-word search strict to phrase-relevant institutions', ...)`
  - asserts `masjid+besi` returns `Masjid Besi Putrajaya` and excludes partial token-only matches.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php` => **12 passed**
  - `php artisan view:cache` => **Blade templates cached successfully**
  - `vendor/bin/phpstan analyse --ansi tests/Feature/InstitutionIndexTest.php` => **No files found to analyse** (project PHPStan config does not target this path directly)

---

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

# Shared Member Scope Roles Todo

- [x] Introduce shared member-role scopes for `institution`, `speaker`, and `event`
- [x] Enforce record membership before allowing scoped permissions
- [x] Move member relation-manager role assignment/read to shared scopes (no per-record custom role creation)
- [x] Seed scoped member role templates only into shared scopes
- [x] Update event/institution/speaker/registration policy checks to use membership + shared scope permissions
- [x] Update event status transition notifications to target institution members with permissions
- [x] Add focused tests for shared-scope seeding and membership enforcement
- [x] Run focused Pest and PHPStan verification

## Review

- Added shared member-scope registry:
  - `app/Support/Authz/MemberRoleScopes.php`
- Added membership-aware permission gate:
  - `app/Support/Authz/MemberPermissionGate.php`
- Updated scoped member seeding to shared scopes only:
  - `app/Support/Authz/ScopedMemberRoleSeeder.php`
  - `database/seeders/ScopedMemberRolesSeeder.php`
- Enabled central authz role management again (for editing shared scoped role permissions from Filament):
  - `app/Providers/Filament/AdminPanelProvider.php`
  - `config/filament-authz.php`
- Dev cleanup performed on local DB:
  - removed `600` legacy per-record scoped roles
  - kept `5` global roles + `12` shared scoped member roles (`3` scopes x `4` templates)
- Refactored member role management UIs to shared scopes:
  - `app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php`
  - `app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php`
  - `app/Filament/Resources/Events/RelationManagers/EventUsersRelationManager.php`
- Updated authorization flow:
  - `app/Policies/InstitutionPolicy.php`
  - `app/Policies/SpeakerPolicy.php`
  - `app/Policies/EventPolicy.php`
  - `app/Policies/RegistrationPolicy.php`
  - `app/Models/Event.php`
- Updated moderation transition recipients:
  - `app/States/EventStatus/Transitions/ApproveEvent.php`
  - `app/States/EventStatus/Transitions/RejectEvent.php`
  - `app/States/EventStatus/Transitions/RequestChanges.php`
  - `app/States/EventStatus/Transitions/CancelEvent.php`
- Added/updated tests:
  - `tests/Feature/ScopedMemberRoleSeederTest.php`
  - `tests/Feature/MemberPermissionGateTest.php`
  - `tests/Feature/EventPolicyTest.php`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/ScopedMemberRoleSeederTest.php` => **4 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/MemberPermissionGateTest.php` => **2 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventPolicyTest.php` => **30 passed**
  - `vendor/bin/phpstan analyse --ansi [changed authz/policy/model/relation-manager files]` => **No errors**

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

---

# Majlis Save Search Visibility Regression (False Filter Values)

- [x] Compare git-history save-search behavior with current active-filter rendering
- [x] Restore active-filter visibility for explicit false-valued filters (`0` / `No ...`)
- [x] Add regression test to prevent save/search bar disappearing for false filters
- [x] Run focused verification

## Review

- Root cause identified in `resources/views/livewire/pages/events/index.blade.php`:
  - Active-filter detection used `value !== false`, so explicit `false` selections (for example `has_event_url=0`) were treated as inactive.
  - That hid the active-filter row where `Save This Search` is rendered.
- Fix:
  - Replaced generic value checks with explicit boolean activity checks (`... !== null`, `filled(...)`, `count(...) > 0`).
  - `hasActiveFilters` now derives from `activeFilterCount > 0`.
- Added test in `tests/Feature/EventSearchTest.php`:
  - `treats explicit false URL filter as active and keeps active filter chips visible`.
  - Confirms `No Event URL` chip and `Clear All Filters` appear for `has_event_url=0`.

- Verification:
  - `php -l resources/views/livewire/pages/events/index.blade.php` => **No syntax errors**
  - `php -l tests/Feature/EventSearchTest.php` => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="treats explicit false URL filter as active and keeps active filter chips visible|ignores prayer time filter when timing mode is absolute|filters absolute timing events by selected start time range|applies absolute time range to event start time only, not event end time"` => **4 passed (15 assertions)**
  - `vendor/bin/phpstan analyse --ansi resources/views/livewire/pages/events/index.blade.php tests/Feature/EventSearchTest.php` => **No errors**

---

# Majlis Save Search Guest CTA (Chrome MCP Verification)

- [x] Reproduce missing save option in browser and confirm auth/filter state
- [x] Show a visible guest CTA for save-search when active filters are present
- [x] Add regression assertion and verify in browser

## Review

- Browser diagnosis (Chrome MCP):
  - On `/majlis`, no active filters and guest state resulted in no active-filter row.
  - On `/majlis?has_event_url=0`, active-filter row showed but had no save action because save link was `@auth` only.
- UI update in `resources/views/livewire/pages/events/index.blade.php`:
  - Kept authenticated save link as-is.
  - Added guest fallback CTA in active-filter bar:
    - `Log Masuk · Save This Search` linking to login route.
- Test update in `tests/Feature/EventSearchTest.php`:
  - Extended false-filter visibility test to assert `Save This Search` is visible.
- Verification:
  - `php -l resources/views/livewire/pages/events/index.blade.php` => **No syntax errors**
  - `php -l tests/Feature/EventSearchTest.php` => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="treats explicit false URL filter as active and keeps active filter chips visible"` => **1 passed (6 assertions)**
  - Chrome MCP `evaluate_script` on `https://majlisilmu.test/majlis?has_event_url=0` => `hasSaveSearch: true`, `hasLoginSaveCta: true`

---

# Majlis GPS UX Cleanup (Internal Coordinates + Radius + State Label)

- [x] Remove user-facing latitude/longitude (and manual radius) from saved-search create form
- [x] Show `Radius (km)` in `/majlis` advanced filters only when location is active (GPS detected/used)
- [x] Render human-readable geo labels in saved-search captured filters (fix `State Id: 2489`)
- [x] Add focused regression tests and run verification

## Review

- Saved search form UI cleanup:
  - Removed user-facing `Radius (km)`, `Latitude`, and `Longitude` inputs from `resources/views/livewire/pages/saved-searches/index.blade.php`.
  - Kept GPS fields internal in `app/Livewire/Pages/SavedSearches/Index.php` and only prefill `radius_km` when both `lat` and `lng` are present.
- Captured filters readability:
  - Added filter formatting helpers in `app/Livewire/Pages/SavedSearches/Index.php` to map location IDs to human-readable names (`State`, `District`, `Daerah Kecil / Bandar / Mukim`, institution, venue) before rendering chips.
  - Updated both captured-filter chip blocks in `resources/views/livewire/pages/saved-searches/index.blade.php` to use formatted label/value output.
- `/majlis` radius behavior:
  - Replaced `radius_km` select with an incrementable integer input inside the advanced `Location` section in `app/Livewire/Pages/Events/Index.php`.
  - Radius input uses step `1` with explicit `min=1` and `max=1000` (browser attributes and backend normalization).
  - Radius control is visible only when both `lat` and `lng` exist.
  - Updated `resources/views/livewire/pages/events/index.blade.php` so saved-search query params include `radius_km` only when location coordinates are present.
  - Updated radius bounds across app/API validation and normalization from `500` to `1000`.
- Verification:
  - `php -l app/Livewire/Pages/SavedSearches/Index.php` => **No syntax errors**
  - `php -l resources/views/livewire/pages/saved-searches/index.blade.php` => **No syntax errors**
  - `php -l app/Livewire/Pages/Events/Index.php` => **No syntax errors**
  - `php -l resources/views/livewire/pages/events/index.blade.php` => **No syntax errors**
  - `php -l tests/Feature/SavedSearchPageTest.php` => **No syntax errors**
  - `php -l tests/Feature/EventSearchTest.php` => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/SavedSearchPageTest.php` => **7 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="shows radius control only when a nearby location is available"` => **1 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/SavedSearchApiTest.php --filter="(requires lat/lng when radius_km is provided|accepts radius up to 1000 km when coordinates are provided)"` => **2 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/SavedSearches/Index.php app/Livewire/Pages/Events/Index.php tests/Feature/SavedSearchPageTest.php tests/Feature/EventSearchTest.php` => **No errors**
  - Chrome MCP:
    - `/majlis?state_id=2489` shows active chip with state name (`Johor` in seeded local data), not `State Id:<id>`.
    - `/majlis?lat=3.1390&lng=101.6869` only shows `Radius (km)` after expanding Advanced Filters, confirming location-gated visibility.
    - Radius input is `type=number` with `min=1`, `max=1000`, `step=1`.

---

# Majlis Advanced Filter Group Split (People & Content vs Event Settings)

- [x] Split event-specific filters out of `People & Content` into a dedicated section
- [x] Keep content/taxonomy filters grouped under `People & Content`
- [x] Verify `/majlis` renders both groups correctly
- [x] Run focused test + PHPStan verification

## Review

- Updated `app/Livewire/Pages/Events/Index.php` advanced filter schema:
  - Kept `People & Content` for speaker/taxonomy/reference filters.
  - Added new `Event Settings` section for:
    - `Jenis Majlis` (`event_type`)
    - `Format Majlis` (`event_format`)
    - `Jantina` (`gender`)
    - `Kumpulan Umur` (`age_group`)
    - `Bahasa` (`language_codes`)
- Verified in browser (Chrome MCP) on `/majlis` that advanced filters now render as two sections: `People & Content` and `Event Settings`.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="displays the events index page"` => **1 passed (3 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Events/Index.php` => **No errors**

---

# Majlis Filter Field Order Tweak (Kategori before Bidang Ilmu)

- [x] Reorder `People & Content` fields so `Kategori` appears before `Bidang Ilmu`
- [x] Keep active-filter chip order consistent with the same ordering
- [x] Verify syntax/tests and browser rendering

## Review

- Updated field order in `app/Livewire/Pages/Events/Index.php` (`People & Content`): `Kategori` now comes before `Bidang Ilmu`.
- Updated active chip rendering order in `resources/views/livewire/pages/events/index.blade.php` so category chips render before knowledge-field chips.
- Verification:
  - `php -l app/Livewire/Pages/Events/Index.php` => **No syntax errors**
  - `php -l resources/views/livewire/pages/events/index.blade.php` => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="displays the events index page"` => **1 passed (3 assertions)**
  - Chrome MCP on `/majlis` confirms `People & Content` order shows `Kategori` before `Bidang Ilmu`.

---

# Majlis Advanced Filter Translation Coverage

- [x] Audit `/majlis` advanced-filter related translation keys used by Livewire schema and Blade active-filter chips
- [x] Add missing locale entries in `ms.json` and `ms_MY.json`
- [x] Validate locale JSON and verify no missing keys remain for this page's `__()` usage
- [x] Verify browser rendering on `/majlis` in Malay locale

## Review

- Added missing translation keys for advanced-filter sections, descriptions, placeholders, option labels, and related chip text in:
  - `resources/lang/ms.json`
  - `resources/lang/ms_MY.json`
- This includes strings such as `Advanced Filters`, `People & Content`, `Event Settings`, `Links & Visibility`, `Any ...` placeholders, URL availability labels, and nearby-radius helper text.
- Verification:
  - JSON decode checks for both locale files => **No error**
  - Scripted key audit against `app/Livewire/Pages/Events/Index.php` and `resources/views/livewire/pages/events/index.blade.php` => **No missing keys** in `ms.json` and `ms_MY.json`
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="displays the events index page"` => **1 passed (3 assertions)**
  - Chrome MCP on `https://majlisilmu.test/majlis` confirms advanced-filter headings and descriptions are now Malay (`Penapis Lanjutan`, `Masa & Tarikh`, `Penceramah & Kandungan`, `Tetapan Majlis`, etc.).

---

# Scoped Member Roles (Institution/Speaker/Event)

- [x] Add a shared scoped member-role seeder service for Institution, Speaker, and Event scopes
- [x] Auto-seed missing scoped roles from admin Filament member relation managers before rendering role options
- [x] Enable Authz scopes UI support in config so scoped roles are visible/manageable in Authz roles UI
- [x] Seed missing baseline permissions (`speaker.*`, `event.manage-members`)
- [x] Add focused regression tests and run verification

## Review

- Added `App\Support\Authz\ScopedMemberRoleSeeder` with canonical templates:
  - Institution: `owner`, `admin`, `editor`, `viewer`
  - Speaker: `owner`, `admin`, `editor`, `viewer`
  - Event: `organizer`, `co-organizer`, `editor`, `viewer`
- Updated default-seed behavior to be non-destructive:
  - Missing scoped roles are created automatically.
  - Existing scoped role permissions are preserved (to allow per-entity customization).
- Wired auto-seeding into role option loading for member management in:
  - `app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php`
  - `app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php`
  - `app/Filament/Resources/Events/RelationManagers/EventUsersRelationManager.php`
- Added inline custom-role creation in all three member relation managers:
  - Role name + permission selection can be created directly from the role picker modal.
- Added batch backfill seeder:
  - `database/seeders/ScopedMemberRolesSeeder.php`
  - Can be run manually with `php artisan db:seed --class=ScopedMemberRolesSeeder`
- Enabled scopes UI config:
  - `config/filament-authz.php` => `authz_scopes.enabled = true`
- Stopped central-app role listing to avoid massive duplicate scoped role lists in `/admin/authz/roles`:
  - `app/Providers/Filament/AdminPanelProvider.php` now uses `FilamentAuthzPlugin::make()` without `->centralApp()`
- Added missing permissions:
  - `database/seeders/PermissionSeeder.php`
- Added event scope label support:
  - `app/Models/Event.php` => `getAuthzScopeLabel()`
- Added tests:
  - `tests/Feature/ScopedMemberRoleSeederTest.php`
- Verification:
  - `php -l` on all changed PHP files => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature/ScopedMemberRoleSeederTest.php` => **4 passed (11 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/EventPolicyTest.php --filter="manageMembers"` => **3 passed (3 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Support/Authz/ScopedMemberRoleSeeder.php database/seeders/ScopedMemberRolesSeeder.php app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php app/Filament/Resources/Events/RelationManagers/EventUsersRelationManager.php app/Models/Event.php` => **No errors**

# Authz Role Edit Translation Todo

- [x] Audit RoleResource translation keys used by Filament Authz role edit form
- [x] Expand local Malay vendor override with complete `filament-authz` key coverage
- [x] Improve wording for role edit labels, sections, tabs, search placeholders, and permission prefixes
- [x] Clear caches and verify no missing/extra translation keys versus package source

## Review

- Updated `resources/lang/vendor/filament-authz/ms/filament-authz.php` from partial override to full translation map.
- Added all missing groups used by the role edit page:
  - `form`, `filter`, `tabs`, `search`, `section`, `empty_state`, `notification`, `forbidden`, `resource_permission_prefixes`, `command`, `impersonate`.
- Improved Malay wording for admin UI context (for example role details descriptions and permission action prefixes).
- Verification:
  - Key parity check against package source: **missing=0**, **extra=0**
  - `php artisan optimize:clear` completed successfully.
  - Live verification using admin account showed remaining hardcoded English (`Scope`, `Leave empty for a global role.`, `Clear resource search`), which required resource-level override.
- Added localized app-level Authz Role resource override:
  - `app/Filament/Resources/Authz/RoleResource.php`
  - `app/Filament/Resources/Authz/RoleResource/Pages/{ListRoles,CreateRole,EditRole}.php`
- Updated panel plugin registration to disable package Role resource and use app override:
  - `app/Providers/Filament/AdminPanelProvider.php` (`->roleResource(false)`)
- Added new translation keys for scope/search-clear labels:
  - `resources/lang/vendor/filament-authz/ms/filament-authz.php`
  - `resources/lang/vendor/filament-authz/en/filament-authz.php`
- Verification (override changes):
  - `php -l` on all modified/new files => **No syntax errors**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/Authz/RoleResource.php app/Filament/Resources/Authz/RoleResource/Pages/ListRoles.php app/Filament/Resources/Authz/RoleResource/Pages/CreateRole.php app/Filament/Resources/Authz/RoleResource/Pages/EditRole.php app/Providers/Filament/AdminPanelProvider.php` => **No errors**
  - Browser verification on `/admin/authz/roles/{id}/edit` now shows:
    - `Skop`
    - `Biarkan kosong untuk peranan global.`
    - `Kosongkan carian sumber`

# Authz Role Permission Assignment Expansion

- [x] Investigate AiArmada/Authz role-permission sync mechanism and current UX limitations
- [x] Add explicit role UI for assigning non-Filament permissions directly
- [x] Ensure new field participates in existing sync pipeline (`permissions_*`)
- [x] Fix scoped role name uniqueness validation so scoped roles can be saved reliably
- [x] Verify on live admin role edit page with save/reload cycle

## Review

- Package behavior confirmed:
  - `SyncsRolePermissions` only syncs form keys prefixed with `permissions_`.
  - Default role UI mainly exposes discovered Filament Resource/Page/Widget permissions (plus configured custom list), which can hide app-specific permissions from role editors.
- Expanded role edit UI in app override:
  - `app/Filament/Resources/Authz/RoleResource.php`
  - Added new `Kebenaran Tambahan` tab containing `permissions_direct` (prefixed correctly for sync trait).
  - Tab lists **non-discovered** DB permissions (for selected guard), so no duplicate conflict with existing Resource/Page/Widget tabs.
- Fixed save blocker for scoped roles:
  - Replaced global `unique` check on role `name` with scoped unique query (`name + guard_name + scope`) while ignoring current record.
  - This resolved false-positive `nama telah pun diambil` validation on scoped role edits.
- Translation updates:
  - `resources/lang/vendor/filament-authz/ms/filament-authz.php`
  - `resources/lang/vendor/filament-authz/en/filament-authz.php`
- Verification:
  - `php -l` and targeted PHPStan on modified resource files => **No errors**
  - Browser verification on:
    - `https://majlisilmu.test/admin/authz/roles/019cb6ef-009d-73b9-8a90-8239af95ebbe/edit`
  - Confirmed:
    - New tab visible (`Kebenaran Tambahan`)
    - Direct permission checkboxes rendered (e.g., `event.manage-members`, `speaker.manage-members`)
    - Save + reload persists permission changes
    - Test toggle was reverted to original state after validation (`speaker.manage-members` left unchecked).
- Vendor migration completed:
  - RoleResource enhancements moved into vendor package:
    - `vendor/aiarmada/filament-authz/src/Resources/RoleResource.php`
    - `vendor/aiarmada/filament-authz/src/Resources/RoleResource/Concerns/HasAuthzFormComponents.php`
    - `vendor/aiarmada/filament-authz/resources/lang/en/filament-authz.php`
    - `vendor/aiarmada/filament-authz/config/filament-authz.php`
  - Local override resources removed:
    - `app/Filament/Resources/Authz/RoleResource.php`
    - `app/Filament/Resources/Authz/RoleResource/Pages/{ListRoles,CreateRole,EditRole}.php`
  - Admin panel plugin registration reverted to vendor RoleResource:
    - `app/Providers/Filament/AdminPanelProvider.php` now uses `FilamentAuthzPlugin::make()->centralApp()`

# Ahli Event Scope Todo

- [x] Update ahli event resource query scope to include: own submitted, organizer institution membership, organizer speaker membership
- [x] Add/adjust ahli event resource pages to make scoped events manageable from ahli panel
- [x] Add focused feature tests for scope paths (submitter + organizer membership)
- [x] Run focused verification and document review

## Review

- Updated `app/Filament/Ahli/Resources/Events/EventResource.php`:
  - ahli resource now **extends** `App\Filament\Resources\Events\EventResource` so form/table/filter improvements in admin resource flow into ahli by default.
  - keeps ahli-specific overrides only for:
    - scoped query constraints
    - page map (`index`, `edit`)
    - disabled create action
    - record actions tweak (edit + public view, no bulk toolbar actions)
  - scoped query includes:
    - `events.user_id = auth user`
    - `events.submitter_id = auth user`
    - `event_submissions.submitted_by = auth user`
    - organizer institution events where user is institution member
    - organizer speaker events where user is speaker member
    - legacy fallback (`organizer_type` null + member institution via `institution_id`)
  - added `index` page route so ahli users can manage scoped events from panel navigation.
- Added `app/Filament/Ahli/Resources/Events/Pages/ListEvents.php`.
- Updated feature tests in `tests/Feature/AhliPanelInstitutionEditingTest.php`:
  - submitter can access own submitted event edit.
  - speaker member can access speaker-organized event edit (with scoped direct permission).
  - ahli events index lists only scoped events and excludes out-of-scope events.
  - existing institution-scoped tests kept and aligned with organizer fields.
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AhliPanelInstitutionEditingTest.php` => **6 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Ahli/Resources/Events/EventResource.php app/Filament/Ahli/Resources/Events/Pages/ListEvents.php tests/Feature/AhliPanelInstitutionEditingTest.php` => **No errors**
  - `php artisan route:list | rg \"ahli.*events|events/{record}/edit\"` confirms:
    - `GET ahli.majlisilmu.test/events`
    - `GET ahli.majlisilmu.test/events/{record}/edit`

# Public Submission Lock Gating Todo

- [x] Add explicit lock metadata and keep `allow_public_event_submission=true` as default state
- [x] Add centralized eligibility checker returning `eligible + reasons` without mutating lock state
- [x] Add explicit global-admin lock/unlock actions for Institution and Speaker admin pages
- [x] Disable lock action until eligibility passes and show eligibility reasons
- [x] Auto-reopen locked entities when credibility drifts (member/role/phone changes + scheduled sweep)
- [x] Enforce submit-event entity access with resolver filtering and server-side validation
- [x] Add focused feature coverage and run verification

## Review

- Added migration `database/migrations/2026_03_06_120000_add_public_submission_lock_columns_to_institutions_and_speakers.php`:
  - `allow_public_event_submission` (default `true`)
  - `public_submission_locked_at`
  - `public_submission_locked_by`
- Removed unused lock-reason persistence:
  - `public_submission_lock_reason` was deleted from models/service usage
  - fresh installs no longer create the column
  - existing databases drop it via `database/migrations/2026_03_06_130000_drop_public_submission_lock_reason_columns.php`
- Added centralized submission access + lock services:
  - `app/Support/Submission/SubmissionLockEligibilityResult.php`
  - `app/Support/Submission/PublicSubmissionLockService.php`
  - `app/Support/Submission/EntitySubmissionAccess.php`
- Added lock sweep command + scheduler:
  - `app/Console/Commands/SyncPublicSubmissionLocks.php`
  - `routes/console.php` (`app:sync-public-submission-locks`, hourly)
- Updated Institution/Speaker admin edit pages to use explicit lock/unlock actions:
  - initial implementation used explicit header lock/unlock actions in:
    - `app/Filament/Resources/Institutions/Pages/EditInstitution.php`
    - `app/Filament/Resources/Speakers/Pages/EditSpeaker.php`
- Simplified the admin UX to use the existing `allow_public_event_submission` toggle directly on the edit form:
  - header lock/unlock actions were removed
  - toggle changes now route through the same lock service in the edit page save hooks
  - the toggle is now disabled while the record is still public and the lock-eligibility precondition has not passed
  - save-time eligibility failures remain as backend protection
  - helper text on the form now explains when `true -> false` is not yet allowed
- Updated status form schemas:
  - `app/Filament/Resources/Institutions/Schemas/InstitutionForm.php`
  - `app/Filament/Resources/Speakers/Schemas/SpeakerForm.php`
- Updated member role/member change triggers to auto-reopen when eligibility fails:
  - `app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php`
  - `app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php`
  - `app/Models/User.php` (phone verification drift hook)
- Updated submit-event filtering + backend enforcement:
  - `resources/views/components/pages/submit-event/create.blade.php`
- Added focused tests:
  - `tests/Feature/PublicSubmissionLockActionsTest.php`
  - `tests/Feature/SubmitEventEntityAccessTest.php`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/PublicSubmissionLockActionsTest.php` => **6 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/SubmitEventEntityAccessTest.php` => **3 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/AhliPanelInstitutionEditingTest.php` => **6 passed**
  - `vendor/bin/pest --parallel --compact --filter="SubmitEventLocationTest|SubmitEventOrganizerAutoSelectTest|EditInstitutionSocialMediaTest|SpeakerAdminEditSocialMediaLabelTest"` => **9 passed**
  - `vendor/bin/phpstan analyse --ansi [changed lock/access files]` => **No errors**
  - `vendor/bin/phpstan analyse --ansi` => **17 pre-existing unrelated project errors remain**

# Authz User Verification Fields Todo

- [x] Replace package auto-registered Authz user resource with a local override
- [x] Expose `email_verified_at` and `phone_verified_at` on the admin user form with proper datetime inputs
- [x] Add focused regression coverage for the Authz user edit page
- [x] Run focused verification

## Review

- Disabled package auto-registration for the Authz user resource in `config/filament-authz.php` and expanded configured field order to include:
  - `email_verified_at`
  - `phone_verified_at`
- Added local override resource and pages:
  - `app/Filament/Resources/Authz/UserResource.php`
  - `app/Filament/Resources/Authz/UserResource/Pages/ListUsers.php`
  - `app/Filament/Resources/Authz/UserResource/Pages/CreateUser.php`
  - `app/Filament/Resources/Authz/UserResource/Pages/EditUser.php`
- The edit form now exposes both verification timestamps as proper `DateTimePicker` fields on the Authz user page.
- Added regression test:
  - `tests/Feature/AuthzUserResourceTest.php`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AuthzUserResourceTest.php` => **2 passed**
  - Added page-level payload sanitization in:
    - `app/Filament/Resources/Authz/UserResource/Pages/CreateUser.php`
    - `app/Filament/Resources/Authz/UserResource/Pages/EditUser.php`
  - This prevents `roles` / `permissions` from being mass-assigned onto `App\Models\User` while still allowing the form relationship hooks to sync them.
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/Authz/UserResource.php app/Filament/Resources/Authz/UserResource/Pages/CreateUser.php app/Filament/Resources/Authz/UserResource/Pages/EditUser.php tests/Feature/AuthzUserResourceTest.php` => **No errors**

# Member Relation User Links Todo

- [x] Link institution member rows to the Authz user edit resource
- [x] Keep speaker member rows aligned with the same user-resource link behavior
- [x] Add focused regression coverage for the institution members relation
- [x] Run focused verification

## Review

- Updated member relation manager name columns to point at the Authz user edit page:
  - `app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php`
  - `app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php`
- The member name now resolves to `App\Filament\Resources\Authz\UserResource::getUrl('edit', ...)` when the current admin can edit that user resource.
- Added regression test:
  - `tests/Feature/InstitutionMembersRelationLinkTest.php`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionMembersRelationLinkTest.php` => **1 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/Institutions/RelationManagers/MembersRelationManager.php app/Filament/Resources/Speakers/RelationManagers/MembersRelationManager.php tests/Feature/InstitutionMembersRelationLinkTest.php` => **No errors**

# User Dashboard Planner + Digest Preferences Todo

- [x] Add a dedicated authenticated digest preferences page and route aliases
- [x] Move digest preference state/save logic out of `UserDashboard`
- [x] Add `Digest Preferences` to the authenticated desktop and mobile user menus
- [x] Redesign `/dashboard` into an attendee-first planner with overview, calendar, agenda, activity buckets, submitted events, and recent check-ins
- [x] Remove saved-search and digest-preferences panels from `/dashboard`
- [x] Update dashboard and notification preference tests for the new page, menu, and planner behavior
- [x] Run focused verification and document the outcome

## Review

- Added a dedicated digest settings surface:
  - `app/Livewire/Pages/Dashboard/DigestPreferences.php`
  - `resources/views/livewire/pages/dashboard/digest-preferences.blade.php`
  - named route `dashboard.digest-preferences` on `/papan-pemuka/pilihan-digest`
  - legacy alias `/dashboard/digest-preferences`
- Moved digest preference state hydration, validation, and persistence out of `UserDashboard` and preserved the existing saved-search digest preference semantics.
- Updated authenticated navigation in `resources/views/layouts/app.blade.php`:
  - reordered user menu items to `Dashboard`, `Saved Searches`, `Digest Preferences`, `Institution Dashboard`
  - added the new menu entry in both desktop dropdown and mobile authenticated menu
- Rebuilt `app/Livewire/Pages/Dashboard/UserDashboard.php` into an attendee-first planner:
  - dedicated collections for saved, interested, going, registered, submitted, and recent check-ins
  - merged calendar entries by event/date across overlapping roles
  - upcoming agenda derived from the merged calendar entries
  - institution operations removed from the main dashboard data model
- Replaced `resources/views/livewire/pages/dashboard/user-dashboard.blade.php` with a planner layout:
  - header/summary cards
  - overview month calendar with role filters
  - upcoming agenda
  - compact Going / Registered / Saved buckets
  - Submitted Events section
  - Recent Check-ins history
  - removed saved-search and digest-preference panels from the dashboard body
- Updated focused regression coverage:
  - `tests/Feature/DashboardPagesTest.php`
  - `tests/Feature/NotificationPreferencesTest.php`
- Verification:
  - `php artisan route:list --path=digest-preferences` => **new digest-preferences route present**
  - `php artisan route:list --path=dashboard` => **dashboard route set includes digest-preferences**
- `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **8 passed**
- `vendor/bin/pest --parallel --compact tests/Feature/NotificationPreferencesTest.php` => **7 passed**
- `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/DigestPreferences.php app/Livewire/Pages/Dashboard/UserDashboard.php tests/Feature/DashboardPagesTest.php tests/Feature/NotificationPreferencesTest.php` => **No errors**

## Account Settings Notifications Translation Cleanup

- [x] Audit the `/tetapan-akaun?tab=notifications` surface for untranslated labels and leaked translation keys
- [x] Fix the account settings notification template to use the correct notification locale keys
- [x] Add missing push-channel translations across supported locale JSON files
- [x] Add a focused Malay render test for the notifications tab
- [x] Run focused verification

## Review

- Fixed repeated trigger-card copy in `resources/views/livewire/pages/dashboard/account-settings.blade.php` by switching the missing `notifications.settings.triggers.*` references to the existing `notifications.ui.triggers.*` keys.
- Normalized the destination card labels to use the channel label map already prepared by the page, so the notifications tab uses the translated channel names consistently.
- Added the missing `Push Notification` translation key to:
  - `resources/lang/en.json`
  - `resources/lang/ms.json`
  - `resources/lang/ms_MY.json`
  - `resources/lang/zh.json`
  - `resources/lang/ta.json`
  - `resources/lang/jv.json`
- Added Malay regression coverage in `tests/Feature/AccountSettingsPageTest.php` to ensure the notifications tab renders translated copy and does not leak raw translation keys.
- Verification:
- `vendor/bin/pest --parallel --compact tests/Feature/AccountSettingsPageTest.php` => **7 passed**
- `php -l resources/views/livewire/pages/dashboard/account-settings.blade.php` => **No syntax errors**
- `php -l tests/Feature/AccountSettingsPageTest.php` => **No syntax errors**

## Institution Dashboard Route And Copy Cleanup

- [x] Inspect the institution dashboard routes, labels, and registrations copy
- [x] Make `/dashboard/institusi` the institution dashboard URL and remove the old paths
- [x] Replace the generic events heading with `Senarai Majlis`
- [x] Remove the standalone `Pendaftaran Majlis` dashboard section and turn registration counts into Ahli drill-down links
- [x] Rename the private-visibility label from `Peribadi` to `Tersembunyi`
- [x] Update focused tests and run verification

## Review

- Updated `routes/web.php` so the named institution dashboard route now resolves to `/dashboard/institusi`.
- Removed the old institution dashboard paths:
  - `/papan-pemuka/institusi`
  - `/dashboard/institutions`
- Updated `app/Filament/Ahli/Resources/Events/EventResource.php`:
  - registered keyed Ahli relation managers so `?relation=registrations` can reliably target the registrations relation manager tab
- Updated `resources/views/livewire/pages/dashboard/institution-dashboard.blade.php`:
  - changed the institution events section heading to `Event List` / `Senarai Majlis`
  - removed the separate `Event Registrations` / `Pendaftaran Majlis` block from the dashboard
  - turned each event registration count into a deep link to the Ahli event registrations relation manager from the dashboard
  - changed the private-visibility badge copy from `Private` / `Peribadi` to `Hidden` / `Tersembunyi`
- Updated `app/Livewire/Pages/Dashboard/InstitutionDashboard.php`:
  - removed the now-unused institution-registrations dashboard query while preserving the institution summary registration counts
- Updated locale strings in:
  - `resources/lang/en.json`
  - `resources/lang/ms.json`
  - `resources/lang/ms_MY.json`
  - `resources/lang/zh.json`
  - `resources/lang/ta.json`
  - `resources/lang/jv.json`
- Updated focused coverage:
  - `tests/Feature/DashboardPagesTest.php`
    - now expects `route('dashboard.institutions')` to end with `/dashboard/institusi`
    - verifies `/dashboard/institusi` requires auth
    - verifies the removed legacy institution-dashboard URLs return `404`
    - verifies the institution dashboard shows `Event List` / `Senarai Majlis`
    - verifies the old registrations section is gone
    - verifies `Hidden` / `Tersembunyi` replaces `Private` / `Peribadi`
  - `tests/Feature/AhliPanelInstitutionEditingTest.php`
    - switched the dashboard URL usage to the named route
    - verifies institution admins see the Ahli registrations deep link from the dashboard
    - verifies the Ahli event page honors `?relation=registrations` by selecting the registrations relation manager
    - keeps the link hidden for viewers who cannot manage the event
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **14 passed**
  - `vendor/bin/pest --parallel --compact --filter='shows ahli edit links on institution dashboard only when user can update' tests/Feature/AhliPanelInstitutionEditingTest.php` => **1 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/InstitutionDashboard.php app/Filament/Ahli/Resources/Events/EventResource.php tests/Feature/DashboardPagesTest.php tests/Feature/AhliPanelInstitutionEditingTest.php` => **No errors**

# Ahli Event View Drill-down Todo

- [x] Inspect the Ahli event resource page set and confirm whether a view page already exists
- [x] Add an Ahli event view page that supports relation-tab deep links
- [x] Repoint institution-dashboard registration counts to the Ahli view page registrations tab
- [x] Update focused tests and run verification

## Review

- Root cause:
  - the Ahli event resource only exposed `index` and `edit`, so the dashboard registrations count had to deep-link into the edit page even when the user only needed to inspect the registrations relation manager
  - that made the registrations drill-down land on a heavier editing surface instead of a read-first page
  - after adding the Ahli event view page, the shared `EventInfolist` still assumed every admin-related resource route also existed in the Ahli panel, which caused missing-route crashes for speakers, venues, references, and series
- Fix:
  - added `app/Filament/Ahli/Resources/Events/Pages/ViewEvent.php`
    - introduced a dedicated Ahli event `view` page based on Filament `ViewRecord`
    - kept it full-width and added header actions for `Edit` and `View Public Page`
  - updated `app/Filament/Ahli/Resources/Events/EventResource.php`
    - registered the new `'view' => ViewEvent::route('/{record}')` page
    - restored a `ViewAction` in the Ahli events table so the new page is reachable from the panel itself
  - updated `resources/views/livewire/pages/dashboard/institution-dashboard.blade.php`
    - changed the registrations count link from the Ahli edit URL to the Ahli view URL with `?relation=registrations`
    - based the registrations link on relation-manager visibility instead of generic edit access
  - updated `app/Filament/Resources/Events/Schemas/EventInfolist.php`
    - made related-record links panel-safe instead of assuming admin resource routes exist in Ahli
    - kept institution links clickable in Ahli through the Ahli institution resource
    - rendered speakers, venues, references, and series as plain text in Ahli when those resources are not registered there
  - updated `tests/Feature/AhliPanelInstitutionEditingTest.php`
    - verifies institution members can open the Ahli event view page
    - verifies the dashboard registrations link now targets the Ahli view page
    - verifies the Ahli view page selects the `registrations` relation manager when `?relation=registrations` is present
    - verifies the Ahli event view page renders safely with linked speaker, series, reference, and venue data
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **14 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/AhliPanelInstitutionEditingTest.php` => **11 passed**
  - `vendor/bin/phpstan analyse --ansi app/Filament/Resources/Events/Schemas/EventInfolist.php app/Filament/Ahli/Resources/Events/EventResource.php app/Filament/Ahli/Resources/Events/Pages/ViewEvent.php tests/Feature/AhliPanelInstitutionEditingTest.php tests/Feature/DashboardPagesTest.php` => **No errors**

# Institution Dashboard Pending Highlight

- [x] Inspect the institution event list rendering on `/dashboard/institusi`
- [x] Add stronger emphasis for approval-pending events in the institution event list
- [x] Update focused dashboard coverage and verify

## Review

- Root cause:
  - pending institution events were rendered with the same neutral table treatment as routine rows, so approval-waiting items did not stand out despite being the most urgent operational work on the page
- Fix:
  - updated `resources/views/livewire/pages/dashboard/institution-dashboard.blade.php`
    - added a pending-specific amber row treatment for institution events with `status = pending`
    - added a clear `Menunggu Kelulusan` / `Pending Approval` pill under the event title
    - strengthened the status badge styling for pending rows and tagged those rows with `data-event-status="pending-attention"` for regression coverage
  - updated `tests/Feature/DashboardPagesTest.php`
    - added coverage proving pending institution events render the `Pending Approval` emphasis and the pending-attention row marker
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **15 passed**
  - `php -l tests/Feature/DashboardPagesTest.php` => **No syntax errors**

# Institution Dashboard Ahli Action Labels

- [x] Replace the old `Edit in Ahli Panel` wording on institution event rows
- [x] Make pending events use `Review` and non-pending events use `Edit`
- [x] Update focused Ahli dashboard coverage and verify

## Review

- Root cause:
  - the institution dashboard still used the generic `Edit in Ahli Panel` wording on every Ahli drill-down link, even though the user wanted a shorter label and a clearer approval-oriented action for pending events
- Fix:
  - updated `resources/views/livewire/pages/dashboard/institution-dashboard.blade.php`
    - replaced the old event-row action label with a status-aware label
    - pending events now show `Review`
    - non-pending events now show `Edit`
  - updated locale JSON files:
    - `resources/lang/en.json`
    - `resources/lang/ms.json`
    - `resources/lang/ms_MY.json`
    - `resources/lang/zh.json`
    - `resources/lang/ta.json`
    - `resources/lang/jv.json`
    - added direct translation keys for `Edit` and `Review`
  - updated `tests/Feature/AhliPanelInstitutionEditingTest.php`
    - draft event rows now assert `Edit`
    - pending event rows now assert `Review`
    - old `Edit in Ahli Panel` wording is explicitly kept out
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/AhliPanelInstitutionEditingTest.php` => **12 passed**
  - `vendor/bin/phpstan analyse --ansi tests/Feature/AhliPanelInstitutionEditingTest.php` => **No errors**

# Institution Dashboard Event Controls And Members

- [x] Remove the redundant institution summary cards for registrations and members
- [x] Add filtering, sorting, and pagination controls to `Senarai Majlis`
- [x] Add a members-and-roles management section on the institution dashboard
- [x] Update focused dashboard tests, Ahli tests, and verification notes

## Review

- Root cause:
  - the institution dashboard still carried old summary-card assumptions from when registrations and member counts were treated as top-level dashboard metrics, but the user wanted the page to behave like an operations workspace
  - `Senarai Majlis` was still a static table without search, filtering, sorting, or page-size control, so it was not useful once an institution had more than a handful of events
  - member management existed only inside the Filament relation manager, which forced users to leave the dashboard for a routine add-member / assign-role task
- Fix:
  - updated `app/Livewire/Pages/Dashboard/InstitutionDashboard.php`
    - removed the dead registrations-page reset logic
    - added event search, status, visibility, sort, and per-page state with URL-backed filters
    - refactored the event query to support filter and sort combinations while keeping registration counts and pending highlighting intact
    - added paginated institution-member data plus scoped role helpers using the same institution authz scope as the Filament relation manager
    - added member actions for add, edit roles, cancel edit, save roles, and remove member
  - updated `resources/views/livewire/pages/dashboard/institution-dashboard.blade.php`
    - removed the top `Registrations (All)` and `Members` summary cards
    - kept a single compact `Majlis` summary card
    - added real `Senarai Majlis` controls for search, status, visibility, sort, and page size
    - added a new `Members & Roles` section with add-member form, role editing, removal actions, and paginated member list
  - updated locale JSON files:
    - `resources/lang/en.json`
    - `resources/lang/ms.json`
    - `resources/lang/ms_MY.json`
    - `resources/lang/zh.json`
    - `resources/lang/ta.json`
    - `resources/lang/jv.json`
    - added translation coverage for the new event controls, member-management copy, and pending-approval label
  - updated `tests/Feature/DashboardPagesTest.php`
    - now verifies the dashboard no longer renders the removed registrations summary card
    - covers event filtering, sorting, and pagination
    - covers member add / role update / remove flow on the Livewire dashboard itself
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **18 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/AhliPanelInstitutionEditingTest.php` => **12 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/InstitutionDashboard.php tests/Feature/DashboardPagesTest.php tests/Feature/AhliPanelInstitutionEditingTest.php` => **No errors**
  - `php -r 'foreach ([\"resources/lang/en.json\",\"resources/lang/ms.json\",\"resources/lang/ms_MY.json\",\"resources/lang/zh.json\",\"resources/lang/ta.json\",\"resources/lang/jv.json\"] as $file) { json_decode(file_get_contents($file)); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file.\": \".json_last_error_msg().PHP_EOL); exit(1); } } echo \"locale JSON validation passed\\n\";'` => **locale JSON validation passed**

# Institution Dashboard Single Role Select

- [x] Change institution member role inputs from multi-select to single-select
- [x] Update Livewire validation and role-sync payloads to accept one role ID
- [x] Update focused dashboard tests and verification notes

## Review

- Root cause:
  - the new institution members section reused the earlier multi-role relation-manager shape too literally, so the dashboard rendered role selection as a multi-select even though the user wanted a single role per member from this surface
- Fix:
  - updated `app/Livewire/Pages/Dashboard/InstitutionDashboard.php`
    - changed member-role state from array properties to single role ID strings
    - updated add/save validation rules to require a single selected role
    - updated role syncing to accept one scoped role ID at a time
    - kept removal behavior clearing all scoped roles
  - updated `resources/views/livewire/pages/dashboard/institution-dashboard.blade.php`
    - replaced the add-member and edit-role multi-select boxes with single `<select>` fields
    - added an explicit `Select a role` placeholder option
    - updated error bindings to the new single-field validation keys
  - updated locale JSON files:
    - `resources/lang/en.json`
    - `resources/lang/ms.json`
    - `resources/lang/ms_MY.json`
    - `resources/lang/zh.json`
    - `resources/lang/ta.json`
    - `resources/lang/jv.json`
    - added the `Select a role` translation
  - updated `tests/Feature/DashboardPagesTest.php`
    - member-management assertions now use the single role ID state shape for add and edit flows
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DashboardPagesTest.php` => **18 passed**
  - `vendor/bin/phpstan analyse --ansi app/Livewire/Pages/Dashboard/InstitutionDashboard.php tests/Feature/DashboardPagesTest.php` => **No errors**
  - `php -r 'foreach ([\"resources/lang/en.json\",\"resources/lang/ms.json\",\"resources/lang/ms_MY.json\",\"resources/lang/zh.json\",\"resources/lang/ta.json\",\"resources/lang/jv.json\"] as $file) { json_decode(file_get_contents($file)); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file.\": \".json_last_error_msg().PHP_EOL); exit(1); } } echo \"locale JSON validation passed\\n\";'` => **locale JSON validation passed**

# Dawah Share Impact

- [x] Add the `dawah_share_links`, `dawah_share_attributions`, `dawah_share_visits`, and `dawah_share_outcomes` tables and corresponding Eloquent models
- [x] Build canonical share URL generation, subject classification, attribution cookies, and visit tracking middleware
- [x] Record attributed signups, event registrations, saves, interests, going actions, follows, and saved-search creation
- [x] Add dashboard impact summary, dedicated impact pages, and tracked share UI across supported public surfaces
- [x] Add focused feature coverage plus PHPStan verification for the new share-impact flows

## Review

- Root cause:
  - the last `wip` commit had already landed most of the dawah-share runtime, but verification stopped early enough that important downstream attribution paths and public share-surface coverage were still under-tested
  - one impacted speaker-page assertion was also flaky because the event factory can randomly create online events, which made a location fallback test nondeterministic after the share changes touched that page
- Fix:
  - kept the dawah-share runtime intact and completed the verification layer by extending `tests/Feature/DawahShareImpactTest.php` to cover attributed event saves, event interests, saved-search creation, and tracked share UI across the supported public surfaces
  - stabilized `tests/Feature/SpeakerShowPageTimingTest.php` by forcing the location-specific speaker-page cases to use physical events
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/DawahShareImpactTest.php tests/Feature/SpeakerShowPageTimingTest.php` => **15 passed**
  - `vendor/bin/pest --parallel --compact tests/Feature/DawahShareImpactTest.php tests/Feature/EventShowPageTest.php tests/Feature/SpeakerShowPageTimingTest.php tests/Feature/InstitutionShowPageTest.php tests/Feature/DashboardPagesTest.php` => **68 passed**
  - `vendor/bin/phpstan analyse --ansi app/Services/DawahShare app/Http/Controllers/DawahShareController.php app/Http/Middleware/TrackDawahShareAttribution.php app/Livewire/Pages/Dashboard/DawahImpactIndex.php app/Livewire/Pages/Dashboard/DawahImpactLinkShow.php app/Livewire/Pages/Events/Show.php app/Livewire/Pages/SavedSearches/Index.php tests/Feature/DawahShareImpactTest.php tests/Feature/SpeakerShowPageTimingTest.php` => **No errors**

# Feedback Blocking Permission

- [x] Review the uncommitted feedback-blocking changes and existing authz patterns
- [x] Replace dedicated feedback-ban user columns with a direct `feedback.blocked` permission
- [x] Update focused tests and verification for the new permission-based blocking flow

## Review

- Root cause:
  - the feedback-ban branch modeled a rare deny-list state with dedicated `users` columns, plus ban timestamp and reason fields, even though the app already has direct user permissions for exceptional cases
  - API report submission also changed to authenticated-only but never actually enforced `ReportPolicy::create()`, so blocked users could still submit through the API path
- Fix:
  - removed the dedicated feedback-ban migration and user-form fields
  - seeded a global `feedback.blocked` permission and changed `User::canSubmitDirectoryFeedback()` to reverse-check a direct global permission instead of reading columns
  - kept the existing page-level feedback guards and added explicit API authorization in `ReportController`
  - updated the feedback-related tests to ban users via direct permission assignment instead of user columns
- Verification:
  - `vendor/bin/pest --parallel --compact --filter='(ContributionPagesTest|ReportApiModerationTest)'` => **16 passed**
  - `vendor/bin/phpstan analyse --ansi app/Http/Controllers/Api/ReportController.php app/Models/User.php database/seeders/PermissionSeeder.php tests/Feature/ContributionPagesTest.php tests/Feature/Api/ReportApiModerationTest.php` => **No errors**

# Migration Consolidation

- [x] Fold March notification/event follow-up schema changes into their base create migrations
- [x] Remove obsolete March cleanup/refactor migrations that no longer define fresh schema
- [x] Verify fresh migrations still expose the final tables/columns and omit removed legacy tables

## Review

- Root cause:
  - the migration history had accumulated a set of March follow-up migrations that only renamed, added, or dropped schema already implied by the app’s current models and tests, which made the fresh-schema story harder to read and maintain
  - the remaining March create migrations and data migrations also kept the schema timeline split across March files, plus extra backfill/compatibility logic, even though the desired end state is a clean fresh schema with no March migrations at all
- Fix:
  - updated `database/migrations/2026_01_10_000015_create_events_table.php` so the base `events` create includes `parent_event_id` and `event_structure`
  - updated `database/migrations/2026_01_10_000016_create_event_speaker_table.php` so the original event-people migration now creates the final `event_key_people` table shape directly
  - updated earlier migrations to absorb the remaining March table creates directly:
    - `database/migrations/2026_01_10_000019_create_event_submissions_table.php` now also creates `contribution_requests`
    - `database/migrations/2026_01_10_000024_create_registrations_table.php` now also creates `event_checkins`
    - `database/migrations/2026_01_11_101956_create_permission_tables.php` now also creates `member_invitations`
    - `database/migrations/2026_01_23_031834_create_references_table.php` now also creates `reference_user`
    - `database/migrations/2026_02_09_190614_create_notifications_table.php` now creates the full notification-center schema (`notification_settings`, `notification_rules`, `notification_destinations`, `notification_messages`, `notification_deliveries`) without March follow-up files or table-exists guards
  - removed the now-redundant cleanup/refactor/create/backfill migrations:
    - `database/migrations/2026_01_16_213657_create_event_interests_table.php`
    - `database/migrations/2026_02_09_190615_create_notification_endpoints_table.php`
    - `database/migrations/2026_02_09_190615_create_notification_preferences_table.php`
    - `database/migrations/2026_03_04_000100_create_event_checkins_table.php`
    - `database/migrations/2026_03_08_120000_create_notification_center_tables.php`
    - `database/migrations/2026_03_09_120000_refactor_notifications_for_laravel_channels.php`
    - `database/migrations/2026_03_09_180000_add_dispatch_tracking_to_notification_messages_table.php`
    - `database/migrations/2026_03_09_210000_drop_legacy_notification_tables.php`
    - `database/migrations/2026_03_11_000000_migrate_event_speaker_to_event_participants_table.php`
    - `database/migrations/2026_03_11_120000_add_event_hierarchy_to_events_table.php`
    - `database/migrations/2026_03_11_121755_remove_recurrence_and_sessions_from_events_domain.php`
    - `database/migrations/2026_03_12_000001_rename_event_participants_to_event_key_people.php`
    - `database/migrations/2026_03_12_104922_drop_legacy_dawah_share_tables.php`
    - `database/migrations/2026_03_12_170837_ensure_default_signals_tracked_property.php`
    - `database/migrations/2026_03_13_000001_create_contribution_requests_table.php`
    - `database/migrations/2026_03_13_000002_create_reference_user_table.php`
    - `database/migrations/2026_03_13_120000_ensure_admin_signals_tracked_property.php`
    - `database/migrations/2026_03_14_120000_prune_legacy_model_authz_scopes.php`
    - `database/migrations/2026_03_14_130000_create_member_invitations_table.php`
    - `database/migrations/2026_03_15_120000_remove_event_interest_feature.php`
  - extended `tests/Feature/RefactorTest.php` with direct schema assertions for the consolidated tables and removed legacy tables
  - removed the leftover table-exists/backfill migration behavior instead of preserving it, per the stricter “no backward compatibility or backfill” requirement
- Verification:
  - `rg --files database/migrations | sort | grep '2026_03_'` => **no output**
  - `php -l database/migrations/2026_01_10_000015_create_events_table.php && php -l database/migrations/2026_01_10_000016_create_event_speaker_table.php && php -l database/migrations/2026_01_10_000019_create_event_submissions_table.php && php -l database/migrations/2026_01_10_000024_create_registrations_table.php && php -l database/migrations/2026_01_11_101956_create_permission_tables.php && php -l database/migrations/2026_01_23_031834_create_references_table.php && php -l database/migrations/2026_02_09_190614_create_notifications_table.php && php -l tests/Feature/RefactorTest.php` => **No syntax errors**
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(RefactorTest|NotificationCenterApiTest|NotificationPreferencesTest|NotificationCenterTriggersTest|SubmitEventParentProgramTest|DawahShareImpactTest|ContributionWorkflowServiceTest|ContributionWorkflowActionsTest|MemberInvitationActionsTest|MemberInvitationUiTest)'` => **90 passed (459 assertions)**
  - `vendor/bin/phpstan analyse --ansi tests/Feature/RefactorTest.php database/migrations/2026_01_10_000019_create_event_submissions_table.php database/migrations/2026_01_10_000024_create_registrations_table.php database/migrations/2026_01_11_101956_create_permission_tables.php database/migrations/2026_01_23_031834_create_references_table.php database/migrations/2026_02_09_190614_create_notifications_table.php` => **No errors**
  - `git diff --check` => **No diff formatting issues**
  - wider spot-check:
    - `vendor/bin/pest --parallel --compact tests/Feature --filter='(RefactorTest|NotificationCenterApiTest|NotificationPreferencesTest|NotificationCenterTriggersTest|SubmitEventParentProgramTest|DawahShareImpactTest|ContributionWorkflowServiceTest|ContributionWorkflowActionsTest|ContributionPagesTest|MemberInvitationActionsTest|MemberInvitationUiTest)'` => **1 unrelated existing failure in `tests/Feature/ContributionPagesTest.php:163` (`proposed_data.address.line1` expected `No. 8, Jalan Baru`, got `null`)**

# Migration Audit

- [x] Convert remaining local migration `json(...)` columns to `jsonb(...)`
- [x] Audit column usage for `event_submissions`, `moderation_reviews`, and `reports`
- [x] Remove any verified-unused columns and re-run focused verification

## Review

- Root cause:
  - the migration cleanup still left a mix of `json(...)` and `jsonb(...)` definitions across local migrations, which makes the schema inconsistent for PostgreSQL-first usage
  - the older workflow tables had not been re-audited after the squash, so dead compatibility columns could survive even if runtime code no longer needed them
- Fix:
  - changed every remaining local `json(...)` migration column to `jsonb(...)`, including settings payloads, contribution request snapshots, media metadata, audits, tags, activity log properties, deleted-model snapshots, and AI metadata
  - audited the columns in:
    - `database/migrations/2026_01_10_000019_create_event_submissions_table.php`
    - `database/migrations/2026_01_10_000020_create_moderation_reviews_table.php`
    - `database/migrations/2026_01_10_000021_create_reports_table.php`
  - kept all `event_submissions` columns because `event_id`, `submitted_by`, `submitter_name`, and `notes` are all exercised by the submission flows, dashboards, and display tests
  - kept all `reports` columns because `reporter_id`, `reporter_fingerprint`, `handled_by`, `entity_type`, `entity_id`, `category`, `status`, and `resolution_note` are all used by report submission, moderation, notifications, and admin resources
  - removed only `moderation_reviews.reviewer_id`, which had become dead compatibility state; runtime writes and reads use `moderator_id`, so I also updated:
    - `app/Models/User.php`
    - `app/Models/ModerationReview.php`
    - `database/factories/ModerationReviewFactory.php`
    - `database/seeders/ModerationReviewSeeder.php`
    - `tests/Feature/RefactorTest.php`
- Verification:
  - `rg -n -- "->json\\(|json\\('" database/migrations` => **no output**
  - `vendor/bin/pest --parallel --compact tests/Feature --filter='(RefactorTest|EventSubmissionDisplayTest|SubmitEventNotesTest|SubmitEventNotificationTest|ContributionWorkflowServiceTest|ContributionWorkflowActionsTest|ModerationServiceTest|ModerationQueueTest|EventModerationActionsTest|AhliEventApprovalTest|ReportActionsTest|ReportApiModerationTest|NotificationEmailRoutingTest)'` => **102 passed (469 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Models/ModerationReview.php app/Models/User.php database/factories/ModerationReviewFactory.php database/seeders/ModerationReviewSeeder.php tests/Feature/RefactorTest.php` => **No errors**
  - `git diff --check` => **No diff formatting issues**

# Institution Card Location Order

- [x] Trace the institution index card location formatter override
- [x] Switch the public institution card hierarchy to subdistrict, district, state
- [x] Update focused institution index assertions and verify the page behavior

## Review

- Root cause:
  - the public `/institusi` card view was overriding `AddressHierarchyFormatter::parts()` with `['state', 'district', 'subdistrict']`, so cards rendered broad-to-specific order even though the shared formatter already defaults to `subdistrict, district, state`
- Fix:
  - removed the explicit override in `resources/views/components/pages/institutions/⚡index.blade.php` so institution cards now use the shared default order, producing location strings like `Bagan Sena, Kulim, Kedah`
  - updated the focused institution index assertions to lock in the new public-card order and keep the duplicate-subdistrict behavior covered
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php` => **13 passed (52 assertions)**
  - `php -l tests/Feature/InstitutionIndexTest.php` => **No syntax errors**
  - `git diff --check` => **No diff formatting issues**
  - `vendor/bin/phpstan analyse --ansi tests/Feature/InstitutionIndexTest.php` => **tooling limitation: current PHPStan config reports `No files found to analyse` for this Pest target**

# Google Social Email Verification

- [x] Inspect the Google Socialite callback and current verification behavior
- [x] Ensure Google sign-in marks matched existing users as email-verified when appropriate
- [x] Add regression coverage for new and existing-user Google auth verification behavior
- [x] Run focused verification for the Socialite auth flow

## Review
- Root cause:
  - the Google Socialite callback only set `email_verified_at` during brand-new user creation
  - users who already existed locally, or who already had a linked Google social account, could complete Google sign-in while still remaining unverified
- Fix:
  - moved the verification step to run after any successful Google account resolution in `app/Http/Controllers/Auth/SocialiteController.php`
  - only call `markEmailAsVerified()` when the resolved user is still unverified and Google returned a non-empty email address
  - extended `tests/Feature/SocialiteAuthTest.php` to assert verification for:
    - newly created Google users
    - existing local users linked by email
    - existing linked Google social accounts
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/SocialiteAuthTest.php` => **6 passed (28 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Http/Controllers/Auth/SocialiteController.php tests/Feature/SocialiteAuthTest.php` => **No errors**
  - `git diff --check` => **No diff formatting issues**

# Institution Address Comma Spacing

- [x] Trace the institution detail address rendering path
- [x] Normalize address line spacing so commas do not receive preceding spaces
- [x] Add focused regression coverage for trimmed address access
- [x] Run focused verification for the touched model and test

## Review
- Root cause:
  - the institution detail page renders `line1` and `line2` directly, and address text was not normalized at the model layer
  - when stored address data contained trailing or leading whitespace, joins like `{{ $address->line1 }}, {{ $address->line2 }}` produced visible output such as `Persiaran Masjid , Seksyen 14`
- Fix:
  - added trimmed `line1` and `line2` accessors/mutators on `app/Models/Address.php` so address lines are normalized on both read and write
  - added a focused regression in `tests/Unit/HasAddressAccessorTest.php` to prove trailing and leading spaces are removed and the combined string renders as `Persiaran Masjid, Seksyen 14`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Unit/HasAddressAccessorTest.php` => **2 passed (7 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Models/Address.php tests/Unit/HasAddressAccessorTest.php` => **No errors**
  - `git diff --check` => **No diff formatting issues**

# Institution Address Hierarchy

- [x] Inspect the institution show contact block and confirm why hierarchy fields are missing
- [x] Patch the contact block to render subdistrict, district, and state cleanly
- [x] Add focused institution show coverage for street plus hierarchy output
- [x] Run focused verification for the institution show page change

## Review
- Root cause:
  - the institution show contact block rendered only `line1`, `line2`, `postcode`, optional `city`, and `state`
  - even though the page already computed the shared `locationString`, that block never used it, so `subdistrict` and `district` were omitted from the visible address
- Fix:
  - updated `resources/views/components/pages/institutions/⚡show.blade.php` to compose three address layers:
    - street line from `line1` + `line2`
    - postcode/city line
    - hierarchy line from the shared formatter (`subdistrict, district, state`)
  - added a focused regression in `tests/Feature/InstitutionShowPageTest.php` that proves an address with `Shah Alam` and `Petaling` renders in order with the street and postcode
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionShowPageTest.php` => **24 passed (77 assertions)**
  - `php -l tests/Feature/InstitutionShowPageTest.php` => **No syntax errors**
  - `vendor/bin/phpstan analyse --ansi tests/Feature/InstitutionShowPageTest.php` => **tooling limitation: current PHPStan config reports `No files found to analyse` for this Pest target**
  - `git diff --check` => **No diff formatting issues**

# Composer AIArmada Repositories

- [x] Inspect root composer manifest and lockfile for local AIArmada path repositories
- [x] Remove local AIArmada path repository entries from composer.json
- [x] Refresh the lockfile so AIArmada packages no longer resolve from local paths
- [x] Verify composer manifests no longer reference local path repositories

## Review
- Root cause:
  - the root `composer.json` still declared five local `path` repositories under `/Users/Saiffil/Herd/commerce/packages/...` for the `aiarmada/*` packages
  - the lockfile was also pinned to `path` installs, which would keep production `composer install` pointing at a non-existent local filesystem path even after editing the manifest
- Fix:
  - removed the five local `path` repository entries from `composer.json`
  - refreshed only the `aiarmada/*` packages in `composer.lock` via Composer so they now resolve from GitHub `git`/`zip` sources instead of local paths
  - Composer also advanced `livewire/livewire` from `v4.2.1` to `v4.2.2` as part of the targeted dependency refresh
- Verification:
  - `composer update aiarmada/affiliates aiarmada/commerce-support aiarmada/filament-authz aiarmada/filament-signals aiarmada/signals --with-all-dependencies --no-scripts`
  - `composer validate --no-check-publish` => **./composer.json is valid**
  - `rg -n 'aiarmada/.+|"type": "path"|"type"\\s*:\\s*"path"' composer.json composer.lock` => **no remaining `path` entries**
  - `git diff --check` => **No diff formatting issues**

# Institution Address Line Order

- [x] Adjust the institution contact address block to the requested three-line order
- [x] Update the focused institution show regression for locality + postcode on line two
- [x] Run focused verification for the revised address order

## Review
- Root cause:
  - the previous hierarchy patch put postcode on its own line and rendered the full hierarchy string on the next line
  - the requested UI order is more specific: `street`, then `subdistrict/locality + postcode`, then `district + state`
- Fix:
  - updated `resources/views/components/pages/institutions/⚡show.blade.php` so the contact block now composes:
    - `line1, line2`
    - first hierarchy part (or city fallback) with postcode
    - remaining hierarchy parts
  - updated `tests/Feature/InstitutionShowPageTest.php` to lock the output to:
    - `Persiaran Masjid, Seksyen 14`
    - `Shah Alam, 40000`
    - `Petaling, Selangor`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionShowPageTest.php` => **24 passed (77 assertions)**
  - `php -l tests/Feature/InstitutionShowPageTest.php` => **No syntax errors**
  - `git diff --check` => **No diff formatting issues**

# Institution Contribution Google Places Picker

- [x] Normalize the dedicated institution create form to nested `data.address.*` state
- [x] Add a config-gated Google Places search-and-preview picker with manual fail-open fallback
- [x] Persist picker-derived `lat`, `lng`, and `google_place_id` through contribution address flows
- [x] Add focused tests for picker gating, place mapping, and nested create submission
- [x] Run targeted verification for the touched PHP, Livewire, and Blade paths

## Review
- Root changes:
  - normalized `SubmitInstitution` to the same nested `data.address.*` payload shape already used by suggest-update flows, so address edits are persisted through the existing mutation service instead of being silently dropped on create
  - added a config-gated institution-only location picker view powered by Google Maps JavaScript + Places API (New) that uses `PlaceAutocompleteElement`, shows a preview map, and auto-fills the nested address payload while keeping the manual address fields editable below it
  - added a new `ResolveGooglePlaceSelectionAction` to map Google place details into local `state_id`, `district_id`, and `subdistrict_id` values without any server-side Google API call, leaving ambiguous matches empty instead of guessing
  - extended the shared address creation/mutation paths to persist `lat`, `lng`, and `google_place_id` alongside the existing `google_maps_url`
- Rollback/cost control:
  - added `services.google.maps_api_key` and `services.google.places_enabled`, plus matching `.env.example` entries (`GOOGLE_MAPS_API_KEY`, `GOOGLE_PLACES_ENABLED`)
  - when the flag is off or the key is missing, the picker is not rendered and the page falls back to the existing manual address form
  - when the Google script or place lookup fails at runtime, the picker disables itself for that page session and the manual form remains usable
- Test coverage added/updated:
  - `tests/Feature/InstitutionContributionLocationPickerTest.php`
  - `tests/Unit/ResolveGooglePlaceSelectionActionTest.php`
  - `tests/Feature/InstitutionIndexTest.php`
  - `tests/Feature/SharedFormSchemaTest.php`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/ContributionPagesTest.php` => **21 passed (86 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php` => **13 passed (56 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionContributionLocationPickerTest.php` => **3 passed (17 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SharedFormSchemaTest.php` => **9 passed (52 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Unit/ResolveGooglePlaceSelectionActionTest.php` => **2 passed (14 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Actions/Location/ResolveGooglePlaceSelectionAction.php app/Forms/InstitutionContributionFormSchema.php app/Forms/SharedFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php app/Services/ContributionEntityMutationService.php tests/Feature/InstitutionContributionLocationPickerTest.php tests/Feature/InstitutionIndexTest.php tests/Feature/SharedFormSchemaTest.php tests/Unit/ResolveGooglePlaceSelectionActionTest.php` => **No errors**
  - `vendor/bin/pint --test app/Actions/Location/ResolveGooglePlaceSelectionAction.php app/Forms/InstitutionContributionFormSchema.php app/Forms/SharedFormSchema.php app/Livewire/Pages/Contributions/SubmitInstitution.php app/Services/ContributionEntityMutationService.php tests/Feature/InstitutionContributionLocationPickerTest.php tests/Feature/InstitutionIndexTest.php tests/Feature/SharedFormSchemaTest.php tests/Unit/ResolveGooglePlaceSelectionActionTest.php` => **pass**
  - `git diff --check` => **No diff formatting issues**

# Event Filter Country Default

- [x] Reproduce the live `/majlis` default-country behavior with a browser-style timezone cookie
- [x] Fix server-side handling of the browser-set `user_timezone` cookie
- [x] Add an HTTP-level regression test for the default country selection path
- [x] Re-verify the page through tests and a live browser pass

## Review
- Root cause:
  - the new preferred-country resolver worked in isolation, but the browser-set `user_timezone` cookie was still going through Laravel's cookie encryption middleware
  - because that cookie is written directly by frontend JavaScript, Laravel could not decrypt it on the next request and treated it as missing, so `/majlis` fell back to Malaysia
- Fix:
  - configured `bootstrap/app.php` to exclude `user_timezone` from cookie encryption so browser-written timezone cookies survive the request lifecycle
  - added a focused HTTP regression in `tests/Feature/EventSearchTest.php` using `withUnencryptedCookie()` against `/majlis` to match real browser behavior instead of only testing the resolver in isolation
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventSearchTest.php --filter="defaults the majlis country filter from an unencrypted browser timezone cookie|filters events by country|displays the events index page"` => **3 passed (14 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Unit/PreferredCountryResolverTest.php` => **3 passed (3 assertions)**
  - `vendor/bin/phpstan analyse --ansi bootstrap/app.php app/Support/Location/PreferredCountryResolver.php tests/Feature/EventSearchTest.php tests/Unit/PreferredCountryResolverTest.php` => **No errors**
  - `vendor/bin/pint --test bootstrap/app.php tests/Feature/EventSearchTest.php tests/Unit/PreferredCountryResolverTest.php app/Support/Location/PreferredCountryResolver.php` => **pass**
  - `curl -sk -H 'Cookie: user_timezone=Asia/Jakarta' https://majlisilmu.test/majlis` => **server response now emits `country_id=103` and `Indonesia`**
  - live browser reload on `https://majlisilmu.test/majlis` with `user_timezone=Asia/Jakarta` => **active filter shows `Indonesia` and saved-search link uses `country_id=103`**

# Institution Filter Country Default

- [x] Inspect the public institutions index location filter flow and identify where country should slot in
- [x] Add country selection plus preferred-country defaulting to the institutions index
- [x] Add focused regression coverage for country filtering and browser-style timezone defaulting
- [x] Verify with focused tests and a live browser pass on `/institusi`

## Review
- Root changes:
  - added `country_id` as a first-class URL-backed filter on the public institutions Volt page in `resources/views/components/pages/institutions/⚡index.blade.php`
  - defaulted that filter from `PreferredCountryResolver`, so `/institusi` now follows the same priority as `/majlis`: saved timezone, then `CF-IPCountry`, then Malaysia
  - replaced the hard-coded Malaysia-only states query with cached country + state option lists using the existing `countries_all_v1` and `states_all_v1` cache keys
  - made country changes reset `state_id`, `district_id`, and `subdistrict_id`, and threaded `country_id` into the institutions address scope query
  - updated the institutions loading targets so changing country triggers the same loading state as the other search and location filters
- Test coverage:
  - added a country-filter regression in `tests/Feature/InstitutionIndexTest.php`
  - added an HTTP-level browser-cookie regression for the timezone-derived default country on `/institusi`
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/InstitutionIndexTest.php --filter="shows location scope controls on institution index|filters institutions by country|defaults institutions country filter from an unencrypted browser timezone cookie"` => **3 passed (10 assertions)**
  - `php -l tests/Feature/InstitutionIndexTest.php` => **No syntax errors**
  - `vendor/bin/pint --test resources/views/components/pages/institutions/⚡index.blade.php tests/Feature/InstitutionIndexTest.php` => **pass**
  - `git diff --check -- resources/views/components/pages/institutions/⚡index.blade.php tests/Feature/InstitutionIndexTest.php tasks/todo.md` => **No diff formatting issues**
  - `curl -sk -H 'Cookie: user_timezone=Asia/Jakarta' https://majlisilmu.test/institusi` => **Livewire snapshot now carries `country_id=103`**
  - live browser reload on `https://majlisilmu.test/institusi` with `user_timezone=Asia/Jakarta` => **country select shows `Indonesia`, and the state dropdown is scoped to Indonesian states**

# Submit Event Registration Default

- [x] Inspect the institution submit-event flow and all `event_settings` creation paths for unintended registration defaults
- [x] Make standalone submitted events default to no registration unless a parent program explicitly requires it
- [x] Prevent admin relation sync from creating `event_settings` rows that silently inherit `registration_required = true`
- [x] Add focused regressions for standalone submissions and relation sync behavior
- [x] Verify with targeted Pest coverage, PHPStan, and formatting checks

## Review
- Root cause:
  - the institution submit-event flow only copied `event_settings` when a parent program existed, so standalone submissions never pinned a safe registration state of their own
  - the shared `SyncEventResourceRelationsAction` created `event_settings` rows by writing only `registration_mode`; when that happened, the database default on `registration_required` could silently turn registration on
  - the advanced parent-program builder also defaulted `registration_required` to `true`, which was unsafe while the registration product is still incomplete
- Fix:
  - added an explicit `persistRegistrationSettings()` step in the submit-event flow so standalone submissions create `event_settings` with `registration_required = false` and `registration_mode = event`, while parent-child submissions still inherit the parent program settings
  - changed `SyncEventResourceRelationsAction` to preserve the current `registration_required` value and default new settings rows to `false` instead of inheriting the table default
  - changed the advanced builder defaults so new parent programs no longer start with registration enabled unless someone intentionally turns it on
  - extended tests to assert the institution-scoped submission path stores `registration_required = false` and that the public event page does not render the registration UI for those events
- Verification:
  - `vendor/bin/pest --parallel --compact tests/Feature/EventActionsTest.php` => **5 passed (22 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/AdvancedEventCreationTest.php` => **5 passed (28 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SubmitEventParentProgramTest.php` => **2 passed (19 assertions)**
  - `vendor/bin/pest --parallel --compact tests/Feature/SubmitEventEntityAccessTest.php` => **5 passed (30 assertions)**
  - `vendor/bin/phpstan analyse --ansi app/Actions/Events/SyncEventResourceRelationsAction.php app/Actions/Events/ResolveAdvancedBuilderContextAction.php tests/Feature/EventActionsTest.php tests/Feature/AdvancedEventCreationTest.php tests/Feature/SubmitEventEntityAccessTest.php` => **No errors**
  - `vendor/bin/pint --test app/Actions/Events/SyncEventResourceRelationsAction.php app/Actions/Events/ResolveAdvancedBuilderContextAction.php tests/Feature/EventActionsTest.php tests/Feature/AdvancedEventCreationTest.php tests/Feature/SubmitEventEntityAccessTest.php` => **pass**
  - `git diff --check -- app/Actions/Events/SyncEventResourceRelationsAction.php app/Actions/Events/ResolveAdvancedBuilderContextAction.php resources/views/components/pages/submit-event/create.blade.php tests/Feature/EventActionsTest.php tests/Feature/AdvancedEventCreationTest.php tests/Feature/SubmitEventEntityAccessTest.php tasks/todo.md` => **No diff formatting issues**
