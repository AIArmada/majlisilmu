# MajlisIlmu Admin MCP Agent Guide

Updated: April 25, 2026
Audience: model-facing admin MCP agents and tool clients.

This guide is for the admin MCP surface only. For transport, connector, OAuth, inspector, and other setup details, use `docs/MAJLISILMU_MCP_GUIDE.md`. The member guide is separate and is not exposed through this server.

## What MCP Means in MajlisIlmu

MajlisIlmu exposes the admin MCP server for full admin-surface resource access. Treat the live MCP tool descriptors as the source of truth for what the admin agent can do.

## Verified Documentation Resource

| Resource | URI | Purpose |
|---|---|---|
| `docs-admin-mcp-guide` | `file://docs/MAJLISILMU_MCP_ADMIN_AGENT_GUIDE.md` | Admin-facing guide for auth, transport, discovery primitives, capability matrix, writable resources, and workflow guidance |

## Documentation search and fetch tools

The admin server exposes two read-only documentation tools for model discoverability:

| Tool | Purpose | Notes |
|---|---|---|
| `search` | Search the verified admin MCP guide exposed by this server | Input: one `query` string |
| `fetch` | Fetch the admin guide by id | Input: one `id` string returned by `search` |

These tools search and fetch only the verified admin guide above. They do **not** search admin runtime records.

## Documentation routing prompt

| Prompt | Purpose | Arguments |
|---|---|---|
| `documentation-tool-routing` | Guidance for deciding when to use `search` vs `fetch` for the verified admin docs page | `topic?` |

The prompt tells the model to:

- fetch `docs-admin-mcp-guide` directly when the question is clearly about MajlisIlmu admin MCP behavior
- use `search` when the topic is fuzzy or a discovery step is still helpful
- ensure the verified guide is already in context before the first operational admin MCP tool call
- keep runtime data access on the admin record tools instead of the docs tools
- optionally accept a `topic` hint such as `crud`, `auth`, `media uploads`, `runtime records`, `search`, or `fetch` for more targeted guidance

## Operational preflight rule

Before any MajlisIlmu admin MCP read, search, query, lookup, list, fetch, write, update, create, relation traversal, schema discovery, or workflow action, the client must ensure the verified admin guide is already in context. If it is not, fetch `docs-admin-mcp-guide` first, or use `search` then `fetch` when the topic is still fuzzy.

The admin MCP server enforces a transport-level version of this rule for operational `tools/call` requests: those calls are rejected until `docs-admin-mcp-guide` has been fetched through the MCP `fetch` tool or the guide resource has been read through MCP `resources/read` in the same initialized MCP session.

Apply this before operational tools such as:

- `admin-list-records`
- `admin-get-record`
- `admin-list-related-records`
- `admin-get-resource-meta`
- `admin-get-write-schema`
- `admin-create-record`
- `admin-update-record`
- `admin-get-event-moderation-schema`
- `admin-get-report-triage-schema`
- `admin-get-contribution-request-review-schema`
- `admin-get-membership-claim-review-schema`
- `admin-moderate-event`
- `admin-triage-report`
- `admin-review-contribution-request`
- `admin-review-membership-claim`

The client may skip a fresh docs fetch only when the verified guide is already active in context, or when the user provides the exact `resource_key`, `record_key`, tool, and intended read operation and no interpretation is required.

Even then, re-check live write schemas or explicit workflow schema/tool guidance before create, update, preview, moderation, triage, or review mutations.

Because the server cannot safely infer conversational shortcuts such as “the user already supplied the exact resource and tool with zero interpretation needed”, the runtime MCP guard remains stricter and still requires a same-session guide fetch or read before operational tool execution.

## What this server exposes

Tool-centric clients like ChatGPT and the OpenAI Responses MCP integration import tools from `tools/list`, not raw resources from `resources/list`.

- A client connected to `/mcp/admin` sees only the admin docs tools (`search`, `fetch`) plus `admin-*` tools. It will not see any `member-*` tools.
- The `docs-admin-mcp-guide` resource is the model-readable documentation page for the admin MCP surface, not a replacement for the live tool and resource descriptors.

## MCP capability matrix

Use this section as the quick admin-only capability summary.

| Capability | Admin MCP |
| --- | --- |
| Docs search | `search` |
| Docs fetch | `fetch` |
| Resource discovery | `admin-list-resources` |
| Resource metadata | `admin-get-resource-meta` |
| Record list | `admin-list-records` |
| Record read | `admin-get-record` |
| Record action guidance | `admin-get-record-actions` |
| Explicit workflow schema discovery | `admin-get-event-moderation-schema`, `admin-get-report-triage-schema`, `admin-get-contribution-request-review-schema`, `admin-get-membership-claim-review-schema` |
| Related-record traversal | `admin-list-related-records` |
| Write schema discovery | `admin-get-write-schema` |
| GitHub issue reporting | `admin-create-github-issue` |
| Event moderation | `admin-moderate-event` |
| Report triage | `admin-triage-report` |
| Contribution-request workflows | `admin-review-contribution-request` |
| Membership-claim workflows | `admin-review-membership-claim` |
| Create | `admin-create-record` |
| Update | `admin-update-record` |
| Validate-only preview | Yes, on `admin-create-record` and `admin-update-record` |

## Writable resource matrix

| Resource | Admin MCP | Notes |
| --- | --- | --- |
| `events` | list/get/meta + schema + create + update + preview | Event moderation has a dedicated workflow schema tool |
| `inspirations` | list/get/meta + schema + create + update + preview | Admin-only through the current MCP surface |
| `institutions` | list/get/meta + schema + create + update + preview | Member scope is separate |
| `speakers` | list/get/meta + schema + create + update + preview | Member scope is separate |
| `references` | list/get/meta + schema + create + update + preview | Member scope is separate |
| `reports` | list/get/meta + schema + create + update + preview | Admin-only CRUD plus explicit triage workflow |
| `donation-channels` | list/get/meta + schema + create + update + preview | Admin-only payment channel management |
| `series` | list/get/meta + schema + create + update + preview | Admin-only through the current MCP surface |
| `spaces` | list/get/meta + schema + create + update + preview | Admin-only through the current MCP surface |
| `tags` | list/get/meta + schema + create + update + preview | Admin-only taxonomy management |
| `venues` | list/get/meta + schema + create + update + preview | Admin-only through the current MCP surface |
| `subdistricts` | list/get/meta + schema + create + update + preview | No media upload fields |

## Current structurally write-capable admin resources include:

- `speakers`
- `events`
- `inspirations`
- `institutions`
- `references`
- `reports`
- `donation-channels`
- `series`
- `spaces`
- `tags`
- `venues`
- `subdistricts`

## Relation traversal rules

- Admin only: use `admin-get-resource-meta` to discover whether a relation is exposed, then call `admin-list-related-records` with that relation name.
- Do not assume every admin resource has a relation you can traverse; rely on metadata returned by the live tool.

## Record action guidance

- Use `admin-get-record-actions` when you already have a specific record and want the shortest model-visible list of next MCP calls.
- These read-only tools return focused next-step actions such as refreshing record detail, traversing exposed relations, fetching update schemas, previewing updates, and any explicit workflow tools that are currently valid for that record.
- On the admin surface, workflow-bearing records such as events, reports, contribution requests, and membership claims include both dedicated workflow-schema tool hints and the action-specific defaults, fields, conditional rules, and currently available workflow actions in the same response.
- These tools do not execute mutations; they only point the client at the correct next MCP tool call.

## Explicit workflow schema tools

- Use the dedicated admin workflow-schema tools when you want the canonical read-only workflow contract for one record before calling the matching mutation tool.
- These tools return the same workflow payload shape used by the HTTP admin schema endpoints: defaults, available actions, fields, and conditional rules.
- Current explicit workflow schema tools are `admin-get-event-moderation-schema`, `admin-get-report-triage-schema`, `admin-get-contribution-request-review-schema`, and `admin-get-membership-claim-review-schema`.

## Entity selection heuristics for record search

Use these heuristics before guessing which top-level resource to search:

- Search `institutions` first when the noun matches an institution type from `App\Enums\InstitutionType`:
  - `masjid`
  - `surau`
  - `madrasah`
  - `maahad`
  - `pondok`
  - `sekolah`
  - `kolej`
  - `universiti`
- Search `venues` first when the noun matches a venue type from `App\Enums\VenueType`:
  - `dewan`
  - `auditorium`
  - `stadium`
  - `perpustakaan`
  - `padang`
  - `hotel`
- Treat `spaces` as finer-grained sublocations inside an institution, not as the default first lookup target for named mosques, surau, or other institution identities.
- Example: `Masjid Abidin` should be searched in `institutions` first. If the noun does not match the institution-type terms and sounds like a standalone physical place, look in `venues` next.

## Quick search playbook

When the user asks you to “look for” a named place, start with the most likely top-level resource instead of fanning out across multiple resource types immediately:

- Mosque, surau, school, campus, madrasah, maahad, pondok, or university-style names → search `institutions` first.
- Standalone hall/dewan, auditorium, stadium, library, field, or hotel-style names → search `venues` first.
- Room, wing, floor, block, or hall inside a known institution → resolve the parent `institution` first, then use `spaces` for the internal location when needed.
- Only broaden to a second top-level resource when the first pass returns no good match or the user’s wording is genuinely ambiguous.

## Record and schema reading rules

- Use `record_key` values from prior MCP results; prefer returned route-key-style identifiers when the record payload exposes them.
- Treat live write schemas as the field-level source of truth for payload structure, required fields, and media support.
- Institution write schemas expose `nickname`, `address`, `contacts`, and `social_media` semantics. `address` updates deep-merge omitted nested keys, and `address: {}` is effectively a no-op when the record already has an address with a stored country.
- Speaker write schemas expose `address`, `honorific`, `pre_nominal`, `post_nominal`, `qualifications`, `language_ids`, `contacts`, and `social_media` semantics. If you send `address`, include `address.country_id`; `address: {}` is invalid on the write path, while array-style fields replace when present.
- Venue write schemas expose `address`, `facilities`, `contacts`, and `social_media` semantics. Omitted address keys preserve the existing nested values, but `address: {}` deletes the stored venue address on the shared save path.
- Reference write schemas expose `author`, `publication_year`, `publisher`, and `social_media` semantics. Omitted optional scalars preserve the existing value, while `null` or trimmed empty input clears `author`, `publication_year`, and `publisher` to `null`.
- Event write schemas expose `event_url`, `live_url`, `recording_url`, `languages`, `references`, `series`, `domain_tags`, `discipline_tags`, `source_tags`, `issue_tags`, `speakers`, `other_key_people`, `organizer_type`, and `registration_mode`. Omitted scalar and relation fields preserve the current value via server-side form-state merge, `null` or `[]` clear supported relation collections, and submitted `speakers` / `other_key_people` arrays rebuild the underlying `key_people` rows with new order values.
- Event record detail payloads also expose the public change-surface projection fields `active_change_notice`, `change_announcements`, and `replacement_event` so MCP clients can reason about the same published replacement-chain behavior as the public/mobile event detail contract without following stale links.
- Series write schemas expose `description`, `languages`, and `slug` semantics. `title`, `slug`, and `visibility` remain required on update; `description` clears on `null` / trimmed empty input and `languages` follows omit-preserve / null-clear / array-replace semantics.
- Donation channel write schemas expose `donatable_type`, `method`, `label`, `reference_note`, and the method-specific bank / DuitNow / ewallet fields. Owner-type aliases normalize to canonical stored morph values, method switches clear unrelated field groups, and destructive QR clear flags remain unsupported through MCP.
- Inspiration write schemas expose `content`, `source`, and `main`. `category`, `locale`, `title`, and `content` remain required on update; `source` clears on `null` / trimmed empty input, and the single `main` media field can only be replaced through MCP, not directly cleared through a destructive flag.
- Space write schemas expose `slug`, `capacity`, and `institutions`. `name` and `slug` remain required on update, `capacity` clears to `null`, and `institutions` follows omit-preserve / null-clear / array-replace relation sync semantics.
- Report write schemas expose `entity_type`, `entity_id`, `category`, `description`, `reporter_id`, `handled_by`, `resolution_note`, and `evidence`. `entity_type`, `entity_id`, `category`, and `status` remain required on update, `category` depends on `entity_type`, the optional text / user-reference fields clear on `null`, and `evidence` preserves on omission or `null` but clears on `[]`.
- Tag write schemas expose `name`, `name.ms`, `name.en`, and `order_column`. `name.en` falls back to `name.ms` when it is omitted, `null`, or trimmed empty input, and blank / null `order_column` values trigger sortable recomputation instead of storing `null`.
- Subdistrict write schemas expose `country_id`, `state_id`, `district_id`, and `name`. `country_id`, `state_id`, and `name` remain required on update, `name` is trimmed, `state_id` must match `country_id`, and `district_id=null` is valid only for federal-territory states.
- Handle-style social platforms (`facebook`, `twitter`, `instagram`, `youtube`, `tiktok`, `telegram`, `whatsapp`, `linkedin`, `threads`) may canonicalize a submitted URL into stored `username`, so persisted `url` can come back as `null` after normalization.
- Even though the schema advertises model-layer normalization notes for Twitter / X, validated MCP payloads should still use the canonical platform value `twitter`, not `x`.
- Enum fields and filters use enum backing values, not display labels. For events, use values like `kuliah_ceramah`, `all_ages`, `prayer_relative`, `maghrib`, and `immediately` instead of labels like `Kuliah / Ceramah` or localized prayer text.
- This guide summarizes server-level capability only; actor-specific authorization still applies at runtime.

## Validate-only preview behavior

- `validate_only=true` is supported on `admin-create-record` and `admin-update-record`.
- Previews normalize descriptors into file summaries without persisting media.
- `apply_defaults=true` enables server-side default application during preview flows where the schema advertises it.
- schema-driven `feedback` issues may include suggested values, defaults, and conditional `required_because` context.
- Validation failures in validate-only mode include `fix_plan`, `remaining_blockers`, `normalized_payload_preview`, and `can_retry` so tool clients can recover in one retry loop.
- `clear_*` media flags are intentionally rejected in MCP even when the raw HTTP admin schema may mention destructive media handling.

## MCP media/file upload contract

MCP write tools are JSON tools, not multipart HTTP endpoints. When a schema advertises a media/file field, send a descriptor object instead of a browser file upload:

```json
{
  "filename": "poster.png",
  "mime_type": "image/png",
  "content_base64": "iVBORw0KGgo..."
}
```

Accepted aliases are `file_name` or `name` for `filename`, `mime` for `mime_type`, and `base64` or `data` for `content_base64`. Data URLs are accepted for `content_base64`. Filename extensions are recommended; when they are omitted, the server derives the staged extension from `mime_type`.

Schema fields describe the exact upload rules:

- `mcp_upload.shape` is `file_descriptor` for a single file and `array<file_descriptor>` for multiple files.
- `accepted_mime_types`, `max_file_size_kb`, and `max_files` are authoritative for that field.
- `mcp_upload.replacement_semantics` describes whether the submitted descriptor or descriptor array replaces the target media collection.
- When a write tool supports `validate_only=true`, previews normalize descriptors into file summaries without persisting media.
- `current_media` stays metadata-only and does not expose signed or temporary URLs.
- `clear_*` media flags remain unsupported through MCP and are rejected even when clients submit them manually.

## Admin MCP tool catalog

The admin server is the model-visible API-like surface for admin workflows. The tools below are the current contract that ChatGPT can call:

| Tool | Purpose | Raw HTTP admin equivalent |
|---|---|---|
| `search` | Search the verified admin MCP documentation pages exposed by this server | MCP-only documentation discovery tool |
| `fetch` | Fetch one verified admin documentation page by id | MCP-only documentation fetch tool |
| `admin-list-resources` | List accessible admin resources and their capability summary | `GET /api/v1/admin/manifest` |
| `admin-get-resource-meta` | Read one admin resource's metadata, pages, relations, abilities, and write-support flags | `GET /api/v1/admin/{resourceKey}/meta` |
| `admin-list-records` | List records for one admin resource with optional search, structured filters, date filters, and pagination | `GET /api/v1/admin/{resourceKey}` |
| `admin-list-related-records` | Traverse a named relation on one admin record | `GET /api/v1/admin/{resourceKey}/{recordKey}/relations/{relation}` |
| `admin-get-record` | Read one admin record and its permissions | `GET /api/v1/admin/{resourceKey}/{recordKey}` |
| `admin-get-record-actions` | Get focused next-step MCP actions for one admin record | MCP-only next-step action guidance tool |
| `admin-get-write-schema` | Discover the create/update contract for a writable admin record | `GET /api/v1/admin/{resourceKey}/schema` |
| `admin-get-event-moderation-schema` | Read the explicit moderation schema for one event | `GET /api/v1/admin/events/{recordKey}/moderation-schema` |
| `admin-get-report-triage-schema` | Read the explicit triage schema for one report | `GET /api/v1/admin/reports/{recordKey}/triage-schema` |
| `admin-get-contribution-request-review-schema` | Read the explicit review schema for one contribution request | `GET /api/v1/admin/contribution-requests/{recordKey}/review-schema` |
| `admin-get-membership-claim-review-schema` | Read the explicit review schema for one membership claim | `GET /api/v1/admin/membership-claims/{recordKey}/review-schema` |
| `admin-create-github-issue` | Create a GitHub issue in the configured repository and auto-assign Copilot | `POST /api/v1/github-issues` (admin caller path) |
| `admin-moderate-event` | Run one explicit moderation action on an event | `POST /api/v1/admin/events/{recordKey}/moderate` |
| `admin-triage-report` | Run one explicit triage action on a report | `POST /api/v1/admin/reports/{recordKey}/triage` |
| `admin-review-contribution-request` | Approve or reject one pending contribution request | `POST /api/v1/admin/contribution-requests/{recordKey}/review` |
| `admin-review-membership-claim` | Approve or reject a pending membership claim | `POST /api/v1/admin/membership-claims/{recordKey}/review` |
| `admin-create-record` | Create or preview a writable admin record | `POST /api/v1/admin/{resourceKey}` |
| `admin-update-record` | Update or preview a writable admin record | `PUT /api/v1/admin/{resourceKey}/{recordKey}` |

Admin tool behavior notes:

- `validate_only=true` is supported for create/update preview flows.
- `admin-list-resources` is a discovery manifest, not merely a small name list. Keep `verbose=false` for compact exploration and use `verbose=true` only when you need full metadata.
- `current_media` is metadata only; it is useful for form prefill but does not expose signed URLs.
- `admin-list-records` accepts a `filters` object keyed by the resource metadata filter keys, for example `{ "status": "approved", "is_active": true }` for `events`.
- For `speakers`, `institutions`, and `references`, `admin-list-records` search reuses the same specialized search services as the public directory endpoints; the main difference is record scope, not text-matching behavior.
- For date-aware resources, `starts_after`, `starts_before`, and `starts_on_local_date` are date-only `YYYY-MM-DD` strings interpreted in the resolved request timezone. Do not send ISO 8601 timestamps to those MCP arguments.
- Event enum filters and payload values must be backing values, for example `filter[event_type]=kuliah_ceramah` and `filter[timing_mode]=prayer_relative`.
- `admin-get-record-actions` is read-only and returns record-specific next-step MCP tools, including explicit workflow-schema tool hints when a moderation, triage, or review flow is currently available on that record.
- The dedicated admin workflow-schema tools are read-only and expose defaults, available actions, fields, and conditional rules for their matching moderation/review workflow.
- Media/file upload fields accept JSON base64 descriptors only when the matching write schema advertises them.
- `clear_*` media flags are intentionally rejected in MCP even when the raw HTTP admin schema may mention destructive media handling.
- `admin-create-github-issue` creates a GitHub issue and, for admin actors, automatically assigns Copilot using the server-side configuration and model fallback chain.
- Read-only tools should be annotated as such so ChatGPT can safely choose them.
- Write tools should be described as schema-guided and idempotent where the server logic supports that behavior.

## Summary for agents

1. Fetch `docs-admin-mcp-guide` before the first operational tool call.
2. Use `search` when the topic is fuzzy, `fetch` when the guide is obvious.
3. Choose the right top-level resource first.
4. Trust live write schemas and workflow schema tools before mutating anything.
5. Keep setup, connector, and raw HTTP API concerns out of MCP-only reasoning.

## Explicit CRUD boundary

- Admin MCP currently supports create and update for writable resources through schema-guided tools.
- Admin MCP does **not** expose generic delete tools (for example `admin-delete-record`).
- Treat delete, restore, reorder, and replicate as panel-led operations unless a future MCP tool is explicitly added.
