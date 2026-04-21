# MajlisIlmu MCP Guide

Updated: April 21, 2026
Audience: developers and AI-client integrators.

## What MCP Means in MajlisIlmu

MajlisIlmu exposes two web MCP servers plus two local testing handles that AI clients can connect to:

- `Admin MCP` for full admin-surface resource access.
- `Member MCP` for Ahli-scoped access to the member surface.

Both web servers are registered in `routes/ai.php` and protected by bearer-token or OAuth-based authentication. Local testing handles are also registered there so you can exercise the full MCP stack through the app container and its configured database.

## How To Read Connector Reports

When you are comparing a report, a Filament page, an HTTP admin endpoint, and an MCP response, treat them as different surfaces with different contracts:

| Surface | Source of truth | Use it for | Do not assume |
|---|---|---|---|
| Filament admin panel | `app/Filament/Resources/*` and related pages | Human admin UX and resource configuration | Exact API payload shape |
| Raw HTTP admin API | `docs/MAJLISILMU_MOBILE_API_REFERENCE.md` and `/api/v1/admin` | HTTP admin contracts, schemas, and record payloads | MCP write-schema parity |
| MCP admin server | `/mcp/admin` | AI-client tool calls and sanitized write schemas | Raw HTTP schema parity |

Key rules:

- `GET /api/v1/admin/{resourceKey}/schema` documents the raw HTTP admin contract.
- `GET /mcp/admin` uses a separate MCP write-schema formatter; destructive `clear_*` media flags are removed there, while supported media/file fields are advertised with JSON base64 descriptor metadata.
- `current_media` contains metadata only; it does not expose signed or temporary URLs.
- Generic user record payloads intentionally redact `email`, `email_verified_at`, `phone`, `phone_verified_at`, `daily_prayer_institution_id`, and `friday_prayer_institution_id`.
- Record lookups use `route_key` for record-specific paths, and missing records return 404 rather than a generic server error.
- Supported admin update flows are schema-guided `PUT` requests: required fields must be sent, while optional omitted fields are merged by the underlying save actions when that resource supports merge-based updates.

If you are writing or reviewing an AI-generated report, read the raw HTTP admin reference for `/api/v1/admin` behavior and this guide for MCP-only behavior. Do not infer one surface from the other.

## Available Servers

### Admin MCP

- Route: `/mcp/admin`
- Server class: `App\Mcp\Servers\AdminServer`
- Intended for admin workflows such as resource discovery, record browsing, record detail, named relation traversal, schema discovery, and supported create/update operations.

### Member MCP

- Route: `/mcp/member`
- Server class: `App\Mcp\Servers\MemberServer`
- Intended for Ahli/member workflows such as resource discovery, record browsing, record detail, schema discovery, supported updates on writable member-visible resources, contribution-request queues, and membership claims.

## How To Connect

### 1. Issue a bearer token

The quickest way to use MCP with this app is to issue a scoped Sanctum token for the server you want to call.

```shell
php artisan mcp:token someone@example.com "VS Code Admin MCP" --server=admin
php artisan mcp:token someone@example.com "VS Code Member MCP" --server=member
```

Use the returned token as an `Authorization: Bearer <token>` header in your MCP client.

The token is server-scoped:

- `admin` tokens carry the `mcp:admin` ability.
- `member` tokens carry the `mcp:member` ability.

### 2. Or use OAuth-capable clients

The app also registers MCP OAuth routes under `oauth/mcp` in `routes/ai.php`.

Use this path when your client supports OAuth instead of manual bearer tokens. The OAuth setup is backed by Passport and the published MCP authorization view in `resources/views/mcp/authorize.blade.php`.

### ChatGPT Connector Settings

If ChatGPT shows the optional OAuth advanced settings, use these values for this server:

- Registration method: `Dynamic Client Registration (DCR)`
- Default scopes: `mcp:use`
- Base scopes: leave blank unless the server later advertises additional scopes
- OpenID support: leave disabled, because this server does not advertise an OIDC configuration URL
- Auth URL / Token URL / Registration URL / Authorization server base / Resource: keep the discovered endpoints that ChatGPT already populated

Why this is the right choice:

- The MCP server advertises a Registration URL, so DCR is available.
- CIMD is unavailable because the server does not advertise CIMD support.
- The server’s MCP OAuth flow is built around the `mcp:use` scope.
- The server does not advertise OIDC discovery, so OpenID Connect is not needed for this connector.

If ChatGPT asks which resource to use, point it at the same MCP resource route you are connecting to, such as `/mcp/admin` for the admin server or `/mcp/member` for the member server.

### 3. Or manage tokens inside the app

Authenticated users can manage their MCP tokens from the account-settings API:

- `GET /api/v1/account-settings/mcp-tokens`
- `POST /api/v1/account-settings/mcp-tokens`
- `DELETE /api/v1/account-settings/mcp-tokens/{tokenId}`

### 4. Test the local MCP stack

If you want to verify the MCP servers against your local database without going through remote MCP transport, use the local handles:

```shell
php artisan mcp:inspector majlisilmu-admin-local
php artisan mcp:inspector majlisilmu-member-local
```

These local handles run inside the app process, so they use the database configured for the current environment:

- In local development, they point at your local database connection.
- In automated tests, they use the test database created for the test run.

Use these handles when you want to confirm the MCP tools, resource discovery, and auth behavior locally before wiring the same server class into a remote client.

## Inspect And Debug

Use the MCP Inspector when you want to test the server before wiring it into a client:

```shell
php artisan mcp:inspector /mcp/admin
php artisan mcp:inspector /mcp/member
php artisan mcp:inspector majlisilmu-admin-local
php artisan mcp:inspector majlisilmu-member-local
```

The inspector is useful for:

- Verifying authentication.
- Checking which tools are exposed.
- Trying tool calls interactively.
- Debugging client configuration issues.

## What Each Server Exposes

### How ChatGPT Reads These Tools

ChatGPT does not infer server capabilities from the Laravel controllers or database schema. It reasons from the MCP tool descriptors you expose.

For each tool, ChatGPT sees:

- the tool `name`
- the human-readable `title` / `description`
- the input schema for arguments
- impact annotations such as read-only, destructive, idempotent, and open-world hints
- any UI linkage metadata like `_meta.ui.resourceUri`
- the tool response `structuredContent` and `content`

ChatGPT does **not** read `_meta` as model context unless the tool response explicitly includes it in model-visible content. Treat `_meta` as host/UI-only data.

If you want ChatGPT to understand a capability, expose it as a dedicated tool with a clear schema and description. One tool should map to one user intent, the same way one API operation maps to one HTTP action.

### Verified documentation resources

Both admin and member servers expose this read-only markdown resource through MCP `resources/list` and `resources/read`:

| Resource | URI | Purpose |
|---|---|---|
| `docs-mcp-guide` | `file://docs/MAJLISILMU_MCP_GUIDE.md` | Verified guide for MCP auth, transport, discovery primitives, capability matrix, media rules, and current admin/member write behavior |

Treat this resource as the model-readable documentation page for MajlisIlmu MCP, not as a replacement for the live tool/resource descriptors.

The broader internal cross-surface parity docs (`MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.*`) are intentionally not exposed through MCP.

Tool-centric clients like ChatGPT and the OpenAI Responses MCP integration import tools from `tools/list`, not raw resources from `resources/list`. To make these docs reliably discoverable in those clients, both servers also expose read-only `search` and `fetch` documentation tools.

### Documentation search and fetch tools

Both servers expose two MCP-standard read-only documentation tools for model discoverability:

| Tool | Purpose | Notes |
|---|---|---|
| `search` | Search the verified documentation page exposed by this server | Input: one `query` string. Returns JSON text with `{results:[{id,title,url}]}`. |
| `fetch` | Fetch the verified documentation page by id | Input: one `id` string returned by `search`. Returns JSON text with `{id,title,text,url,metadata}`. |

These tools search and fetch only the verified MCP guide exposed above. They do **not** search admin/member resource records.

### Documentation routing prompt

Both servers also expose one small MCP prompt for clients that support prompt discovery:

| Prompt | Purpose | Arguments |
|---|---|---|
| `documentation-tool-routing` | Short guidance for deciding when to use `search` vs `fetch` for the verified docs pages | `topic?` |

The prompt tells the model to:

- fetch `docs-mcp-guide` directly when the question is clearly about MajlisIlmu MCP docs
- use `search` when the topic is fuzzy or a discovery step is still helpful
- keep runtime data access on the admin/member record tools instead of the docs tools
- optionally accept a `topic` hint such as `crud`, `auth`, `media uploads`, `runtime records`, `search`, or `fetch` for more targeted guidance

### MCP capability matrix

Use this section as the quick MCP-only capability summary.

| Capability | Admin MCP | Member MCP |
| --- | --- | --- |
| Docs search | `search` | `search` |
| Docs fetch | `fetch` | `fetch` |
| Resource discovery | `admin-list-resources` | `member-list-resources` |
| Resource metadata | `admin-get-resource-meta` | `member-get-resource-meta` |
| Record list | `admin-list-records` | `member-list-records` |
| Record read | `admin-get-record` | `member-get-record` |
| Related-record traversal | `admin-list-related-records` | `member-list-related-records` |
| Write schema discovery | `admin-get-write-schema` | `member-get-write-schema` |
| GitHub issue reporting | `admin-create-github-issue` | `member-create-github-issue` |
| Event moderation | `admin-moderate-event` | Not exposed |
| Report triage | `admin-triage-report` | Not exposed |
| Contribution-request workflows | `admin-review-contribution-request` | `member-list-contribution-requests`, `member-approve-contribution-request`, `member-reject-contribution-request`, `member-cancel-contribution-request` |
| Membership-claim workflows | `admin-review-membership-claim` | `member-list-membership-claims`, `member-submit-membership-claim`, `member-cancel-membership-claim` |
| Create | `admin-create-record` | Not exposed |
| Update | `admin-update-record` | `member-update-record` |
| Validate-only preview | Yes, on `admin-create-record` and `admin-update-record` | Yes, on `member-update-record` |

Admin GitHub issue reports can skip Copilot assignment entirely by setting `GITHUB_ISSUE_REPORTING_ADMIN_COPILOT_ASSIGNMENT_ENABLED=false` on the server.

### Writable resource matrix

| Resource | Admin MCP | Member MCP | Notes |
| --- | --- | --- | --- |
| `events` | list/get/meta + schema + create + update + preview | list/get/meta + schema + update + preview | Member scope only; no member create |
| `inspirations` | list/get/meta + schema + create + update + preview | Not exposed | Admin-only through the current MCP surface |
| `institutions` | list/get/meta + schema + create + update + preview | list/get/meta + schema + update + preview | Member scope limited to linked institutions |
| `speakers` | list/get/meta + schema + create + update + preview | list/get/meta + schema + update + preview | Member scope limited to linked speakers |
| `references` | list/get/meta + schema + create + update + preview | list/get/meta + schema + update + preview | Member scope limited to linked references |
| `reports` | list/get/meta + schema + create + update + preview | Not exposed | Admin CRUD plus explicit triage workflow |
| `donation-channels` | list/get/meta + schema + create + update + preview | Not exposed | Admin-only payment channel management |
| `series` | list/get/meta + schema + create + update + preview | Not exposed | Admin-only through the current MCP surface |
| `spaces` | list/get/meta + schema + create + update + preview | Not exposed | Admin-only through the current MCP surface |
| `tags` | list/get/meta + schema + create + update + preview | Not exposed | Admin-only taxonomy management |
| `venues` | list/get/meta + schema + create + update + preview | Not exposed | Admin-only through the current MCP surface |
| `subdistricts` | list/get/meta + schema + create + update + preview | Not exposed | No media upload fields |

### Relation traversal rules

- Admin only: use `admin-get-resource-meta` to discover whether a relation is exposed, then call `admin-list-related-records` with that relation name.
- Member MCP: use `member-get-resource-meta` to discover whether a relation is exposed, then call `member-list-related-records` with that relation name.
- Do not assume every admin resource has a relation you can traverse; rely on metadata returned by the live tool.

### Entity selection heuristics for record search

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

### Quick search playbook

When the user asks you to “look for” a named place, start with the most likely top-level resource instead of fanning out across multiple resource types immediately:

- Mosque, surau, school, campus, madrasah, maahad, pondok, or university-style names → search `institutions` first.
- Standalone hall/dewan, auditorium, stadium, library, field, or hotel-style names → search `venues` first.
- Room, wing, floor, block, or hall inside a known institution → resolve the parent `institution` first, then use `spaces` for the internal location when needed.
- Only broaden to a second top-level resource when the first pass returns no good match or the user’s wording is genuinely ambiguous.

### Record and schema reading rules

- Use `record_key` values from prior MCP results; prefer returned route-key-style identifiers when the record payload exposes them.
- Treat live write schemas as the field-level source of truth for payload structure, required fields, and media support.
- This guide summarizes server-level capability only; actor-specific authorization still applies at runtime.

### Non-goals

- This guide is not the raw HTTP admin API contract.
- This guide is not a Filament panel parity matrix.
- This guide does not promise delete, restore, reorder, or replicate support through MCP.

### Admin MCP tool catalog

The admin server is the model-visible API-like surface for admin workflows. The tools below are the current contract that ChatGPT can call:

| Tool | Purpose | Raw HTTP admin equivalent |
|---|---|---|
| `search` | Search the verified MajlisIlmu MCP documentation pages exposed by this server | MCP-only documentation discovery tool |
| `fetch` | Fetch one verified MajlisIlmu documentation page by id | MCP-only documentation fetch tool |
| `admin-list-resources` | List accessible admin resources and their capability summary | `GET /api/v1/admin/manifest` |
| `admin-get-resource-meta` | Read one admin resource's metadata, pages, relations, abilities, and write-support flags | `GET /api/v1/admin/{resourceKey}/meta` |
| `admin-list-records` | List records for one admin resource with optional search and pagination | `GET /api/v1/admin/{resourceKey}` |
| `admin-list-related-records` | Traverse a named relation on one admin record | `GET /api/v1/admin/{resourceKey}/{recordKey}/relations/{relation}` |
| `admin-get-record` | Read one admin record and its permissions | `GET /api/v1/admin/{resourceKey}/{recordKey}` |
| `admin-get-write-schema` | Discover the create/update contract for a writable admin record | `GET /api/v1/admin/{resourceKey}/schema` |
| `admin-create-github-issue` | Create a GitHub issue in the configured repository and auto-assign Copilot | `POST /api/v1/github-issues` (admin caller path) |
| `admin-moderate-event` | Run one explicit moderation action on an event | `POST /api/v1/admin/events/{recordKey}/moderate` |
| `admin-triage-report` | Run one explicit triage action on a report | `POST /api/v1/admin/reports/{recordKey}/triage` |
| `admin-review-contribution-request` | Approve or reject one pending contribution request | `POST /api/v1/admin/contribution-requests/{recordKey}/review` |
| `admin-review-membership-claim` | Approve or reject a pending membership claim | `POST /api/v1/admin/membership-claims/{recordKey}/review` |
| `admin-create-record` | Create or preview a writable admin record | `POST /api/v1/admin/{resourceKey}` |
| `admin-update-record` | Update or preview a writable admin record | `PUT /api/v1/admin/{resourceKey}/{recordKey}` |

Admin tool behavior notes:

- `validate_only=true` is supported for create/update preview flows.
- `current_media` is metadata only; it is useful for form prefill but does not expose signed URLs.
- Media/file upload fields accept JSON base64 descriptors only when the matching write schema advertises them.
- `clear_*` media flags are intentionally rejected in MCP even when the raw HTTP admin schema may mention destructive media handling.
- `admin-create-github-issue` creates a GitHub issue and, for admin actors, automatically assigns Copilot using the server-side configuration and model fallback chain.
- Read-only tools should be annotated as such so ChatGPT can safely choose them.
- Write tools should be described as schema-guided and idempotent where the server logic supports that behavior.

### Member MCP tool catalog

The member server is the model-visible API-like surface for Ahli-scoped workflows. These tools expose the member boundary as ChatGPT-readable operations:

| Tool | Purpose |
|---|---|
| `search` | Search the verified MajlisIlmu MCP documentation pages exposed by this server |
| `fetch` | Fetch one verified MajlisIlmu documentation page by id |
| `member-list-resources` | List accessible Ahli-scoped member resources |
| `member-get-resource-meta` | Read one member resource's metadata, permissions, and available write support |
| `member-list-records` | List records for one member resource with optional search and pagination |
| `member-list-related-records` | List related records for one member record |
| `member-get-record` | Read one member record by resource key and record key |
| `member-get-write-schema` | Discover the writable update schema for one member record |
| `member-list-contribution-requests` | List the authenticated member's own contribution requests plus any pending approvals |
| `member-approve-contribution-request` | Approve one reviewable contribution request |
| `member-reject-contribution-request` | Reject one reviewable contribution request |
| `member-cancel-contribution-request` | Cancel one pending contribution request owned by the authenticated member |
| `member-list-membership-claims` | List the authenticated member's membership claims |
| `member-submit-membership-claim` | Submit a membership claim with justification and evidence uploads |
| `member-cancel-membership-claim` | Cancel one pending membership claim owned by the authenticated member |
| `member-create-github-issue` | Create a GitHub issue in the configured repository |
| `member-update-record` | Update a writable member record using the schema-guided payload contract |

Member tool behavior notes:

- Member tools are constrained to the Ahli workspace boundary and live membership relationships.
- Update tools are schema-guided and should be treated as the member-side API equivalent of the relevant HTTP workflow.
- Member update tools support `validate_only=true` for preview-only member writes.
- Member related-record traversal is limited to one level and only for relations exposed by member resource metadata.
- Contribution-request workflow tools cover listing, approving, rejecting, and cancelling queue items that the authenticated member can legitimately act on through the Ahli surface.
- Membership-claim workflow tools cover listing, submitting with evidence uploads, and cancelling the member's own pending claims.
- `member-create-github-issue` creates a plain GitHub issue only; it does not assign Copilot.
- Media/file upload fields accept JSON base64 descriptors only when the matching member write schema advertises them.
- As with admin tools, ChatGPT only understands what the tool descriptor exposes; if a capability is not registered as a tool, the model will not assume it exists.

### MCP media/file upload contract

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
- When a write tool supports `validate_only=true` (currently admin create/update and member update), previews normalize descriptors into file summaries without persisting media.
- Admin preview validation failures now include schema-driven `feedback` hints (`allowed_values`, `suggested`, `closest_valid_value`, `default`, `required_because`) and can return a candidate `normalized_payload` when `apply_defaults=true`.
- `current_media` stays metadata-only and does not expose signed or temporary URLs.
- `clear_*` media flags remain unsupported through MCP and are rejected even when clients submit them manually.

### Admin MCP resource scope

The admin tool catalog above is the canonical list of model-visible operations. The admin server exposes those tools for resource discovery, record browsing, relation traversal, schema discovery, and supported create/update workflows.

Current structurally write-capable admin resources include:

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

Read-only admin resources are still discoverable through the resource list and metadata tools; use the capability matrix for the full runtime inventory.

Write-tool preview tip:

- `admin-create-record` and `admin-update-record` accept `validate_only=true` to validate, normalize, and preview a write without persisting it.
- Add `apply_defaults=true` on preview calls when you want the server to apply schema defaults before validation and return a candidate autofilled payload in validation feedback.
- Preview responses return the normalized payload plus warning metadata for supported write-side checks.
- Validation failures return schema-driven `feedback` issues with suggested values, defaults, and conditional `required_because` context.
- Validation failures in validate-only mode now include `fix_plan`, `remaining_blockers`, `normalized_payload_preview`, and `can_retry` so tool clients can recover in one retry loop.
- MCP write schemas advertise supported media/file fields with JSON descriptor metadata, do not advertise destructive media clear-flags, and reject clear-flags if a client submits them anyway.
- Update previews also include the current record snapshot so you can compare what would change before retrying without `validate_only`.

### Member MCP resource scope

The member tool catalog above is the canonical list of model-visible operations. The member server exposes those tools for resource discovery, record browsing, schema discovery, and supported member-side update workflows.

Current member-write-capable resources include:

- `institutions`
- `speakers`
- `references`
- `events`

## Configuration Notes

The MCP configuration lives in `config/mcp.php`.

Useful environment variables:

- `MCP_REDIRECT_DOMAINS` — add hosted OAuth redirect domains here.
- `MCP_CUSTOM_SCHEMES` — allow private-use callback schemes for desktop clients such as `vscode` or `claude`.
- `MCP_AUTHORIZATION_SERVER` — override the OAuth issuer identifier if needed.
- `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` — optional raw PEM secrets for Passport; leave them blank to use the generated `storage/oauth-*.key` files. Do not set file paths here.

## Common Troubleshooting Checks

- `401 Unauthorized` usually means the client is missing a bearer token or the token does not match the target server.
- `403 Forbidden` usually means the user does not have the required admin or member access.
- OAuth redirect failures usually mean the redirect domain or custom scheme has not been allowlisted in `config/mcp.php`.

## Relevant Files

- `routes/ai.php` — MCP server registration and OAuth routes.
- `app/Mcp/Servers/AdminServer.php` — admin MCP server definition.
- `app/Mcp/Servers/MemberServer.php` — member MCP server definition.
- `app/Mcp/Resources/Docs/*` — verified markdown MCP resources exposed by both servers.
- `app/Support/Mcp/McpWriteSchemaFormatter.php` — sanitized MCP write-schema formatter.
- `app/Support/Mcp/McpFilePayloadNormalizer.php` — JSON file descriptor staging and validation for MCP writes.
- `tests/Unit/McpGuideDocsTest.php` — MCP guide regression coverage for verified resource and CRUD claims.
- `app/Console/Commands/IssueMcpToken.php` — command for issuing scoped tokens.
- `app/Support/Mcp/McpTokenManager.php` — token issuance, listing, and revocation logic.
- `tests/Feature/Mcp/AdminServerTest.php` — admin MCP regression coverage.
- `tests/Feature/Mcp/MemberServerTest.php` — member MCP regression coverage.
- `tests/Feature/Mcp/LocalServerRegistrationTest.php` — local MCP registration coverage.

## Quick Start

1. Decide whether you need the admin or member server.
2. Issue a matching bearer token, or start an OAuth flow if your client supports it.
3. Point your MCP client at `/mcp/admin` or `/mcp/member`.
4. Use `php artisan mcp:inspector` if you need to verify the connection or inspect available tools; prefer the local handles when you want to exercise the app’s local database and full MCP transport end to end.

## Appendix: Compact Tool Reference

Use this as the quick scan list when you want ChatGPT to reason about the connector as an API surface.

### Admin MCP tools

| Tool | When to use it | Core arguments |
|---|---|---|
| `search` | Search the verified MCP/docs pages exposed by this server | `query` |
| `fetch` | Fetch the full text of one verified docs page | `id` |
| `admin-list-resources` | Discover accessible admin resources | `verbose?`, `writable_only?` |
| `admin-get-resource-meta` | Inspect one resource’s metadata, routes, relations, and abilities | `resource_key` |
| `admin-list-records` | Search and paginate records for one admin resource | `resource_key`, `search?`, `page?`, `per_page?` |
| `admin-list-related-records` | Traverse a named relation on a record | `resource_key`, `record_key`, `relation`, `page?`, `per_page?` |
| `admin-get-record` | Read one admin record and its permissions | `resource_key`, `record_key` |
| `admin-get-write-schema` | Fetch the create/update contract for a writable admin record | `resource_key`, `operation`, `record_key?` |
| `admin-moderate-event` | Run one explicit moderation action on an event | `record_key`, `action`, `reason_code?`, `note?` |
| `admin-triage-report` | Run one explicit triage action on a report | `record_key`, `action`, `resolution_note?` |
| `admin-review-contribution-request` | Approve or reject one pending contribution request | `record_key`, `action`, `reason_code?`, `reviewer_note?` |
| `admin-review-membership-claim` | Approve or reject one pending membership claim | `record_key`, `action`, `granted_role_slug?`, `reviewer_note?` |
| `admin-create-record` | Create or preview a writable admin record | `resource_key`, `payload`, `validate_only?`, `apply_defaults?` |
| `admin-update-record` | Update or preview a writable admin record | `resource_key`, `record_key`, `payload`, `validate_only?`, `apply_defaults?` |

### Member MCP tools

| Tool | When to use it | Core arguments |
|---|---|---|
| `search` | Search the verified MCP/docs pages exposed by this server | `query` |
| `fetch` | Fetch the full text of one verified docs page | `id` |
| `member-list-resources` | Discover accessible Ahli-scoped resources | `verbose?` |
| `member-get-resource-meta` | Inspect one member resource’s metadata and write support | `resource_key` |
| `member-list-records` | Search and paginate records for one member resource | `resource_key`, `search?`, `page?`, `per_page?` |
| `member-list-related-records` | Traverse a named relation on a member record | `resource_key`, `record_key`, `relation`, `page?`, `per_page?` |
| `member-get-record` | Read one member record | `resource_key`, `record_key` |
| `member-get-write-schema` | Fetch the writable update contract for one member record | `resource_key`, `record_key` |
| `member-list-contribution-requests` | List the authenticated member's contribution queue and pending approvals | none |
| `member-approve-contribution-request` | Approve one reviewable contribution request | `request_id`, `reviewer_note?` |
| `member-reject-contribution-request` | Reject one reviewable contribution request | `request_id`, `reason_code`, `reviewer_note?` |
| `member-cancel-contribution-request` | Cancel one pending contribution request owned by the member | `request_id` |
| `member-list-membership-claims` | List the authenticated member's membership claims | none |
| `member-submit-membership-claim` | Submit a membership claim with evidence uploads | `subject_type`, `subject`, `justification`, `evidence` |
| `member-cancel-membership-claim` | Cancel one pending membership claim owned by the member | `claim_id` |
| `member-update-record` | Update or preview a writable member record | `resource_key`, `record_key`, `payload`, `validate_only?` |

### Reading rules for the appendix

- Read-only tools should be treated as discovery and preview operations.
- Write tools are schema-guided and should always be preceded by the matching write-schema call.
- The model should treat these tools as the full ChatGPT-visible connector API. If a capability is not listed here, it is not part of the supported tool contract.
- `documentation-tool-routing` is an optional prompt for clients that support prompts; it explains when to use the verified docs `search` and `fetch` tools and accepts an optional `topic` hint for more targeted routing advice.
- For tiny call examples, see `docs/MAJLISILMU_MCP_TOOL_EXAMPLES.md`.
