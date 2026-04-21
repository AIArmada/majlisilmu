---
title: API / MCP / Filament Capability Matrix
verified_at: 2026-04-20
purpose: Canonical parity map for public workflow API, generic admin HTTP API, admin/member MCP, and the Filament admin/Ahli panels.
machine_readable_companion: docs/MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.json
---

# API / MCP / Filament Capability Matrix

This document is the human-readable source of truth for capability parity across MajlisIlmu surfaces.

Machine-readable companion: `docs/MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.json`.

Use this file to answer four questions:

- Which resources are actually registered at runtime in the admin and Ahli panels?
- Which resources are structurally writable through the generic admin HTTP API and MCP servers?
- Which capabilities live only in workflow-oriented public/authenticated API routes?
- Which transport rules matter for preview, media, and record traversal?

If code changes here, update this Markdown file, the JSON companion, and `tests/Unit/CrudComparisonDocsTest.php` in the same change set.

## Interpretation rules

- **Runtime panel registration wins.** The admin registry is built from `Filament::getPanel('admin')->getResources()`, not from a directory listing. Vendor/plugin resources count.
- **Structural support and actor permission are different things.** `getPages()` and generic write support tell you whether a route/tool exists; the current actor still needs to pass middleware and model policy checks.
- **Public and authenticated API are workflow-first.** They expose user-facing workflows and contracts, not a generic mirror of every Filament resource.
- **Admin HTTP API and Admin MCP share the same mutation backend.** The writable resource set comes from `AdminResourceMutationService`.
- **Member MCP is intentionally narrower.** It is Ahli-scoped, update-only, and has no related-record traversal tool.
- **Panel-only operations stay panel-only.** Delete, restore, replicate, and reorder are not exposed by the generic admin HTTP API or the current MCP tool sets.

## Canonical source files

### Runtime inventory and metadata

- `app/Support/Api/Admin/AdminResourceRegistry.php`
- `app/Support/Api/Member/MemberResourceRegistry.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/Filament/AhliPanelProvider.php`
- `app/Filament/Resources/**/*Resource.php`
- `app/Filament/Ahli/Resources/**/*Resource.php`

### Generic admin write path

- `routes/api.php`
- `app/Support/Api/Admin/AdminResourceService.php`
- `app/Support/Api/Admin/AdminResourceMutationService.php`
- `app/Http/Controllers/Api/Admin/ResourceController.php`

### MCP transport and schema shaping

- `routes/ai.php`
- `app/Mcp/Servers/AdminServer.php`
- `app/Mcp/Servers/MemberServer.php`
- `app/Mcp/Tools/Admin/*`
- `app/Mcp/Tools/Member/*`
- `app/Support/Mcp/McpWriteSchemaFormatter.php`
- `app/Support/Mcp/McpFilePayloadNormalizer.php`

### Verification

- `tests/Feature/Api/Admin/AdminApiTest.php`
- `tests/Feature/Mcp/AdminServerTest.php`
- `tests/Feature/Mcp/MemberServerTest.php`
- `tests/Feature/AdminResourcesCoverageTest.php`
- `tests/Unit/CrudComparisonDocsTest.php`

## Surface summary

| Surface | Auth boundary | Contract style | Canonical purpose |
| --- | --- | --- | --- |
| Public API | public plus route-specific throttles | workflow / discovery contracts | discovery, search, detail pages, auth entrypoints, public submit-event, guest-safe reads |
| Authenticated API | `auth:sanctum` | workflow / self-service contracts | contributions, reports, follows, saved searches, notifications, account settings, institution workspace, event state |
| Admin API | `auth:sanctum` + `EnsureAdminApiAccess` | generic resource HTTP contract | admin resource manifest, list/get/meta, related records, schema-guided create/update |
| Admin MCP | `auth:sanctum,api` + `EnsureAdminMcpAccess` | generic MCP tool contract | admin resource discovery, list/get/meta, related records, schema-guided create/update |
| Member MCP | `auth:sanctum,api` + `EnsureMemberMcpAccess` | scoped MCP tool contract | Ahli-scoped discovery, list/get/meta, schema-guided update |
| Admin Panel | Filament admin panel | UI/resource surface | full operational UI, relation managers, deletes/restores/reorders where the resource supports them |
| Ahli Panel | Filament Ahli panel | UI/resource surface | member-scoped editing workspace for owned or linked records |

## Workflow-first API families

The public/authenticated API is not a second generic CRUD registry. It is a set of workflow contracts.

### Public discovery and form contracts

- `GET /api/v1/manifest`
- `GET /api/v1/forms/submit-event`
- `GET /api/v1/forms/contributions/institutions`
- `GET /api/v1/forms/contributions/speakers`
- `GET /api/v1/catalogs/*`
- `GET /api/v1/search`
- `GET /api/v1/institutions*`
- `GET /api/v1/speakers*`
- `GET /api/v1/venues/{venueKey}`
- `GET /api/v1/references/{referenceKey}`
- `GET /api/v1/series/{series}`
- `GET /api/v1/events*`

### Public mutation workflows

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/social/google`
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`
- `POST /api/v1/submit-event`
- `POST /api/v1/events/{event}/registrations`

### Authenticated workflow families

- account settings and MCP token self-service
- GitHub issue reporting for API and MCP/API integration feedback
- authenticated contribution create/suggest/review actions
- membership claims
- advanced event submission
- follows
- institution workspace member management
- reports
- current-user and per-event state (`/me/events/*`, `/events/{event}/me`, saved, going, check-ins, registrations)
- saved searches
- notifications and notification destinations/settings

## Runtime admin resource inventory (30 registered resources)

This is the runtime admin panel inventory, not just the local `app/Filament/Resources` directory. The generic admin HTTP API and Admin MCP sit on top of this inventory and then filter it per actor.

### Local app resources

| Resource key | Source | Pages | Generic admin write |
| --- | --- | --- | --- |
| `ai-model-pricings` | app | `index`, `create`, `edit` | no |
| `ai-usage-logs` | app | `index` | no |
| `audits` | app | `index`, `view` | no |
| `contribution-requests` | app | `index`, `view` | no |
| `countries` | app | `index`, `create`, `edit` | no |
| `districts` | app | `index`, `create`, `edit` | no |
| `donation-channels` | app | `index`, `create`, `edit` | no |
| `events` | app | `index`, `create`, `view`, `edit` | yes |
| `inspirations` | app | `index`, `create`, `edit` | no |
| `institutions` | app | `index`, `create`, `view`, `edit` | yes |
| `membership-claims` | app | `index`, `view` | no |
| `references` | app | `index`, `create`, `edit` | yes |
| `reports` | app | `index`, `create`, `edit` | no |
| `series` | app | `index`, `create`, `edit` | no |
| `slug-redirects` | app | `index`, `create`, `view`, `edit` | no |
| `spaces` | app | `index`, `create`, `view`, `edit` | no |
| `speakers` | app | `index`, `create`, `view`, `edit` | yes |
| `states` | app | `index`, `create`, `edit` | no |
| `subdistricts` | app | `index`, `create`, `edit` | yes |
| `tags` | app | `index`, `create`, `edit` | no |
| `users` | app | `index`, `create`, `view`, `edit` | no |
| `venues` | app | `index`, `create`, `view`, `edit` | yes |

### Vendor/plugin resources

These are registered at runtime and therefore part of the admin registry surface too.

| Resource key | Package family | Pages | Generic admin write |
| --- | --- | --- | --- |
| `permissions` | `aiarmada/filament-authz` | `index`, `create`, `edit` | no |
| `roles` | `aiarmada/filament-authz` | `index`, `create`, `edit` | no |
| `saved-signal-reports` | `aiarmada/filament-signals` | `index`, `create`, `edit` | no |
| `signal-alert-logs` | `aiarmada/filament-signals` | `index` | no |
| `signal-alert-rules` | `aiarmada/filament-signals` | `index`, `create`, `edit` | no |
| `signal-goals` | `aiarmada/filament-signals` | `index`, `create`, `edit` | no |
| `signal-segments` | `aiarmada/filament-signals` | `index`, `create`, `edit` | no |
| `tracked-properties` | `aiarmada/filament-signals` | `index`, `create`, `edit` | no |

### What “generic admin write” means here

`yes` means the resource is structurally enabled in `AdminResourceMutationService` for:

- `GET /api/v1/admin/{resourceKey}/schema`
- `POST /api/v1/admin/{resourceKey}`
- `PUT /api/v1/admin/{resourceKey}/{recordKey}`
- `admin-get-write-schema`
- `admin-create-record`
- `admin-update-record`

It does **not** mean every admin-facing actor can write it. Actual create/update access is still policy-driven per request.

## Runtime Ahli resource inventory (4 registered resources)

| Resource key | Pages | Generic member write | Scope |
| --- | --- | --- | --- |
| `events` | `index`, `view`, `edit` | yes | member-owned or member-linked events |
| `institutions` | `edit` | yes | institutions the current member belongs to |
| `references` | `index`, `edit` | yes | references the current member belongs to |
| `speakers` | `index`, `view`, `edit` | yes | speakers the current member belongs to |

All four Ahli resources are update-capable through Member MCP. None expose generic member create or generic member delete.

## Structural write-capable intersection

These six resources are the entire current generic admin write whitelist.

| Resource | Admin API | Admin MCP | Member MCP | Admin panel pages | Ahli panel pages | Workflow overlap |
| --- | --- | --- | --- | --- | --- | --- |
| `events` | `R + meta + related + S + C + U + P` | `R + meta + related + S + C + U + P` | `R + S + U` | `index`, `create`, `view`, `edit` | `index`, `view`, `edit` | public read/search/detail; public submit-event create; authenticated saved/going/check-ins/registrations |
| `institutions` | `R + meta + related + S + C + U + P` | `R + meta + related + S + C + U + P` | `R + S + U` | `index`, `create`, `view`, `edit` | `edit` | public read/detail; authenticated contribution create/suggest; institution workspace; follows |
| `speakers` | `R + meta + related + S + C + U + P` | `R + meta + related + S + C + U + P` | `R + S + U` | `index`, `create`, `view`, `edit` | `index`, `view`, `edit` | public read/detail; authenticated contribution create/suggest; follows |
| `references` | `R + meta + related + S + C + U + P` | `R + meta + related + S + C + U + P` | `R + S + U` | `index`, `create`, `edit` | `index`, `edit` | public read/detail; authenticated suggest update; follows |
| `venues` | `R + meta + related + S + C + U + P` | `R + meta + related + S + C + U + P` | not exposed | `index`, `create`, `view`, `edit` | not exposed | public read/detail plus public venue catalogs |
| `subdistricts` | `R + meta + related + S + C + U + P` | `R + meta + related + S + C + U + P` | not exposed | `index`, `create`, `edit` | not exposed | public and admin catalog lookups only |

## Read-only generic admin groups

These resources are readable through the generic admin registry, but not writable through the current generic admin HTTP/MCP write path.

- **Geography:** `countries`, `states`, `districts`
- **Directory/content extras:** `donation-channels`, `series`, `spaces`, `tags`, `inspirations`
- **Moderation/workflow records:** `reports`, `membership-claims`, `contribution-requests`
- **System/auth/ops:** `ai-model-pricings`, `ai-usage-logs`, `audits`, `users`, `slug-redirects`, `roles`, `permissions`, `tracked-properties`, `signal-goals`, `signal-segments`, `saved-signal-reports`, `signal-alert-rules`, `signal-alert-logs`

## MCP media and preview semantics

This is the area where the previous version drifted the most.

### Admin HTTP API

- Write schemas come from `GET /api/v1/admin/{resourceKey}/schema`.
- Preview uses `validate_only=1` on the `POST` or `PUT` request.
- Validation failures in validate-only mode now return machine-readable remediation details: `fix_plan`, `remaining_blockers`, `normalized_payload_preview`, and `can_retry`.
- Schema `content_type` is resource-specific:
	- `multipart/form-data` for media-capable resources such as `events`, `institutions`, `speakers`, `references`, and `venues`
	- `application/json` for `subdistricts`

### Admin MCP

- Write schemas are reformatted to MCP JSON contracts by `McpWriteSchemaFormatter`.
- Create/update preview is supported through the `validate_only` boolean tool argument.
- Validation failures in validate-only mode now return the same remediation fields as the admin HTTP API so AI clients can recover in one retry loop.
- When a schema advertises file fields, MCP uploads use `json_base64_descriptor`, not multipart.
- Descriptor normalization and staging is implemented by `McpFilePayloadNormalizer`.
- Destructive media clear flags such as `clear_cover`, `clear_avatar`, `clear_gallery`, and siblings are intentionally removed from MCP schema fields and rejected by the write tools.

### Member MCP

- Update only; no create tool.
- No `validate_only` preview path today.
- No generic related-record tool today.
- Media fields follow the same descriptor transport as admin MCP when the schema advertises them.
- Destructive `clear_*` media flags are also rejected here.

## Important asymmetries

- **Runtime admin inventory is broader than local app files.** Because the registry uses live Filament panel registration, vendor/plugin resources are part of the admin surface.
- **Page keys do not equal permission.** `getPages()` and generic write support describe structural capability; per-actor authorization still comes from middleware and model policies.
- **Admin API and Admin MCP share one mutation whitelist.** If `AdminResourceMutationService` changes, both transports change together.
- **Member MCP is narrower than admin.** It maps only to the four Ahli resources and exposes updates only.
- **Public contributions and reports are authenticated workflows.** The API exposes form discovery publicly, but the actual create routes for contributions and reports live behind `auth:sanctum`.
- **Delete, restore, replicate, and reorder remain panel-led.** Do not assume generic admin HTTP or MCP parity for those operations.
- **Use returned `route_key` values for record-specific admin URLs and MCP record keys whenever available.**

## Maintenance rule

Whenever any of these change:

- Filament panel resource registration
- `AdminResourceMutationService`
- `MemberResourceMutationService`
- `routes/api.php`
- `routes/ai.php`
- MCP schema formatter or file normalizer behavior

update this Markdown file, the JSON companion, and `tests/Unit/CrudComparisonDocsTest.php` in the same change set.
