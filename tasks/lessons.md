# Lessons

- When removing Laravel packages, clear and regenerate the exact PHPUnit cache targets (`APP_PACKAGES_CACHE`, `APP_SERVICES_CACHE`) instead of only running `php artisan package:discover` with `APP_ENV=testing`.
- Parallel Pest runs can expose Blaze compiled-view race issues; keep `BLAZE_ENABLED=false` in `phpunit.xml` unless test-specific Blaze coverage is explicitly required.
- When users request "badges under About", confirm the target domain model (tag categories vs generic metadata) before expanding scope; prefer preserving existing taxonomy UI with fixed category ordering.
- For avatar "cropping" complaints, verify the source file alpha/canvas first; if the asset is already circular PNG, solve with a dedicated flattened card conversion instead of only CSS container tweaks.
- For single-speaker hero cards, "prominent avatar" usually requires layout changes (wider media pane + larger avatar block), not only image-fit adjustments.
- For "premium/classy" UI requests on featured cards, start with restrained ring thickness and tighter media-side width first; users generally prioritize typography breathing room over oversized decorative avatar framing.
- When upgrading speaker card aesthetics, align both single-speaker and multi-speaker variants in the same pass so the page doesn't feel visually inconsistent between event records.
- For multi-speaker premium grids, users still expect avatar presence; avoid over-shrinking profile images when improving typography and card materials.
- Always verify speaker-card changes on both a single-speaker event and a multi-speaker event; placeholder media fallbacks can behave differently and hide regressions.
- To make avatars feel "much bigger," increase avatar diameter and overlap while reducing header-panel height; changing only one of these is usually not enough.
