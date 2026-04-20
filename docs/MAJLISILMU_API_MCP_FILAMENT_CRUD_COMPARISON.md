---
title: API / MCP / Filament CRUD Comparison Matrix
generated_at: 2026-04-20
purpose: Compare CRUD and workflow parity across API, MCP, and Filament panels.
---

# API / MCP / Filament CRUD Comparison Matrix

This is the living comparison map for the current codebase.

Machine-readable companion: `docs/MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.json`.

The goal is not just to know which surface exists, but to see where the same data family can be read, created, updated, deleted, or only reviewed.

## Legend

| Code | Meaning |
| --- | --- |
| `R` | read / list / get |
| `S` | schema endpoint or schema tool available |
| `C` | create |
| `U` | update |
| `D` | delete |
| `P` | `validate_only` preview available |
| `panel pages` | Filament pages discovered from the resource class |
| `canCreate` | Filament policy gate, not just route presence |

## Surface summary

| Surface | Auth | Shape | What it is |
| --- | --- | --- | --- |
| Public API | public for discovery, bearer-authenticated for some mutations | workflow / contract based | public forms, catalogs, submissions, and self-service state changes |
| Authenticated API | `auth:sanctum` | workflow / contract based | current-user workflows, state transitions, and moderation-side actions |
| Admin API | `auth:sanctum` + admin gate | generic resource interface | the admin Filament resource registry exposed over HTTP |
| Admin MCP | `auth:sanctum` + admin gate | generic tool interface | the same admin resource registry exposed through MCP tools |
| Member MCP | `auth:sanctum` + member gate | scoped tool interface | Ahli-scoped subset of the admin resource contract |
| Admin Panel | Filament admin panel | UI/resource surface | broader operational CRUD, delete/restore, and relation management |
| Ahli Panel | Filament Ahli panel | UI/resource surface | narrower member workspace for owned/member-linked records |

## API workflow families

The public and authenticated API layers are mostly **workflow-oriented**, not generic CRUD. They should be treated as contract families rather than as a second copy of the Filament resource registry.

- **Discovery / read contracts**: `manifest`, `forms/*`, `catalogs/*`, `search`, `institutions`, `speakers`, `venues`, `references`, `series`.
- **Public create contracts**: `submit-event`, `contributions/institutions`, `contributions/speakers`, `reports`.
- **Authenticated state/workflow contracts**: `saved-searches`, `follows`, `events/{event}/saved`, `events/{event}/going`, `membership-claims`, `contributions/*`, `notifications`, `notification-settings`, `account-settings`, `institution-workspace`, `mcp-tokens`, `event registrations`, `check-ins`, `advanced-events`.

The important consequence is that the API is **not** trying to mirror every Filament resource one-for-one. It exposes the workflows that users and clients actually need.

## Writable entity intersection

These are the data families where CRUD parity matters most because they are shared across multiple surfaces.

| Family | Public API | Admin API | Admin MCP | Member MCP | Admin Panel | Ahli Panel | Drift note |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `events` | submit-event create + public read/search/detail | `R + S + C + U + P` | `R + S + C + U + P` | `U` only, Ahli-scoped | `index / create / view / edit`, but `canCreate=false` | `index / view / edit`, `canCreate=false` | panel create exists but is policy-disabled; MCP media uploads stay unsupported |
| `institutions` | contribution create + public read/detail | `R + S + C + U + P` | `R + S + C + U + P` | `U` only, Ahli-scoped | `index / create / view / edit`, but `canCreate=false` | `edit` only | same create mismatch as events |
| `speakers` | contribution create + public read/detail | `R + S + C + U + P` | `R + S + C + U + P` | `U` only, Ahli-scoped | `index / create / view / edit`, but `canCreate=false` | `index / view / edit` | same create mismatch as events |
| `references` | public read/detail + suggestion workflow | `R + S + C + U + P` | `R + S + C + U + P` | `U` only, Ahli-scoped | `index / create / edit`, but `canCreate=false` | `index / edit` | public API does not do direct reference CRUD; it uses suggestion flows |
| `venues` | public read/detail only | `R + S + C + U + P` | `R + S + C + U + P` | `-` | `index / create / view / edit`, `canCreate=true` | `-` | panel and API/MCP are aligned here |
| `subdistricts` | public catalog read only | `R + S + C + U + P` | `R + S + C + U + P` | `-` | `index / create / edit`, `canCreate=true` | `-` | panel and API/MCP are aligned here |

### Notes on the writable intersection

- `AdminResourceMutationService` is the source of truth for the six write-enabled admin resources: events, institutions, references, speakers, subdistricts, and venues.
- `MemberResourceMutationService` narrows that admin write contract to the four Ahli resources: events, institutions, references, and speakers.
- Admin HTTP and Admin MCP both support `validate_only` preview on supported create/update writes.
- Member MCP currently does **not** expose a `validate_only` flag on `member-update-record`.
- MCP write tools intentionally reject media uploads, so the logical field contract is shared even when the transport is not.

## API / MCP / panel coverage gaps

These groups are readable through the admin resource registry, but they do **not** have generic admin API/MCP write support today.

| Group | Examples | Admin API / Admin MCP | Admin Panel | Ahli Panel | Notes |
| --- | --- | --- | --- | --- | --- |
| Geography catalogs | `countries`, `states`, `districts` | `R` only | CRUD in panel | `-` | intended as geography reference data; API/MCP keep them read-only |
| Directory / content extras | `tags`, `spaces`, `series`, `inspirations`, `donation-channels` | `R` only | CRUD in panel | `-` | panel-only writes; admin API/MCP can still read where the resource is discoverable |
| Moderation / workflow records | `reports`, `membership-claims`, `contribution-requests` | `R` only | list / view, plus resource-specific UI | `-` | the public/authenticated API owns the actual submission workflow |
| System / authz / analytics | `users`, `slug-redirects`, `ai-model-pricings`, `ai-usage-logs`, `audits`, `tracked-properties`, `signal-goals`, `signal-segments`, `saved-signal-reports`, `signal-alert-rules`, `signal-alert-logs`, `roles`, `permissions` | `R` only | CRUD or read-only mix, depending on the resource | `-` | intentionally not mirrored into admin API/MCP writes |

## Ahli workspace mapping

The member MCP server and Ahli panel are intentionally smaller than the admin surface. They map to the same underlying admin resources, but they only expose the member-scoped subset.

| Ahli resource | Admin equivalent | Ahli panel pages | Member MCP write | Notes |
| --- | --- | --- | --- | --- |
| `App\Filament\Ahli\Resources\Events\EventResource` | `App\Filament\Resources\Events\EventResource` | `index / view / edit` | update only | scoped to member-owned or member-linked events |
| `App\Filament\Ahli\Resources\Institutions\InstitutionResource` | `App\Filament\Resources\Institutions\InstitutionResource` | `edit` | update only | member workspace only, no list page |
| `App\Filament\Ahli\Resources\References\ReferenceResource` | `App\Filament\Resources\References\ReferenceResource` | `index / edit` | update only | reference membership scope only |
| `App\Filament\Ahli\Resources\Speakers\SpeakerResource` | `App\Filament\Resources\Speakers\SpeakerResource` | `index / view / edit` | update only | speaker membership scope only |

## Current asymmetries to keep in mind

- **Admin API / Admin MCP can write more than the admin panel can create** for events, institutions, references, and speakers. The panel has the route, but the resource-level `canCreate()` gate is false.
- **Delete / restore / replicate are panel-led concerns.** The generic admin HTTP and MCP resource layers do not expose delete routes.
- **Member MCP is update-only.** It is deliberately narrower than the admin write contract and currently omits `validate_only`.
- **Public API is workflow-first.** If you add a new entity, first decide whether it belongs in a public workflow contract, the admin resource registry, both, or neither.
- **MCP is not a media transport.** If a write contract involves files, the admin HTTP form may support it, but the MCP path should stay JSON-only unless a dedicated media flow is added later.

## Source of truth files

These are the files that should be revisited when this matrix changes.

- `routes/api.php`
- `routes/ai.php`
- `app/Support/Api/Frontend/FrontendFormContractService.php`
- `app/Support/Api/Admin/AdminResourceRegistry.php`
- `app/Support/Api/Admin/AdminResourceMutationService.php`
- `app/Support/Api/Member/MemberResourceMutationService.php`
- `app/Mcp/Servers/AdminServer.php`
- `app/Mcp/Servers/MemberServer.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/Filament/AhliPanelProvider.php`
- `app/Filament/Resources/*`
- `app/Filament/Ahli/Resources/*`

## Update rule

Whenever a resource is added, removed, or its write policy changes, update this matrix in the same change set.

If you need a quick mental model:

- **Public API** = user-facing workflows.
- **Admin API / Admin MCP** = the same admin resource contract, two transports.
- **Member MCP / Ahli panel** = scoped subset of that admin contract.
- **Filament admin panel** = broadest operational UI, with some policy gates that are intentionally tighter than the API.
